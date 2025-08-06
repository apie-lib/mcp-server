<?php
namespace Apie\McpServer\Tool;

use Apie\Common\ActionDefinitionProvider;
use Apie\Common\ActionDefinitions\CreateResourceActionDefinition;
use Apie\Common\ActionDefinitions\GetResourceActionDefinition;
use Apie\Common\ActionDefinitions\GetResourceListActionDefinition;
use Apie\Common\ActionDefinitions\ModifyResourceActionDefinition;
use Apie\Common\ActionDefinitions\RemoveResourceActionDefinition;
use Apie\Common\ActionDefinitions\ReplaceResourceActionDefinition;
use Apie\Common\ActionDefinitions\RunGlobalMethodDefinition;
use Apie\Common\ActionDefinitions\RunResourceMethodDefinition;
use Apie\Common\Actions\CreateObjectAction;
use Apie\Common\Actions\GetItemAction;
use Apie\Common\Actions\GetListAction;
use Apie\Common\Actions\ModifyObjectAction;
use Apie\Common\Actions\RemoveObjectAction;
use Apie\Common\Actions\RunAction;
use Apie\Common\Actions\RunItemMethodAction;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\Context\ApieContext;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\ContextConstants;
use Apie\Core\Identifiers\KebabCaseSlug;
use Apie\Core\Metadata\MetadataFactory;
use Apie\SchemaGenerator\SchemaGenerator;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputSchema;

class ToolFactory
{
    private const ALLOWED_FIELD_NAMES = [
        'required',
        'title',
        'description',
        'nullable',
        'minItems',
        'maxItems',
        'minProperties',
        'maxProperties',
        'minLength',
        'maxLength',
        'pattern',
        'example',
        'default',
        'minimum',
        'maximum'
    ];

    private const MAPPER = [
        CreateResourceActionDefinition::class => 'createCreateObjectTool',
        ReplaceResourceActionDefinition::class => 'createCreateObjectTool',
        ModifyResourceActionDefinition::class => 'createModifyObjectTool',
        GetResourceActionDefinition::class => 'createGetObjectTool',
        GetResourceListActionDefinition::class => 'createListObjectTool',
        RemoveResourceActionDefinition::class => 'createRemoveObjectTool',
        RunResourceMethodDefinition::class => 'createObjectMethodCallTool',
        RunGlobalMethodDefinition::class => 'createGlobalMethodCallTool',
    ];

    public function __construct(
        private readonly ContextBuilderFactory $contextBuilder,
        private readonly SchemaGenerator $schemaGenerator,
        private readonly BoundedContextHashmap $boundedContextHashmap,
        private readonly ActionDefinitionProvider $actionDefinitionProvider
    ) {
    }

    public function createList(): ListToolsResult
    {
        $context = $this->contextBuilder->createGeneralContext(
            [
                ToolFactory::class => $this,
                ContextConstants::MCP_SERVER => true,
                SchemaGenerator::class => $this->schemaGenerator
            ]
        );
        $tools = [];
        foreach ($this->boundedContextHashmap as $id => $boundedContext) {
            $subcontext = $context
                ->withContext(
                    ContextConstants::BOUNDED_CONTEXT_ID,
                    $id
                )
                ->withContext(
                    BoundedContext::class,
                    $boundedContext
                );
            foreach ($this->actionDefinitionProvider->provideActionDefinitions($boundedContext, $subcontext) as $routeDefinition) {
                foreach (self::MAPPER as $className => $methodName) {
                    if (get_class($routeDefinition) === $className) {
                        $tools[] = $this->{$methodName}($routeDefinition);
                    }
                }
            }
        }
        return new ListToolsResult($tools);
    }

    public function findByName(string $name): Tool
    {
        $all = $this->createList();
        foreach ($all->tools as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        throw new \LogicException('Tool "' . $name . '" not found!');
    }

    public function createObjectMethodCallTool(
        RunResourceMethodDefinition $definition
    ) {
        $class = $definition->getResourceName();
        $method = $definition->getMethod();
        $name = 'run-object-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($method->getDeclaringClass())
            . '-method-'
            . KebabCaseSlug::fromClass($method);
        $data = json_decode(
            json_encode($this->schemaGenerator->createMethodSchema($method)->getSerializableData()),
            true
        );
        $tool = new Tool(
            $name,
            $this->toToolInputSchema(
                $data
            ),
            RunItemMethodAction::getDescription($class, $method)
        );
        $tool->{"x-definition"} = RunItemMethodAction::class;
        $tool->{"x-method-class"} = $method->getDeclaringClass()->name;
        $tool->{"x-method"} = $method->name;
        $tool->{"x-fields"} = RunItemMethodAction::getRouteAttributes($class, $method);

        return $tool;
    }

    /**
     * @see https://ai.google.dev/api/caching#Schema
     * @param array<string, mixed> $property
     * @return array<string, mixed>
     */
    private function filterProperty(array $property): array
    {
        $filtered = [
            'type' => $property['type'] ?? 'object',
        ];
        foreach (self::ALLOWED_FIELD_NAMES as $fieldName) {
            if (isset($property[$fieldName])) {
                $filtered[$fieldName] = $property[$fieldName];
            }
        }
        if (isset($property['format']) && $property['format'] === 'date-time') {
            $filtered['format'] = 'date-time';
        }
        if (isset($property['enum'])) {
            $filtered['enum'] = $property['enum'];
            $filtered['format'] = 'enum';
        }

        if ($filtered['type'] === 'object') {
            $filtered['properties'] = [];
            foreach ($property['properties'] ?? [] as $key => $subProperty) {
                $filtered['properties'][$key] = $this->filterProperty($subProperty);
            }
        }
        if ($property['type'] === 'array' && isset($property['items'])) {
            $filtered['items'] = $this->filterProperty($property['items'] ?? []);
        }
        if (isset($property['anyOf'])) {
            $filtered['anyOf'] = [];
            foreach ($property['anyOf'] as $key => $subProperty) {
                $filtered['anyOf'][$key] = $this->filterProperty($subProperty);
            }
        } else if(isset($property['oneOf'])) {
            $filtered['anyOf'] = [];
            foreach ($property['oneOf'] as $key => $subProperty) {
                $filtered['anyOf'][$key] = $this->filterProperty($subProperty);
            }
        } else if(isset($property['allOf'])) {
            $filtered['anyOf'] = [];
            foreach ($property['allOf'] as $key => $subProperty) {
                $filtered['anyOf'][$key] = $this->filterProperty($subProperty);
            }
        }

        return $filtered;
    }

    /**
     * Gemini API is quite strict what it supports and our JSON schema is too accurate.
     * We have to strip details because of it.
     * 
     * @param array<string, mixed> $input
     */
    public function toToolInputSchema(array $input): ToolInputSchema
    {
        $filtered = $this->filterProperty($input);
        return ToolInputSchema::fromArray(
            $filtered
        );
    }

    public function createCreateObjectTool(CreateResourceActionDefinition|ReplaceResourceActionDefinition $definition): Tool
    {
        $class = $definition->getResourceName();
        $name = ($definition instanceof CreateResourceActionDefinition ? 'create-object-' : 'replace-object-')
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class);
        $data = json_decode(
            json_encode($this->schemaGenerator->createSchema($class->name)->getSerializableData()),
            true
        );
        if ($definition instanceof ReplaceResourceActionDefinition && !isset($data['properties']['id'])) {
            $data['properties']['id'] = ['type' => 'string'];
            $data['required'] ??= [];
            $data['required'][] = 'id';
        }
        $tool = new Tool(
            $name,
            $this->toToolInputSchema($data),
            CreateObjectAction::getDescription($class)
        );
        $tool->{"x-definition"} = CreateObjectAction::class;
        $tool->{"x-fields"} = CreateObjectAction::getRouteAttributes($class);

        return $tool;
    }

    public function createModifyObjectTool(ModifyResourceActionDefinition $definition): Tool
    {
        $class = $definition->getResourceName();
        $name = 'modify-object-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class);
        $data = json_decode(
            json_encode($this->schemaGenerator->createSchema($class->name)->getSerializableData()),
            true
        );
        $modifiableKeys = MetadataFactory::getModificationMetadata($class, new ApieContext())->getHashmap()->toArray();
        foreach ($data['properties'] ?? [] as $prop => $value) {
            if (!isset($modifiableKeys[$prop])) {
                unset($data['properties'][$prop]);
            }
        }
        if (!isset($data['properties']['id'])) {
            $data['properties']['id'] = ['type' => 'string'];
        }
        $data['required'] = ['id'];

        $tool = new Tool(
            $name,
            $this->toToolInputSchema($data),
            ModifyObjectAction::getDescription($class)
        );
        $tool->{"x-definition"} = ModifyObjectAction::class;
        $tool->{"x-fields"} = ModifyObjectAction::getRouteAttributes($class);

        return $tool;
    }

    public function createRemoveObjectTool(RemoveResourceActionDefinition $definition): Tool
    {
        $class = $definition->getResourceName();
        $name = 'remove-object-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class);
        $tool = new Tool(
            $name,
            $this->toToolInputSchema(
                [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string'
                        ],
                    ],
                    'required' => ['id']
                ]
            ),
            RemoveObjectAction::getDescription($class)
        );
        $tool->{"x-definition"} = RemoveObjectAction::class;
        $tool->{"x-fields"} = RemoveObjectAction::getRouteAttributes($class);

        return $tool;
    }

    public function createGetObjectTool(GetResourceActionDefinition $definition): Tool
    {
        $class = $definition->getResourceName();
        $name = 'get-object-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class);
        $tool = new Tool(
            $name,
            $this->toToolInputSchema(
                [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string'
                        ],
                    ],
                    'required' => ['id']
                ]
            ),
            GetItemAction::getDescription($class)
        );
        $tool->{"x-definition"} = GetItemAction::class;
        $tool->{"x-fields"} = GetItemAction::getRouteAttributes($class);

        return $tool;
    }

    public function createListObjectTool(GetResourceListActionDefinition $definition): Tool
    {
        $class = $definition->getResourceName();
        $name = 'all-object-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class);
        $tool = new Tool(
            $name,
            $this->toToolInputSchema(
                [
                    'type' => 'object',
                    'properties' => [
                    ],
                    'required' => []
                ]
            ),
            GetListAction::getDescription($class)
        );
        $tool->{"x-definition"} = GetListAction::class;
        $tool->{"x-fields"} = GetListAction::getRouteAttributes($class);

        return $tool;
    }

    public function createGlobalMethodCallTool(
        RunGlobalMethodDefinition $definition
    ) {
        $method = $definition->getMethod();
        $class = $method->getDeclaringClass();
        $name = 'run-global-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class)
            . '-method-'
            . KebabCaseSlug::fromClass($method);
        $data = json_decode(
            json_encode($this->schemaGenerator->createMethodSchema($method)->getSerializableData()),
            true
        );
        $tool = new Tool(
            $name,
            $this->toToolInputSchema(
                $data
            ),
            RunAction::getDescription($class, $method)
        );
        $tool->{"x-definition"} = RunAction::class;
        $tool->{"x-method-class"} = $method->getDeclaringClass()->name;
        $tool->{"x-method"} = $method->name;
        $tool->{"x-fields"} = RunAction::getRouteAttributes($class, $method);

        return $tool;
    }
}
