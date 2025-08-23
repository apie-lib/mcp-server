<?php
namespace Apie\McpServer\RouteDefinitions;

use Apie\Common\Interfaces\GlobalRouteDefinitionProviderInterface;
use Apie\Common\RouteDefinitions\ActionHashmap;
use Apie\Core\BoundedContext\BoundedContext;
use Apie\Core\Context\ApieContext;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;

class McpServerRouteDefinitionProvider implements GlobalRouteDefinitionProviderInterface
{
    public function __construct(private ?string $remotePath)
    {
    }

    public function getGlobalRoutes(): ActionHashmap
    {
        if ($this->remotePath !== null) {
            return new ActionHashmap([
                'mcp_server_post' => new ExternalMcpRouteDefinition(new UrlRouteDefinition($this->remotePath), RequestMethod::POST),
                'mcp_server_get' => new ExternalMcpRouteDefinition(new UrlRouteDefinition($this->remotePath), RequestMethod::GET),
            ]);
        }
        return new ActionHashmap([]);
    }
    public function getActionsForBoundedContext(BoundedContext $boundedContext, ApieContext $apieContext): ActionHashmap
    {
    
        return new ActionHashmap([]);
    }
}
