<?php
namespace Apie\McpServer\Factory;

use Apie\McpServer\Runner\InlineRunner;
use Mcp\Server\Server;
use Psr\Log\LoggerInterface;

class InlineRunnerFactory implements RunnerFactoryInterface
{
    /**
     * @param array<int, JsonRpcMessage> $messages
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private array $messages = []
    ) {
    }

    public function createRunner(Server $server): InlineRunner
    {
        return new InlineRunner(
            $server,
            $server->createInitializationOptions(),
            $this->logger,
            $this->messages
        );
    }
}
