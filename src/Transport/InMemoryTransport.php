<?php
namespace Apie\McpServer\Transport;

use Apie\McpServer\Exception\StopRunnerException;
use Mcp\Server\Transport\Transport;
use Mcp\Types\JsonRpcMessage;

/**
 * The mcp server package we use have no testability classes, so we use our own in-memory transport.
 * This allows us to test the server without needing a real transport layer.
 */
class InMemoryTransport implements Transport
{
    /**
     * @var array<int, JsonRpcMessage> $written
     */
    public array $written = [];

    /**
     * @param array<int, JsonRpcMessage> $messages
     */
    public function __construct(
        private array $messages
    ) {
    }
    private bool $isRunning = false;

    public function readMessage(): ?JsonRpcMessage
    {
        if (!$this->isRunning) {
            throw new StopRunnerException('Transport is not running');
        }
        $res = array_shift($this->messages) ?: null;
        if (empty($this->messages)) {
            $this->isRunning = false;
        }
        return $res;
    }

    /**
     * Write a message to the transport
     *
     * @param JsonRpcMessage $message The message to write
     * @throws \Exception if an error occurs while writing
     */
    public function writeMessage(JsonRpcMessage $message): void
    {
        if (!$this->isRunning) {
            throw new StopRunnerException('Transport is not running');
        }
        $this->written[] = $message;
    }
    public function start(): void
    {
        $this->isRunning = true;
    }

    public function stop(): void
    {
        $this->isRunning = false;
        $this->messages = [];
    }
}
