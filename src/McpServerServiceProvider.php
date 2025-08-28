<?php
namespace Apie\McpServer;

use Apie\ServiceProviderGenerator\UseGeneratedMethods;
use Illuminate\Support\ServiceProvider;

/**
 * This file is generated with apie/service-provider-generator from file: mcp_server.yaml
 * @codeCoverageIgnore
 */
class McpServerServiceProvider extends ServiceProvider
{
    use UseGeneratedMethods;

    public function register()
    {
        $this->app->singleton(
            \Apie\McpServer\RunMcpServerCommand::class,
            function ($app) {
                return new \Apie\McpServer\RunMcpServerCommand(
                    $app->make(\Apie\McpServer\Factory\RunnerFactoryInterface::class),
                    $app->make(\Apie\McpServer\Tool\ToolFactory::class),
                    $app->make(\Apie\McpServer\Tool\ToolRunner::class),
                    $app->make(\Psr\Log\LoggerInterface::class)
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\McpServer\RunMcpServerCommand::class,
            array(
              0 =>
              array(
                'name' => 'console.command',
              ),
            )
        );
        $this->app->tag([\Apie\McpServer\RunMcpServerCommand::class], 'console.command');
        $this->app->bind(\Apie\McpServer\Factory\RunnerFactoryInterface::class, \Apie\McpServer\Factory\RunnerFactory::class);
        
        $this->app->singleton(
            \Apie\McpServer\Factory\RunnerFactory::class,
            function ($app) {
                return new \Apie\McpServer\Factory\RunnerFactory(
                    $app->make(\Psr\Log\LoggerInterface::class)
                );
            }
        );
        $this->app->singleton(
            \Apie\McpServer\Tool\ToolRunner::class,
            function ($app) {
                return new \Apie\McpServer\Tool\ToolRunner(
                    $app->make(\Apie\Core\ContextBuilders\ContextBuilderFactory::class),
                    $app->make('apie'),
                    $app->make(\Psr\Log\LoggerInterface::class),
                    $app->bound(\Apie\Console\ConsoleCliStorage::class) ? $app->make(\Apie\Console\ConsoleCliStorage::class) : null
                );
            }
        );
        $this->app->singleton(
            \Apie\McpServer\Tool\ToolFactory::class,
            function ($app) {
                return new \Apie\McpServer\Tool\ToolFactory(
                    $app->make(\Apie\Core\ContextBuilders\ContextBuilderFactory::class),
                    $app->make(\Apie\SchemaGenerator\SchemaGenerator::class),
                    $app->make(\Apie\Core\BoundedContext\BoundedContextHashmap::class),
                    $app->make(\Apie\Common\ActionDefinitionProvider::class)
                );
            }
        );
        $this->app->singleton(
            \Apie\McpServer\Controllers\RemoteMcpController::class,
            function ($app) {
                return new \Apie\McpServer\Controllers\RemoteMcpController(
                    $app->make(\Apie\McpServer\Tool\ToolFactory::class),
                    $app->make(\Apie\McpServer\Tool\ToolRunner::class),
                    $app->make(\Psr\Log\LoggerInterface::class),
                    $app->make('apie.mcp_store')
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\McpServer\Controllers\RemoteMcpController::class,
            array(
              0 =>
              array(
                'name' => 'controller.service_arguments',
              ),
            )
        );
        $this->app->tag([\Apie\McpServer\Controllers\RemoteMcpController::class], 'controller.service_arguments');
        $this->app->bind('apie.mcp_store', \Mcp\Server\Transport\Http\SessionStoreInterface::class);
        
        $this->app->singleton(
            \Apie\McpServer\RouteDefinitions\McpServerRouteDefinitionProvider::class,
            function ($app) {
                return new \Apie\McpServer\RouteDefinitions\McpServerRouteDefinitionProvider(
                    $this->parseArgument('%apie.remote_mcp_path%', \Apie\McpServer\RouteDefinitions\McpServerRouteDefinitionProvider::class, 0)
                );
            }
        );
        \Apie\ServiceProviderGenerator\TagMap::register(
            $this->app,
            \Apie\McpServer\RouteDefinitions\McpServerRouteDefinitionProvider::class,
            array(
              0 =>
              array(
                'name' => 'apie.common.route_definition',
              ),
            )
        );
        $this->app->tag([\Apie\McpServer\RouteDefinitions\McpServerRouteDefinitionProvider::class], 'apie.common.route_definition');
        $this->app->singleton(
            \Mcp\Server\Transport\Http\InMemorySessionStore::class,
            function ($app) {
                return new \Mcp\Server\Transport\Http\InMemorySessionStore(
                
                );
            }
        );
        $this->app->singleton(
            \Mcp\Server\Transport\Http\FileSessionStore::class,
            function ($app) {
                return new \Mcp\Server\Transport\Http\FileSessionStore(
                    $this->parseArgument('%kernel.cache_dir%/mcp_server_sessions', \Mcp\Server\Transport\Http\FileSessionStore::class, 0)
                );
            }
        );
        $this->app->bind(\Mcp\Server\Transport\Http\SessionStoreInterface::class, \Mcp\Server\Transport\Http\FileSessionStore::class);
        
        
    }
}
