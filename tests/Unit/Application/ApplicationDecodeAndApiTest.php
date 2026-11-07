<?php
namespace Ufw\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Registry;
use Ufw\Route\Literal;
use Ufw\Router;
use Ufw\Tests\Support\TestHttpRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class ApplicationDecodeAndApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testDecodeRequestReturnsMatchedRouterRequest()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $app->setDispatchControllers(array('ping'));

        $request = TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'index',
        ), 'GET', '/ping');
        $router = new Router($app, $request, array(
            'home' => array(
                'type' => Literal::class,
                'path' => '/ping',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'constraints' => array('controller' => '~^ping$~'),
            ),
        ));
        $router->match();
        $app->setRouter($router);

        $decode = new \ReflectionMethod(Application::class, 'decodeRequest');
        $decode->setAccessible(true);
        $decoded = $decode->invoke($app);
        $this->assertSame('ping', $decoded->getController());
    }

    public function testHasActionResolvesRestApiControllerForGetOnIndex()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);

        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', 'index', 'GET', null);
        $this->assertIsArray($result);
        $this->assertSame('api', $result['kind']);
    }

    public function testScalarToStringCoversBoolAndNull()
    {
        $method = new \ReflectionMethod(Application::class, 'scalarToString');
        $method->setAccessible(true);
        $this->assertSame('', $method->invoke(null, null));
        $this->assertSame('1', $method->invoke(null, true));
        $this->assertSame('0', $method->invoke(null, false));
    }
}
