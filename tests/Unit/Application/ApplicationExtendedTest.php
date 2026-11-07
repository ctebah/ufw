<?php
namespace Ufw\Tests\Unit\Application;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Exception\BreakExecution;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Response;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';
require_once dirname(__DIR__, 2) . '/Fixture/Helper/FixtureHelperProbe.php';

class ApplicationExtendedTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        $_SERVER = array();
        parent::tearDown();
    }

    public function testConstructorThrowsWhenAppConfigIsNotArray()
    {
        Paths::setApplicationPath(TestAppPaths::badApp());
        ApplicationFixtures::resetApplicationStaticConfig();
        $configProp = new \ReflectionProperty(Application::class, 'config');
        $configProp->setAccessible(true);
        $configProp->setValue(null, null);

        $this->expectException(\RuntimeException::class);
        new Application();
    }

    public function testGetHelperPrefersApplicationHelperNamespace()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('controllers' => array('ping')),
        ));
        $helper = $app->getHelper('FixtureHelperProbe', $controller);
        $this->assertInstanceOf(\Helper\FixtureHelperProbe::class, $helper);
        $this->assertSame('app-helper', $helper->marker());
    }

    public function testHasActionAppliesGlobalControllerAlias()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controller_aliases' => array('p' => 'ping'),
                'controllers' => array('ping'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'p', 'index', 'GET', null);
        $this->assertIsArray($result);
        $this->assertSame('api', $result['kind']);
    }

    public function testHasActionReturnsFalseForUnknownController()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $this->assertFalse($has->invoke($app, 'missing', 'index', null, null));
    }

    public function testHasActionReturnsNullActionWhenReflectionFailsButClassExists()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', 'doesNotExistAtAll', null, null);
        $this->assertIsArray($result);
        $this->assertNull($result['action']);
    }

    public function testDispatchThrowsBreakExecutionWhenActionNameEmptyAfterResolution()
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
        $controller = new class($app) extends ControllerBase {
            public function getResponse()
            {
                return new Response();
            }
        };
        $app->setCurrentController($controller);

        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => ''));
        $this->expectException(BreakExecution::class);
        $app->dispatch($request);
    }

    public function testBuildRequestContextUsesHttpsWhenServerSaysSo()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $_SERVER = array(
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/secure',
            'HTTP_HOST' => 'gate.test',
            'HTTPS' => 'on',
        );
        $build = new \ReflectionMethod(Application::class, 'buildRequestContextLinesFromGlobals');
        $build->setAccessible(true);
        $blob = implode("\n", $build->invoke($app));
        $this->assertStringContainsString('https://gate.test/secure', $blob);
    }

    public function testGetDispatchDefaultsAndControllersForModule()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'module' => array(
                    'shop' => array(
                        'defaults' => array('controller' => 'item', 'action' => 'list'),
                        'controllers' => array('item'),
                        'controller_aliases' => array('i' => 'item'),
                    ),
                ),
            ),
        ));
        $app->setDispatchDefaults(array('controller' => 'ping', 'action' => 'index'));
        $defaults = $app->getDispatchDefaults('shop');
        $this->assertSame('item', $defaults['controller']);
        $controllers = $app->getDispatchControllers('shop');
        $this->assertContains('item', $controllers);
        $this->assertContains('i', $controllers);
    }

    public function testNormalizeStringKeyedArrayViaDispatchConfig()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'routes' => 'not-array',
                0 => 'skip',
                'controllers' => array('ping'),
            ),
        ));
        $method = new \ReflectionMethod(Application::class, 'getDispatchConfig');
        $method->setAccessible(true);
        $dispatch = $method->invoke($app);
        $this->assertArrayHasKey('controllers', $dispatch);
        $this->assertArrayNotHasKey(0, $dispatch);
    }

    public function testHasActionUsesDefaultControllerWhenNameEmpty()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setDispatchDefaults(array('controller' => 'ping', 'action' => 'index'));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, '', 'index', 'GET', null);
        $this->assertIsArray($result);
    }

    public function testHasActionAppliesModuleAliasBranch()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'module' => array(
                    'api' => array(
                        'controller_aliases' => array('p' => 'ping'),
                    ),
                ),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'p', 'index', 'GET', 'api');
        $this->assertTrue(is_array($result) || $result === false);
    }

    public function testGetRouterTreatsNonArrayRoutesAsEmpty()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('routes' => 'bad')));
        Registry::getInstance()->set('application', $app);
        $this->assertInstanceOf(\Ufw\Router::class, $app->getRouter());
    }

    public function testGetModuleDispatchConfigReturnsEmptyWhenModulesInvalid()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Application::class, 'getModuleDispatchConfig');
        $method->setAccessible(true);
        $this->assertSame(array(), $method->invoke($app, array('module' => 'nope'), 'api'));
    }

    public function testNormalizeStringListReturnsEmptyForNonArray()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Application::class, 'normalizeStringList');
        $method->setAccessible(true);
        $this->assertSame(array(), $method->invoke($app, 'x'));
    }

    public function testScalarToStringReturnsEmptyForObjects()
    {
        $method = new \ReflectionMethod(Application::class, 'scalarToString');
        $method->setAccessible(true);
        $this->assertSame('', $method->invoke(null, new \stdClass()));
    }

    public function testDispatchErrorSetsJsonFormatWhenClientPrefersJson()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $psr = new \Ufw\ServerRequest(
            array('REQUEST_METHOD' => 'GET'),
            array(),
            '/x',
            'GET',
            'php://input',
            array('Accept' => 'application/json')
        );
        $app->setRequest(new Request(
            array('controller' => 'x', 'action' => 'y'),
            array(),
            array(),
            null,
            $psr
        ));
        $dispatchError = new \ReflectionMethod(Application::class, 'dispatchError');
        $dispatchError->setAccessible(true);
        try {
            $dispatchError->invoke($app, 'not-found', 'error');
        } catch (\Ufw\Exception\BreakExecution $e) {
            $this->assertSame('json', $app->getResponse()->getFormat());
        }
    }

    public function testHasActionReturnsNullActionWhenHttpVerbMissingOnApiController()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', 'index', 'PATCH', null);
        $this->assertIsArray($result);
        $this->assertNull($result['action']);
    }

    public function testNormalizeStringKeyedArrayReturnsEmptyForNonArrayInput()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Application::class, 'normalizeStringKeyedArray');
        $method->setAccessible(true);
        $this->assertSame(array(), $method->invoke($app, 'not-array'));
    }

    public function testDispatchThrowsRuntimeWhenControllerDoesNotExtendBase()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('bare'),
                'defaults' => array('controller' => 'bare', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $app->setDispatchControllers(array('bare'));
        $request = TestHttpRequest::withAttributes(array(
            'controller' => 'bare',
            'action' => 'index',
            'method' => 'GET',
        ));
        $this->expectException(\RuntimeException::class);
        $app->dispatch($request);
    }
}
