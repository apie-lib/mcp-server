<?php
namespace Apie\McpServer\Runner;

use Apie\McpServer\Exception\StopRunnerException;
use Apie\McpServer\Transport\InMemoryTransport;
use Mcp\Server\InitializationOptions;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use Mcp\Server\ServerSession;
use Psr\Log\LoggerInterface;

class InlineRunner extends ServerRunner
{

    /**
     * @param array<int, JsonRpcMessage> $messages
     */
    public function __construct(
        private readonly Server $server,
        private readonly InitializationOptions $initOptions,
        LoggerInterface $logger,
        private readonly array $messages = []
    ) {
        parent::__construct($server, $initOptions, $logger);
    }

    public function run(): void
    {

        try {
            $transport = new InMemoryTransport($this->messages);

            $session = new ServerSession(
                $transport,
                $this->initOptions,
                $this->logger
            );

            // Connect the server with the session
            $this->server->setSession($session);

            // Add handlers
            $session->registerHandlers($this->server->getHandlers());
            $session->registerNotificationHandlers($this->server->getNotificationHandlers());
            $this->logger->info('Server started');
            $session->start();

            
        } catch (StopRunnerException) {
            $this->logger->info('Runner has ended');
            return;
        } catch (\Exception $e) {
            $this->logger->error('Server error: ' . $e->getMessage());
            throw $e;
        } finally {
            if (isset($session)) {
                $session->stop();
            }
            if (isset($transport)) {
                $transport->stop();
            }
        }
    }
}
