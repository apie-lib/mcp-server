<img src="https://raw.githubusercontent.com/apie-lib/apie-lib-monorepo/main/docs/apie-logo.svg" width="100px" align="left" />
<h1>mcp-server</h1>






 [![Latest Stable Version](https://poser.pugx.org/apie/mcp-server/v)](https://packagist.org/packages/apie/mcp-server) [![Total Downloads](https://poser.pugx.org/apie/mcp-server/downloads)](https://packagist.org/packages/apie/mcp-server) [![Latest Unstable Version](https://poser.pugx.org/apie/mcp-server/v/unstable)](https://packagist.org/packages/apie/mcp-server) [![License](https://poser.pugx.org/apie/mcp-server/license)](https://packagist.org/packages/apie/mcp-server) [![PHP Composer](https://apie-lib.github.io/projectCoverage/coverage-mcp-server.svg)](https://apie-lib.github.io/projectCoverage/mcp-server/index.html)  

[![PHP Composer](https://github.com/apie-lib/mcp-server/actions/workflows/php.yml/badge.svg?event=push)](https://github.com/apie-lib/mcp-server/actions/workflows/php.yml)

This package is part of the [Apie](https://github.com/apie-lib) library.
The code is maintained in a monorepo, so PR's need to be sent to the [monorepo](https://github.com/apie-lib/apie-lib-monorepo/pulls)

## Documentation
Exposes Apie actions and schemas as tools through the Model Context Protocol (MCP), so
an AI agent can discover and call your bounded-context actions. It builds on
`apie/schema-generator` for tool definitions and `logiscape/mcp-sdk-php` for the
protocol implementation.

### Standalone usage
```bash
composer require apie/mcp-server
```
Use `Apie\McpServer\Factory\InlineRunnerFactory` to create an in-process MCP runner
around your own `Apie\Core\ContextBuilders\ContextBuilderFactory` and bounded contexts,
or the `apie:mcp-server` console command (`RunMcpServerCommand`) for a stdio server. No
Laravel or Symfony application is required for the inline transport, only the Apie core
services.

### Symfony integration
`apie/apie-bundle` loads `packages/mcp-server/mcp_server.yaml`, which registers the
`apie:mcp-server` console command, `Apie\McpServer\Controllers\RemoteMcpController` for
a remote HTTP endpoint (configured through the bundle's `remote_mcp_path` option), and a
`Mcp\Server\Transport\Http\SessionStoreInterface` (file-based by default, in-memory in
the `test` environment) for tracking MCP sessions.

### Laravel integration
`apie/laravel-apie` registers the generated `Apie\McpServer\McpServerServiceProvider`
(built from `mcp_server.yaml`), exposing the same Artisan console command and remote MCP
controller/route wired to the application's own bounded contexts and session store.
