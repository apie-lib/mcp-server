<?php
namespace Apie\McpServer\Tool;

use Apie\Common\ActionDefinitionProvider;
use Apie\Common\ActionDefinitions\CreateResourceActionDefinition;
use Apie\Common\ActionDefinitions\GetResourceActionDefinition;
use Apie\Common\ActionDefinitions\RemoveResourceActionDefinition;
use Apie\Common\Actions\CreateObjectAction;
use Apie\Common\Actions\GetItemAction;
use Apie\Common\Actions\RemoveObjectAction;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\ContextConstants;
use Apie\Core\Identifiers\KebabCaseSlug;
use Apie\HtmlBuilders\ResourceActions\RemoveResourceAction;
use Apie\SchemaGenerator\SchemaGenerator;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputSchema;

class ToolFactory
{
    private const MAPPER = [
        CreateResourceActionDefinition::class => 'createCreateObjectTool',
        GetResourceActionDefinition::class => 'createGetObjectTool',
        RemoveResourceActionDefinition::class => 'createRemoveObjectTool',
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

    public function createCreateObjectTool(CreateResourceActionDefinition $definition): Tool
    {
        $class = $definition->getResourceName();
        $name = 'create-object-'
            . $definition->getBoundedContextId()->toNative()
            . '-'
            . KebabCaseSlug::fromClass($class);
        $data = json_decode(
            json_encode($this->schemaGenerator->createSchema($class->name)->getSerializableData()),
            true
        );
        $tool = new Tool(
            $name,
            ToolInputSchema::fromArray(
                $data
            ),
            CreateObjectAction::getDescription($class)
        );
        $tool->{"x-definition"} = CreateObjectAction::class;
        $tool->{"x-fields"} = CreateObjectAction::getRouteAttributes($class);

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
            ToolInputSchema::fromArray(
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
            ToolInputSchema::fromArray(
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
}
