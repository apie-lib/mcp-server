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
                    $app->make(\Apie\McpServer\Tool\ToolRunner::class)
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
                    $app->make('apie')
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
        
    }
}
