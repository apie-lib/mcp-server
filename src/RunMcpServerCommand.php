<?php
namespace Apie\McpServer;

use Apie\McpServer\Factory\RunnerFactoryInterface;
use Apie\McpServer\Tool\ToolFactory;
use Apie\McpServer\Tool\ToolRunner;
use Mcp\Server\Server;
use Symfony\Component\Console\Command\Command;

class RunMcpServerCommand extends \Symfony\Component\Console\Command\Command
{
    public function __construct(
        private readonly RunnerFactoryInterface $runnerFactory,
        private readonly ToolFactory $toolFactory,
        private readonly ToolRunner $toolRunner,
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
        $output->writeln("Creating MCP Server");
        $server = new Server('apie-server');
        $server->registerHandler('tools/list', function ($params) {
            return $this->toolFactory->createList();
        });
        $server->registerHandler('tools/call', function ($params) {
            $name = $params->name;
            $tool = $this->toolFactory->findByName($name);
            return $this->toolRunner->run($tool, $params);
        });

        $output->writeln('MCP Server is running...');
        $runner = $this->runnerFactory->createRunner($server);
        $runner->run();
        $output->writeln('Runner has ended');
        return Command::SUCCESS;
    }
}
