<?php
namespace Apie\Tests\McpServer;

use Apie\Common\ActionDefinitionProvider;
use Apie\Common\ApieFacade;
use Apie\Common\ContextBuilderFactory;
use Apie\Common\Interfaces\RouteDefinitionProviderInterface;
use Apie\Common\Tests\Concerns\ProvidesApieFacade;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Core\Datalayers\InMemory\InMemoryDatalayer;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\TestHelpers\TestWithInMemoryDatalayer;
use Apie\McpServer\Factory\InlineRunnerFactory;
use Apie\McpServer\RunMcpServerCommand;
use Apie\McpServer\Tool\ToolFactory;
use Apie\McpServer\Tool\ToolRunner;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\SchemaGenerator\SchemaGenerator;
use Apie\Serializer\DecoderHashmap;
use Apie\Serializer\Serializer;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\RequestId;
use Mcp\Types\RequestParams;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

class RunMcpServerCommandTest extends TestCase
{
    use TestWithInMemoryDatalayer;

    private function createRequestParams(array $data): RequestParams
    {
        $res = new RequestParams();
        foreach ($data as $key => $value) {
            $res->$key = $value;
        }

        return $res;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testLegacyToolCall()
    {
        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $contextBuilder = ContextBuilderFactory::create($hashmap, DecoderHashmap::create());
        $apieFacade = new ApieFacade(
            $this->createMock(RouteDefinitionProviderInterface::class),
            $hashmap,
            Serializer::create(),
            $this->givenAnInMemoryDataLayer(new BoundedContextId('example'))
        );
        $testItem = new RunMcpServerCommand(
            new InlineRunnerFactory(
                new NullLogger(),
                [
                    new JsonRpcMessage(
                        new JSONRPCRequest(
                            "2.0",
                            new RequestId('1'),
                            $this->createRequestParams([
                                'clientInfo' => [
                                    'name' => 'TestClient',
                                    'version' => '1.0.0',
                                ],
                                'protocolVersion' => '1.0.0',
                            ]),
                            'initialize',
                        )
                    ),
                    new JsonRpcMessage(
                        new JSONRPCRequest(
                            "2.0",
                            new RequestId('2'),
                            null,
                            'tools/list',
                        )
                    ),
                    new JsonRpcMessage(
                        new JSONRPCRequest(
                            "2.0",
                            new RequestId('3'),
                            $this->createRequestParams([
                                'name' => 'create-object-default-user-with-address',
                                'arguments' => []
                            ]),
                            'tools/call',
                        )
                    ),
                ]
            ),
            new ToolFactory(
                $contextBuilder,
                new SchemaGenerator(ComponentsBuilderFactory::createComponentsBuilderFactory()),
                $hashmap,
                new ActionDefinitionProvider(),
            ),
            new ToolRunner(
                $contextBuilder,
                $apieFacade
            )
        );
        $tester = new CommandTester($testItem);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        // the output of the command in the console
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Runner has ended', $output);

    }
}
