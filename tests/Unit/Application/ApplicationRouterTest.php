<?php
namespace Ufw\Tests\Unit\Application;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Route\Literal;
use Ufw\Router;

class ApplicationRouterTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        Paths::setApplicationPath(TestAppPaths::app());
        parent::tearDown();
    }

    public function testConstructorLoadsDispatchFromFixtureAppConfig()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $configProp = new \ReflectionProperty(Application::class, 'config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, null);

        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);
        $construct = new \ReflectionMethod(Application::class, '__construct');
        $construct->setAccessible(true);
        $construct->invoke($app);

        $this->assertIsArray($app->getDispatchControllers());
        $defaults = $app->getDispatchDefaults();
        $this->assertIsArray($defaults);
    }

    public function testGetRouterBuildsFromDispatchRoutesSkippingInvalidEntries()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'routes' => array(
                    'home' => array(
                        'type' => Literal::class,
                        'path' => '/',
                        'defaults' => array('controller' => 'ping', 'action' => 'index'),
                    ),
                    0 => 'skip',
                ),
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $app->setDispatchControllers(array('ping'));
        $app->setDispatchDefaults(array('controller' => 'ping', 'action' => 'index'));

        $router = $app->getRouter();
        $this->assertInstanceOf(Router::class, $router);
    }

    public function testGetResponseIsLazySingletonOnApplication()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $first = $app->getResponse();
        $second = $app->getResponse();
        $this->assertSame($first, $second);
        $this->assertSame(200, $first->getStatusCode());
    }
}
