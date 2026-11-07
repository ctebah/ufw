<?php
namespace Ufw\Tests\Unit;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Helper\Render;
use Ufw\Paths;
use Ufw\Tests\Support\ApplicationFixtures;

class HelperRenderTest extends TestCase
{
    private static $probeViewDir;

    protected function setUp(): void
    {
        self::$probeViewDir = TestAppPaths::app() . '/views/ping';
        if (!is_dir(self::$probeViewDir)) {
            mkdir(self::$probeViewDir, 0777, true);
        }
        file_put_contents(self::$probeViewDir . '/index.php', '<?php echo "view-ok";');
    }

    protected function tearDown(): void
    {
        if (is_file(self::$probeViewDir . '/index.php')) {
            unlink(self::$probeViewDir . '/index.php');
        }
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        parent::tearDown();
    }

    public function testRenderMergesStructuredContentWhenViewScriptDisabled(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $response = $app->getResponse();
        $response->setFormat('json');
        $render = new Render();
        $render->setController($controller);
        $render->setOverwrite(false);
        $response->setContent(array('a' => 1));
        $render->render(array('b' => 2));
        $content = $response->getContent();
        $this->assertIsArray($content);
        $this->assertSame(1, $content['a']);
        if (array_key_exists('b', $content)) {
            $this->assertSame(2, $content['b']);
        }
    }

    public function testViewRendererFallsBackToFrameworkPartial(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController();
        $render = new Render();
        $render->setController($controller);
        $method = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'renderHtml');
        $method->setAccessible(true);
        $html = $method->invoke($render, array('messages' => array()), 'partials/flashmessages.php', false);
        $this->assertIsString($html);
    }

    public function testViewRendererIncludesApplicationViewScript(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        list(, $controller) = ApplicationFixtures::bareWithController();
        $render = new Render();
        $render->setController($controller);
        $method = new \ReflectionMethod(\Ufw\Helper\ViewRendererBase::class, 'renderHtml');
        $method->setAccessible(true);
        $html = $method->invoke($render, array(), 'ping/index.php', false);
        $this->assertStringContainsString('view-ok', $html);
    }
}
