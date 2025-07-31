<?php
namespace Apie\McpServer\Tool;

use Apie\Common\ActionDefinitionProvider;
use Apie\Common\ActionDefinitions\CreateResourceActionDefinition;
use Apie\Common\Actions\CreateObjectAction;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\BoundedContext\BoundedContextHashmap;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\ContextConstants;
use Apie\Core\Identifiers\KebabCaseSlug;
use Apie\Core\Identifiers\PascalCaseSlug;
use Apie\HtmlBuilders\ResourceActions\CreateResourceAction;
use Apie\SchemaGenerator\SchemaGenerator;
use Mcp\Types\ListToolsResult;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputSchema;

class ToolFactory
{
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
                if ($routeDefinition instanceof CreateResourceActionDefinition) {
                    $tools[] = $this->createCreateObjectTool($routeDefinition);
                }
            }
        }
        return new ListToolsResult($tools);
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
}
