<?php
namespace Apie\McpServer\RouteDefinitions;

use Apie\Common\Enums\UrlPrefix;
use Apie\Common\Interfaces\HasRouteDefinition;
use Apie\Common\Lists\UrlPrefixList;
use Apie\Core\Enums\RequestMethod;
use Apie\Core\ValueObjects\UrlRouteDefinition;
use Apie\McpServer\Controllers\RemoteMcpController;

class ExternalMcpRouteDefinition implements HasRouteDefinition
{
    public function __construct(private readonly UrlRouteDefinition $url, private readonly RequestMethod $method)
    {
    }

    public function getOperationId(): string
    {
        return 'mcp_remote_' . strtolower($this->method->value);
    }

    public function getMethod(): RequestMethod
    {
        return $this->method;
    }

    public function getUrl(): UrlRouteDefinition
    {
        return $this->url;
    }

    public function getController(): string
    {
        return RemoteMcpController::class;
    }
    
    public function getRouteAttributes(): array
    {
        return [
        ];
    }

    public function getUrlPrefixes(): UrlPrefixList
    {
        return new UrlPrefixList([UrlPrefix::API, UrlPrefix::CMS]);
    }
}
