<?php
namespace Apie\McpServer\Factory;

use Apie\McpServer\Runner\InlineRunner;
use Mcp\Server\HttpServerRunner;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;

interface RunnerFactoryInterface
{
    public function createRunner(Server $server): ServerRunner|HttpServerRunner|InlineRunner;
}
