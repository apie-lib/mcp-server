<?php
namespace Apie\Tests\McpServer\Tool;

use Apie\Common\ActionDefinitions\CreateResourceActionDefinition;
use Apie\Common\ActionDefinitions\RemoveResourceActionDefinition;
use Apie\Common\ActionDefinitions\RunGlobalMethodDefinition;
use Apie\Common\Actions\CreateObjectAction;
use Apie\Common\Actions\RemoveObjectAction;
use Apie\Common\Actions\RunAction;
use Apie\Common\ApieFacade;
use Apie\Common\ContextBuilderFactory;
use Apie\Common\Interfaces\RouteDefinitionProviderInterface;
use Apie\Core\Attributes\Context;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\ContextConstants;
use Apie\Core\Exceptions\EntityNotFoundException;
use Apie\Core\Identifiers\UuidV4;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\Order;
use Apie\Fixtures\Identifiers\OrderIdentifier;
use Apie\Fixtures\Lists\OrderLineList;
use Apie\Fixtures\TestHelpers\TestWithInMemoryDatalayer;
use Apie\McpServer\Tool\ToolRunner;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\Serializer;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Mcp\Types\Tool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

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
            '_meta' => [
                'x-definition' => CreateResourceActionDefinition::class,
                'x-fields' => [
                    ContextConstants::APIE_ACTION => CreateObjectAction::class,
                    ContextConstants::RESOURCE_NAME => Order::class,
                    ContextConstants::BOUNDED_CONTEXT_ID => 'default',
                ],
            ]
        ]);

        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $contextBuilder = ContextBuilderFactory::create($hashmap, DecoderHashmap::create());

        $apieFacade = new ApieFacade(
            $this->createMock(RouteDefinitionProviderInterface::class),
            $hashmap,
            Serializer::create(),
            $this->givenAnInMemoryDataLayer(new BoundedContextId('default'))
        );

        $toolRunner = new ToolRunner($contextBuilder, $apieFacade, new NullLogger());

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
    public function it_returns_a_message_if_a_resource_is_deleted()
    {
        $tool = Tool::fromArray([
            'name' => 'test-tool',
            'description' => 'Test tool',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'enum' => 'bar',
                    ]
                ]
            ],
            'parameters' => [],
            '_meta' => [
                'x-definition' => RemoveResourceActionDefinition::class,
                'x-fields' => [
                    ContextConstants::APIE_ACTION => RemoveObjectAction::class,
                    ContextConstants::RESOURCE_NAME => Order::class,
                    ContextConstants::BOUNDED_CONTEXT_ID => 'default',
                    LockFactory::class => new LockFactory(new InMemoryStore()),
                ],
            ]
        ]);

        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $contextBuilder = ContextBuilderFactory::create($hashmap, DecoderHashmap::create());

        $apieFacade = new ApieFacade(
            $this->createMock(RouteDefinitionProviderInterface::class),
            $hashmap,
            Serializer::create(),
            $this->givenAnInMemoryDataLayer(new BoundedContextId('default'))
        );

        $toolRunner = new ToolRunner($contextBuilder, $apieFacade, new NullLogger());

        $id = OrderIdentifier::createRandom();
        $order = new Order($id, new OrderLineList());
        $apieFacade->persistNew($order, $hashmap['other']);
        // Act
        $result = $toolRunner->run($tool, ['id' => $id->toNative()]);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);
        $this->assertStringContainsString('Resource was deleted correctly.', $result->content[0]->text);
        $this->assertFalse($result->isError);
    }


    public static function toolProvider(): \Generator
    {
        yield 'valid response' => [false, '42', 'validResponse'];
        yield 'permission denied' => [true, 'Action not allowed.', 'noPermissionMethod'];
        yield 'entity not found error' => [true, 'Resource was not found.', 'notFoundMethod'];
    }

    #[Test]
    #[DataProvider('toolProvider')]
    public function it_responds_with_different_flows(
        bool $expectedError,
        string $expectedContent,
        string $methodName
    ): void {
        $tool = Tool::fromArray([
            'name' => 'test-tool',
            'description' => 'Test tool',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                ]
            ],
            'parameters' => [],
            '_meta' => [
                'x-definition' => RunGlobalMethodDefinition::class,
                'x-fields' => [
                    ContextConstants::APIE_ACTION => RunAction::class,
                    ContextConstants::SERVICE_CLASS => self::class,
                    ContextConstants::METHOD_NAME => $methodName,
                    ContextConstants::BOUNDED_CONTEXT_ID => 'default',
                ],
            ]
        ]);

        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $contextBuilder = ContextBuilderFactory::create($hashmap, DecoderHashmap::create());

        $apieFacade = new ApieFacade(
            $this->createMock(RouteDefinitionProviderInterface::class),
            $hashmap,
            Serializer::create(),
            $this->givenAnInMemoryDataLayer(new BoundedContextId('default'))
        );

        $toolRunner = new ToolRunner($contextBuilder, $apieFacade, new NullLogger());
        // Act
        $result = $toolRunner->run($tool, []);

        // Assert
        $this->assertInstanceOf(CallToolResult::class, $result);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);
        $this->assertStringContainsString($expectedContent, $result->content[0]->text);
        $this->assertEquals($expectedError, $result->isError, $expectedError ? 'Error state is on' : 'Error state is off');
    }

    public static function noPermissionMethod(#[Context('does-not-exist')] string $ignored): string
    {
        return $ignored;
    }

    public static function notFoundMethod(): never
    {
        throw new EntityNotFoundException(OrderIdentifier::createRandom());
    }

    public static function validResponse(): int
    {
        return 42;
    }
}
