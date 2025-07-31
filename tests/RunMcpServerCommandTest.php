<?php
namespace Apie\Tests\McpServer;

use Apie\Common\ActionDefinitionProvider;
use Apie\Common\ContextBuilderFactory;
use Apie\Fixtures\BoundedContextFactory;
use Apie\McpServer\Factory\InlineRunnerFactory;
use Apie\McpServer\RunMcpServerCommand;
use Apie\McpServer\Tool\ToolFactory;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\SchemaGenerator\SchemaGenerator;
use Apie\Serializer\DecoderHashmap;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\RequestId;
use Mcp\Types\RequestParams;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

class RunMcpServerCommandTest extends TestCase
{
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
                                'name' => 'shutdown',
                                'arguments' => []
                            ]),
                            'tools/call',
                        )
                    ),
                ]
            ),
            new ToolFactory(
                ContextBuilderFactory::create($hashmap, DecoderHashmap::create()),
                new SchemaGenerator(ComponentsBuilderFactory::createComponentsBuilderFactory()),
                $hashmap,
                new ActionDefinitionProvider(),
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
