<?php
namespace Apie\McpServer\Exception;

/**
 * Exception is only thrown to stop the MCP server runner gracefully. Otherwise it would
 * never stop in the integration tests.
 */
class StopRunnerException extends \RuntimeException
{
    public function __construct(string $message = "Runner has been stopped", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
