<?php
namespace Ufw\Tests\Unit\Application;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Controller\RestApi;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Response;
use Ufw\Route\Literal;
use Ufw\Router;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class ApplicationLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        Paths::setApplicationPath(TestAppPaths::app());
        $_SERVER = array();
        parent::tearDown();
    }

    public function testConstructorLoadsDispatchFromInlineConfig()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
            'routes' => array(),
        ));
        Registry::getInstance()->set('application', $app);

        $construct = new \ReflectionMethod(Application::class, '__construct');
        $construct->setAccessible(true);
        $fresh = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $fresh);
        $fresh->setConfig($app->getConfig());
        $construct->invoke($fresh);

        $this->assertSame(array('ping'), $fresh->getDispatchControllers());
        $this->assertSame('ping', $fresh->getDispatchDefaults()['controller']);
    }

    public function testGetScriptNameAndRequestContextFormatters()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);

        $controller = new class($app) extends ControllerBase {
            public function getResponse()
            {
                return new Response();
            }
        };
        $app->setCurrentController($controller);

        $request = new Request(
            array('module' => 'app', 'controller' => 'ping', 'action' => 'index'),
            array(),
            array(),
            null,
            new ServerRequest(array('REQUEST_URI' => '/ping'), array(), '/ping', 'GET', 'php://input')
        );
        $app->setRequest($request);

        $this->assertSame('pingindex', $app->getScriptName('PingIndex'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ping';
        $build = new \ReflectionMethod(Application::class, 'buildRequestContextLinesFromGlobals');
        $build->setAccessible(true);
        $lines = $build->invoke($app);
        $this->assertNotEmpty($lines);

        $format = new \ReflectionMethod(Application::class, 'formatRequestContextForLog');
        $format->setAccessible(true);
        $text = $format->invoke($app, 404);
        $this->assertStringContainsString('404', $text);
    }

    public function testDispatchWithExplicitRequestRunsPingController()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'controller_aliases' => array(),
                'module' => array(),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        $app->setDispatchDefaults(array('controller' => 'ping', 'action' => 'index'));
        Registry::getInstance()->set('application', $app);

        $request = new Request(
            array('controller' => 'ping', 'action' => 'other'),
            array(),
            array(),
            null,
            new ServerRequest(
                array('REQUEST_METHOD' => 'GET'),
                array(),
                '/ping',
                'GET',
                'php://input',
                array(),
                array(),
                array(),
                null,
                '1.1',
                array('controller' => 'ping', 'action' => 'other')
            )
        );
        $app->dispatch($request);
        $this->assertInstanceOf(ControllerBase::class, $app->getCurrentController());
    }

    public function testGetDispatchDefaultsMergeModuleConfig()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'module' => array(
                    'admin' => array('defaults' => array('controller' => 'dash', 'action' => 'home')),
                ),
            ),
        ));
        $app->setDispatchDefaults(array('controller' => 'ping', 'action' => 'index'));

        $defaults = new \ReflectionMethod(Application::class, 'getDispatchDefaults');
        $defaults->setAccessible(true);
        $global = $defaults->invoke($app, null);
        $this->assertSame('ping', $global['controller']);

        $module = $defaults->invoke($app, 'admin');
        $this->assertSame('dash', $module['controller']);
    }

    public function testGetDispatchConfigAndNormalizeHelpers()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping'),
                'module' => array(
                    'admin' => array('controllers' => array('dash')),
                ),
            ),
        ));

        $getDispatchConfig = new \ReflectionMethod(Application::class, 'getDispatchConfig');
        $getDispatchConfig->setAccessible(true);
        $this->assertSame(array('ping'), $getDispatchConfig->invoke($app)['controllers']);

        $getModule = new \ReflectionMethod(Application::class, 'getModuleDispatchConfig');
        $getModule->setAccessible(true);
        $dispatch = $getDispatchConfig->invoke($app);
        $this->assertSame(array('dash'), $getModule->invoke($app, $dispatch, 'admin')['controllers']);

        $normalizeList = new \ReflectionMethod(Application::class, 'normalizeStringList');
        $normalizeList->setAccessible(true);
        $this->assertSame(array('a', 'b'), $normalizeList->invoke($app, array('a', 'b', 7)));

        $normalizeKeyed = new \ReflectionMethod(Application::class, 'normalizeStringKeyedArray');
        $normalizeKeyed->setAccessible(true);
        $this->assertSame(array('k' => 'v'), $normalizeKeyed->invoke($app, array('k' => 'v', 0 => 'x')));

        $scalar = new \ReflectionMethod(Application::class, 'scalarToString');
        $scalar->setAccessible(true);
        $this->assertSame('1', $scalar->invoke($app, 1));
    }

    public function testHasActionResolvesRegisteredController()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);

        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', 'index', 'GET', null);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('controller', $result);
    }

    public function testDispatchErrorStartsErrorDispatchAndThrowsWhenRouteMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'routes' => array(),
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);
        $router = new Router($app, new Request(array(), array(), array(), null, new ServerRequest()), array(
            'err' => array(
                'type' => Literal::class,
                'path' => '/err',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setRouter($router);

        $dispatchError = new \ReflectionMethod(Application::class, 'dispatchError');
        $dispatchError->setAccessible(true);
        $this->expectException(\Ufw\Exception\BreakExecution::class);
        $dispatchError->invoke($app, 'not-found', 'error');
    }

    public function testDispatchErrorPrefersJsonResponseFormat()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'routes' => array(),
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        Registry::getInstance()->set('application', $app);

        $psr = new ServerRequest(
            array('REQUEST_METHOD' => 'GET'),
            array(),
            '/missing',
            'GET',
            'php://input',
            array('Accept' => 'application/json')
        );
        $app->setRequest(new Request(
            array('module' => 'api', 'controller' => 'x', 'action' => 'y'),
            array(),
            array(),
            null,
            $psr
        ));

        $router = new Router($app, $app->getRequest(), array());
        $app->setRouter($router);

        $dispatchError = new \ReflectionMethod(Application::class, 'dispatchError');
        $dispatchError->setAccessible(true);
        try {
            $dispatchError->invoke($app, 'not-found', 'error');
            $this->fail('Expected BreakExecution');
        } catch (\Ufw\Exception\BreakExecution $e) {
            $this->assertSame('json', $app->getResponse()->getFormat());
        }
    }

    public function testDecodeRequestThrowsBreakExecutionWhenUnmatched()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('routes' => array())));
        Registry::getInstance()->set('application', $app);
        $request = new Request(array(), array(), array(), null, new ServerRequest(array(), array(), '/nope', 'GET', 'php://input'));
        $router = new Router($app, $request, array());
        $app->setRouter($router);

        $decode = new \ReflectionMethod(Application::class, 'decodeRequest');
        $decode->setAccessible(true);
        $this->expectException(\Ufw\Exception\BreakExecution::class);
        $decode->invoke($app);
    }
}
