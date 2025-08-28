<?php
namespace Apie\McpServer;

use Apie\Core\ValueObjects\Utils;
use Apie\McpServer\Factory\RunnerFactoryInterface;
use Apie\McpServer\Tool\ToolFactory;
use Apie\McpServer\Tool\ToolRunner;
use Mcp\Server\Server;
use Mcp\Types\CallToolRequestParams;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;

class RunMcpServerCommand extends \Symfony\Component\Console\Command\Command
{
    public function __construct(
        private readonly RunnerFactoryInterface $runnerFactory,
        private readonly ToolFactory $toolFactory,
        private readonly ToolRunner $toolRunner,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct('apie:mcp-server');
    }

    protected function configure()
    {
        $this
            ->setDescription('Runs the MCP server to link with Apie')
            ->setHelp('This command allows you to run the MCP server for Apie.');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $output->writeln(json_encode([
            "jsonrpc" => "2.0",
            "method" => "notifications/progress",
            "params" => [
                "progressToken" => "apie_mcp_server_startup",
                "progress" => 50,
                "total" => 100,
                "message" => "Creating MCP Server..."
            ]
        ]));
        $server = new Server('apie-server', $this->logger);
        $server->registerHandler('tools/list', function () {
            return $this->toolFactory->createList();
        });
        $server->registerHandler('tools/call', function (CallToolRequestParams $params) {
            $name = $params->name;
            $tool = $this->toolFactory->findByName($name);
            $result = $this->toolRunner->run($tool, Utils::toArray($params->arguments ?? []));
            return $result;
        });

        $output->writeln(json_encode([
            "jsonrpc" => "2.0",
            "method" => "notifications/progress",
            "params" => [
                "progressToken" => "apie_mcp_server_startup",
                "progress" => 100,
                "total" => 100,
                "message" => "MCP Server is running..."
            ]
        ]));
        $runner = $this->runnerFactory->createRunner($server);
        $runner->run();
        $output->writeln(json_encode([
            "jsonrpc" => "2.0",
            "method" => "notifications/progress",
            "params" => [
                "progressToken" => "apie_mcp_server_shutdown",
                "progress" => 100,
                "total" => 100,
                "message" => "MCP Server is ending..."
            ]
        ]));
        return Command::SUCCESS;
    }
}
