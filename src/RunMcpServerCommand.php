<?php
namespace Apie\McpServer;

use Apie\McpServer\Factory\RunnerFactoryInterface;
use Apie\McpServer\Tool\ToolFactory;
use Mcp\Server\Server;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Symfony\Component\Console\Command\Command;

class RunMcpServerCommand extends \Symfony\Component\Console\Command\Command
{
    protected static $defaultName = 'apie:mcp-server';

    public function __construct(
        private readonly RunnerFactoryInterface $runnerFactory,
        private readonly ToolFactory $toolFactory
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Runs the MCP server to link with Apie')
            ->setHelp('This command allows you to run the MCP server for Apie.');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $output->writeln("Creating MCP Server");
        $server = new Server('apie-server');
        $server->registerHandler('tools/list', function ($params) {
            return $this->toolFactory->createList();
        });
        $server->registerHandler('tools/call', function ($params) {
            $name = $params->name;
            /*if ($name === 'shutdown') {

                throw new \RuntimeException('Server is shutting down');
                return new CallToolResult(
                        content: [new TextContent(
                            text: "Server is shutting down."
                        )]
                    );
            }*/
            // Handle other tool calls as needed
            return new \Mcp\Types\JSONRPCResponse('2.0', $params->id, null);
        });

        $output->writeln('MCP Server is running...');
        $runner = $this->runnerFactory->createRunner($server);
        $runner->run();
        $output->writeln('Runner has ended');
        return Command::SUCCESS;
    }
}
