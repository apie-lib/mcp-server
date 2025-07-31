<?php
namespace Apie\McpServer\Factory;

use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use Psr\Log\LoggerInterface;

class RunnerFactory implements RunnerFactoryInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }
    public function createRunner(
        Server $server,
    ): ServerRunner {
        $initOptions = $server->createInitializationOptions();
        return new ServerRunner($server, $initOptions, $this->logger);
    }
}
