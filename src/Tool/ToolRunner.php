<?php
namespace Apie\McpServer\Tool;

use Apie\Common\ApieFacade;
use Apie\Core\Actions\ActionResponse;
use Apie\Core\Actions\ActionResponseStatus;
use Apie\Core\ContextBuilders\ContextBuilderFactory;
use Apie\Core\ContextConstants;
use Apie\Core\ValueObjects\Utils;
use Mcp\Types\CallToolResult;
use Mcp\Types\TextContent;
use Mcp\Types\Tool;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

class ToolRunner
{
    public function __construct(
        private readonly ContextBuilderFactory $contextBuilder,
        private readonly ApieFacade $apieFacade,
    ) {
    }
    public function run(Tool $tool, array $params, ?ServerRequestInterface $request = null): CallToolResult
    {
        $action = new ReflectionClass($tool->{"x-definition"});
        $fields = Utils::toArray($tool->{"x-fields"});
        $fields[ContextConstants::MCP_SERVER] = true;
        $fields[Tool::class] = true;
        $fields[ContextConstants::RAW_CONTENTS] = $params;
        if (isset($params['id'])) {
            $fields[ContextConstants::RESOURCE_ID] = $params['id'];
        }
        $context = $request
            ? $this->contextBuilder->createFromRequest($request, $fields)
            : $this->contextBuilder->createGeneralContext($fields);
        $action = $this->apieFacade->createAction($context);
        /** @var ActionResponse $data */
        $data = ($action)($context, $params);
        return $this->actionResponseToToolResult($data);
    }

    private function actionResponseToToolResult(ActionResponse $input): CallToolResult
    {
        switch ($input->status) {
            case ActionResponseStatus::CLIENT_ERROR:
            case ActionResponseStatus::AUTHORIZATION_ERROR:
            case ActionResponseStatus::OUTPUT_ERROR:
            case ActionResponseStatus::PERISTENCE_ERROR:
            case ActionResponseStatus::SERVER_ERROR:
                return new CallToolResult(
                    [
                        new TextContent(json_encode($input->getResultAsNativeData())),
                    ],
                    true
                );

            case ActionResponseStatus::DELETED:
                return new CallToolResult(
                    [
                        new TextContent(json_encode("Resource was deleted correctly.")),
                    ],
                    false
                );
            case ActionResponseStatus::NOT_FOUND:
                return new CallToolResult(
                    [
                        new TextContent(json_encode("Resource was not found.")),
                    ],
                    true
                );
            
            case ActionResponseStatus::CREATED:
            case ActionResponseStatus::SUCCESS:
                if (isset($input->error)) {
                    throw $input->error;
                }
                return new CallToolResult(
                    [
                        new TextContent(json_encode($input->getResultAsNativeData())),
                    ],
                    false
                );
        }
        throw new \LogicException('Unknown status ' . $input->status->value);
    }
}
