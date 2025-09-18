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
use Apie\Core\Datalayers\ApieDatalayer;
use Apie\Core\Datalayers\ApieDatalayerWithFilters;
use Apie\Core\Identifiers\KebabCaseSlug;
use Apie\Core\Metadata\MetadataFactory;
use Apie\SchemaGenerator\SchemaGenerator;
use cebe\openapi\spec\Schema;
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
        $tool->_meta = [
            'x-definition' => RunItemMethodAction::class,
            'x-method-class' => $method->getDeclaringClass()->name,
            'x-method' => $method->name,
            'x-fields' => [
                ...RunItemMethodAction::getRouteAttributes($class, $method),
                ContextConstants::APIE_ACTION => RunItemMethodAction::class,
            ],
            "x-bounded-context-id" => $definition->getBoundedContextId()->toNative(),
        ];

        return $tool;
    }

    /**
     * @see https://ai.google.dev/api/caching#Schema
     * @param array<string, mixed>|Schema|stdClass $property
     * @return array<string, mixed>
     */
    private function filterProperty(array|Schema|\stdClass $property): array
    {
        if ($property instanceof Schema) {
            $property = (array) $property->getSerializableData();
        }
        if ($property instanceof \stdClass) {
            $property = (array) $property;
        }
        $filtered = [
            'type' => $property['type'] ?? 'object',
        ];
        foreach (self::ALLOWED_FIELD_NAMES as $fieldName) {
            if (isset($property[$fieldName])) {
                $filtered[$fieldName] = $property[$fieldName];
            }
        }
        // Gemini API only supports date-time and enum as formats and fail with 400 bad request otherwise.
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
            if (empty($filtered['properties'])) {
                unset($filtered['properties']);
            }
        }
        if ($filtered['type'] === 'array' && isset($property['items'])) {
            $filtered['items'] = $this->filterProperty($property['items'] ?? []);
        }
        if (isset($property['anyOf'])) {
            $filtered['anyOf'] = [];
            foreach ($property['anyOf'] as $key => $subProperty) {
                $filtered['anyOf'][$key] = $this->filterProperty($subProperty);
            }
        } elseif (isset($property['oneOf'])) {
            $filtered['anyOf'] = [];
            foreach ($property['oneOf'] as $key => $subProperty) {
                $filtered['anyOf'][$key] = $this->filterProperty($subProperty);
            }
        } elseif (isset($property['allOf'])) {
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
        $tool->_meta = [
            "x-definition" => CreateObjectAction::class,
            "x-fields" => [
                ...CreateObjectAction::getRouteAttributes($class),
                ContextConstants::APIE_ACTION => CreateObjectAction::class,
            ],
            "x-bounded-context-id" => $definition->getBoundedContextId()->toNative(),
        ];

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
        $tool->_meta = [
            "x-definition" => ModifyObjectAction::class,
            "x-fields" => [
                ...ModifyObjectAction::getRouteAttributes($class),
                ContextConstants::APIE_ACTION => ModifyObjectAction::class,
            ],
            "x-bounded-context-id" => $definition->getBoundedContextId()->toNative(),
        ];

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
        $tool->_meta = [
            "x-definition" => RemoveObjectAction::class,
            "x-fields" => [
                ...RemoveObjectAction::getRouteAttributes($class),
                ContextConstants::APIE_ACTION => RemoveObjectAction::class,
            ],
            "x-bounded-context-id" => $definition->getBoundedContextId()->toNative(),
        ];

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
        $tool->_meta = [
            "x-definition" => GetItemAction::class,
            "x-fields" => [
                ...GetItemAction::getRouteAttributes($class),
                ContextConstants::APIE_ACTION => GetItemAction::class,
            ],
            "x-bounded-context-id" => $definition->getBoundedContextId()->toNative(),
        ];

        return $tool;
    }

    public function createListObjectTool(GetResourceListActionDefinition $definition): Tool
    {
        $class = $definition->getResourceName();
        $name = 'all-object-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class);

        $context = $this->contextBuilder->createGeneralContext(
            [
                ToolFactory::class => $this,
                ContextConstants::MCP_SERVER => true,
                SchemaGenerator::class => $this->schemaGenerator,
                ContextConstants::BOUNDED_CONTEXT_ID => $definition->getBoundedContextId()->toNative(),
                BoundedContext::class => $this->boundedContextHashmap[$definition->getBoundedContextId()->toNative()],
            ]
        );
        $properties = [];
        $dataLayer = $context->getContext(ApieDatalayer::class, false);
        if ($dataLayer instanceof ApieDatalayerWithFilters) {
            $fieldMetadata = MetadataFactory::getResultMetadata(
                $class,
                $context
            )->getHashmap();
            $filterColumns = $dataLayer->getFilterColumns($class, $definition->getBoundedContextId());
            if ($filterColumns !== null) {
                $properties['filters'] = [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'minLength' => 1,
                        ],
                        'items_per_page' => [
                            'type' => 'integer',
                            'minimum' => 1
                        ],
                        'page' => [
                            'type' => 'integer',
                            'minimum' => 0
                        ],
                    ],
                    'required' => []
                ];
                foreach ($filterColumns as $filterColumn) {
                    $schema = new Schema([
                        'type' => 'string',
                        'minimum' => 1,
                    ]);
                    if (isset($fieldMetadata[$filterColumn])) {
                        $schema = $this->schemaGenerator->createSchema(
                            $fieldMetadata[$filterColumn]->allowsNull() ? '?string' : 'string',
                            true
                        );
                    }
                    $properties['filters']['properties'][$filterColumn] = $schema->getSerializableData();
                }
            }
            $orderByColumns = $dataLayer->getOrderByColumns($class, $definition->getBoundedContextId());
            if ($orderByColumns?->count()) {
                $values = [];
                foreach ($orderByColumns as $orderByColumn) {
                    array_push(
                        $values,
                        $orderByColumn,
                        '+' . $orderByColumn,
                        '-' . $orderByColumn
                    );
                }
                $properties['order_by'] = [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => $values,
                    ]
                ];
            }
        }
        
        $tool = new Tool(
            $name,
            $this->toToolInputSchema(
                [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => []
                ]
            ),
            GetListAction::getDescription($class)
        );
        $tool->_meta = [
            "x-definition" =>  GetListAction::class,
            "x-fields" => [
                ...GetListAction::getRouteAttributes($class),
                ContextConstants::APIE_ACTION => GetListAction::class,
            ],
            "x-bounded-context-id" => $definition->getBoundedContextId()->toNative(),
        ];

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
        $tool->_meta = [
            "x-definition" => RunAction::class,
            "x-method-class" => $method->getDeclaringClass()->name,
            "x-method" => $method->name,
            "x-fields" => [
                ...RunAction::getRouteAttributes($class, $method),
                ContextConstants::APIE_ACTION => RunAction::class,
            ],
            "x-bounded-context-id" => $definition->getBoundedContextId()->toNative(),
        ];

        return $tool;
    }
}
