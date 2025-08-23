<?php
namespace Apie\McpServer\Controllers;

use Apie\Core\ApieLib;
use Apie\McpServer\Tool\ToolFactory;
use Apie\McpServer\Tool\ToolRunner;
use Mcp\Server\HttpServerRunner;
use Mcp\Server\Server;
use Mcp\Server\Transport\Http\HttpMessage;
use Mcp\Server\Transport\Http\SessionStoreInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class RemoteMcpController
{
    public function __construct(
        private readonly ToolFactory $toolFactory,
        private readonly ToolRunner $toolRunner,
        private readonly LoggerInterface $logger,
        private readonly ?SessionStoreInterface $sessionStore = null
    ) {
    }
    public function __invoke(ServerRequestInterface $request): Response
    {
        $server = new Server('apie-server');
        $server->registerHandler('tools/list', function ($params) {
            return $this->toolFactory->createList();
        });
        $server->registerHandler('tools/call', function ($params) use ($request) {
            $name = $params->name;
            $tool = $this->toolFactory->findByName($name);
            return $this->toolRunner->run($tool, $params, $request);
        });
        // TODO: configurable options?
        $httpOptions = [
            'session_timeout' => 1800,
            'max_queue_size' => 500,
            'enable_sse' => false,
            'shared_hosting' => true,
            'server_header' => 'Apie-MCP-Server/' . ApieLib::VERSION,
        ];
        
        $runner = new HttpServerRunner(
            $server,
            $server->createInitializationOptions(),
            $httpOptions,
            $this->logger,
            $this->sessionStore
        );
        $message = new HttpMessage($request->getBody()->getContents());
        $message->setMethod($request->getMethod());
        foreach ($request->getHeaders() as $name => $values) {
            $message->setHeader($name, implode(', ', $values));
        }
        $result = $runner->handleRequest($message);

        return new Response(
            $result->getStatusCode(),
            $result->getHeaders(),
            Stream::create($result->getBody())
        );
    }
}
