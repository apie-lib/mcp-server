<?php
namespace Apie\Tests\McpServer\Tool;

use Apie\Common\ActionDefinitionProvider;
use Apie\Common\ActionDefinitions\CreateResourceActionDefinition;
use Apie\Common\ContextBuilderFactory;
use Apie\Core\BoundedContext\BoundedContextId;
use Apie\Fixtures\BoundedContextFactory;
use Apie\Fixtures\Entities\UserWithAutoincrementKey;
use Apie\McpServer\Tool\ToolFactory;
use Apie\SchemaGenerator\ComponentsBuilderFactory;
use Apie\SchemaGenerator\SchemaGenerator;
use Apie\Serializer\DecoderHashmap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ToolFactoryTest extends TestCase
{
    #[Test]
    public function it_can_create_object_tool()
    {
        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $testItem = new ToolFactory(
            ContextBuilderFactory::create($hashmap, DecoderHashmap::create()),
            new SchemaGenerator(ComponentsBuilderFactory::createComponentsBuilderFactory()),
            $hashmap,
            new ActionDefinitionProvider(),
        );
        $actual = $testItem->createCreateObjectTool(new CreateResourceActionDefinition(
            new ReflectionClass(UserWithAutoincrementKey::class),
            new BoundedContextId('other')
         ));
        $this->assertEquals('create-object-other-user-with-autoincrement-key', $actual->name);
    }

    #[Test]
    public function it_can_list_tools()
    {
        $hashmap = BoundedContextFactory::createHashmapWithMultipleContexts();
        $testItem = new ToolFactory(
            ContextBuilderFactory::create($hashmap, DecoderHashmap::create()),
            new SchemaGenerator(ComponentsBuilderFactory::createComponentsBuilderFactory()),
            $hashmap,
            new ActionDefinitionProvider(),
        );
        $fixturePath = __DIR__ . '/../../fixtures/tool-list.json';
        $actual = json_encode($testItem->createList(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($fixturePath, $actual);
        $this->assertEquals(file_get_contents($fixturePath), $actual);
    }
}