<?php
namespace Ufw\Tests\Unit\Application;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Paths;
use Ufw\Registry;

class ApplicationBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $this->resetApplicationState();
    }

    protected function tearDown(): void
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $this->resetApplicationState();
        parent::tearDown();
    }

    private function resetApplicationState()
    {
        Registry::getInstance()->remove('application');
        $config = new \ReflectionProperty(Application::class, 'config');
        $config->setAccessible(true);
        $config->setValue(null, null);
        $instances = new \ReflectionProperty(Application::class, '_instance');
        $instances->setAccessible(true);
        $instances->setValue(array());
    }

    public function testConstructorLoadsFixtureAppConfigAndDispatchControllers()
    {
        $app = new Application();
        $this->assertIsArray($app->getConfig());
        $this->assertArrayHasKey('assets', $app->getConfig());
        $this->assertSame($app, Registry::getInstance()->get('application'));
        $this->assertContains('ping', $app->getDispatchControllers());
        $this->assertSame('ping', $app->getDispatchDefaults()['controller']);
    }
}
