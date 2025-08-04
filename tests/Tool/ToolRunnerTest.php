<?php
namespace Apie\Tests\McpServer\Tool;

use Apie\Common\ActionDefinitions\CreateResourceActionDefinition;
use Apie\Common\ActionDefinitions\RunGlobalMethodDefinition;
use Apie\Common\Actions\CreateObjectAction;
use Apie\Common\Actions\RunAction;
use Apie\Common\ApieFacade;
use Apie\Common\ContextBuilderFactory;
use Apie\Common\Interfaces\RouteDefinitionProviderInterface;
use Apie\Core\Attributes\Context;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Identifiers\UuidV4;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\Order;
use Apie\Fixtures\TestHelpers\TestWithInMemoryDatalayer;
use Apie\McpServer\Tool\ToolRunner;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\Serializer;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Mcp\Types\Tool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolRunnerTest extends TestCase
{
    use TestWithInMemoryDatalayer;

    #[Test]
    public function it_always_returns_resource_when_created()
    {
        $tool = Tool::fromArray([
            'name' => 'test-tool',
            'description' => 'Test tool',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'foo' => [
                        'type' => 'string',
                        'enum' => 'bar',
                    ]
                ]
            ],
            'parameters' => [],
            'x-definition' => CreateResourceActionDefinition::class,
            'x-fields' => [
                ContextConstants::APIE_ACTION => CreateObjectAction::class,
                ContextConstants::RESOURCE_NAME => Order::class,
                ContextConstants::BOUNDED_CONTEXT_ID => 'default',
            ],
        ]);

        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $contextBuilder = ContextBuilderFactory::create($hashmap, DecoderHashmap::create());

        $apieFacade = new ApieFacade(
            $this->createMock(RouteDefinitionProviderInterface::class),
            $hashmap,
            Serializer::create(),
            $this->givenAnInMemoryDataLayer(new BoundedContextId('default'))
        );

        $toolRunner = new ToolRunner($contextBuilder, $apieFacade);

        $id = UuidV4::createRandom()->toNative();
        // Act
        $result = $toolRunner->run($tool, ['id' => $id, 'orderLines' => [], 'optionalTags' => ['test']]);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);
        $this->assertStringContainsString('"orderLines":[]', $result->content[0]->text);
        $this->assertFalse($result->isError);
    }

    #[Test]
    public function it_responds_with_access_denied(): void
    {
        $tool = Tool::fromArray([
            'name' => 'test-tool',
            'description' => 'Test tool',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                ]
            ],
            'parameters' => [],
            'x-definition' => RunGlobalMethodDefinition::class,
            'x-fields' => [
                ContextConstants::APIE_ACTION => RunAction::class,
                ContextConstants::SERVICE_CLASS => self::class,
                ContextConstants::METHOD_NAME => 'noPermissionMethod',
                ContextConstants::BOUNDED_CONTEXT_ID => 'default',
            ],
        ]);

        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $contextBuilder = ContextBuilderFactory::create($hashmap, DecoderHashmap::create());

        $apieFacade = new ApieFacade(
            $this->createMock(RouteDefinitionProviderInterface::class),
            $hashmap,
            Serializer::create(),
            $this->givenAnInMemoryDataLayer(new BoundedContextId('default'))
        );

        $toolRunner = new ToolRunner($contextBuilder, $apieFacade);
        // Act
        $result = $toolRunner->run($tool, []);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);
        $this->assertStringContainsString('Action not allowed.', $result->content[0]->text);
        $this->assertTrue($result->isError);
    }

    public static function noPermissionMethod(#[Context('does-not-exist')] string $ignored): string
    {
        return $ignored;
    }
}
