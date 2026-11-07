<?php
namespace Ufw\Tests\Unit\Application;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\Route\Literal;
use Ufw\Router;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class ApplicationDispatchHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testGetHelperInstantiatesFrameworkHelperAndBindsController()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('controllers' => array('ping')),
        ));
        $url = $app->getHelper('url', $controller);
        $this->assertInstanceOf(\Ufw\Helper\Url::class, $url);
        $this->assertSame($controller, $url->getController());

        $cached = $app->getHelper('url');
        $this->assertSame($url, $cached);
    }

    public function testGetDispatchControllersMergesModuleAliases()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'module' => array(
                    'admin' => array(
                        'controllers' => array('dash'),
                        'controller_aliases' => array('d' => 'dash'),
                    ),
                ),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        $list = $app->getDispatchControllers('admin');
        $this->assertContains('dash', $list);
        $this->assertContains('d', $list);
    }

    public function testHasActionResolvesAliasAndRejectsProtectedAction()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'controller_aliases' => array('p' => 'ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        Registry::getInstance()->set('application', $app);

        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $viaAlias = $has->invoke($app, 'p', 'other', 'GET', null);
        $this->assertIsArray($viaAlias);
        $this->assertSame('classic', $viaAlias['kind']);

        $secret = $has->invoke($app, 'ping', 'secret', 'GET', null);
        $this->assertFalse($secret);
    }

    public function testDispatchViaMatchedRouterUsesRouteController()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        $request = TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'other',
        ), 'GET', '/ping');
        $router = new Router($app, $request, array(
            'home' => array(
                'type' => Literal::class,
                'path' => '/ping',
                'defaults' => array('controller' => 'ping', 'action' => 'other'),
                'constraints' => array('controller' => '~^ping$~'),
            ),
        ));
        $router->match();
        $app->setRouter($router);

        $app->dispatch(null);
        $this->assertInstanceOf(\Controller\Ping::class, $app->getCurrentController());
    }

    public function testDispatchWithExplicitRequestInvokesPingOtherAction()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        $app->setDispatchDefaults(array('controller' => 'ping', 'action' => 'index'));

        $request = TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'other',
        ));
        $request = new Request(
            array('controller' => 'ping', 'action' => 'other'),
            array(),
            array(),
            null,
            $request->getServerRequest()
        );

        $app->dispatch($request);
        $this->assertInstanceOf(\Controller\Ping::class, $app->getCurrentController());
    }

    public function testLogExceptionToFileWritesWhenPathConfigured()
    {
        Paths::setApplicationPath(TestAppPaths::app());
        $logFile = Paths::application() . '/log/gate-exceptions.tmp';
        if (is_file($logFile)) {
            unlink($logFile);
        }

        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'logging' => array('exception_log_path' => $logFile),
            'dispatch' => array('controllers' => array()),
        ));
        Registry::getInstance()->set('application', $app);
        $app->setRequest(new Request(
            array('controller' => 'ping', 'action' => 'index'),
            array('q' => '1'),
            array(),
            null,
            new ServerRequest(
                array('REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/ping'),
                array(),
                '/ping?q=1',
                'GET',
                'php://input',
                array(),
                array(),
                array('q' => '1'),
                null,
                '1.1',
                array('controller' => 'ping', 'action' => 'index')
            )
        ));

        $log = new \ReflectionMethod(Application::class, 'logExceptionToFile');
        $log->setAccessible(true);
        $log->invoke($app, new \RuntimeException('gate-fail'), 404);

        $this->assertFileExists($logFile);
        $body = file_get_contents($logFile);
        $this->assertStringContainsString('gate-fail', $body);
        $this->assertStringContainsString('Dispatch:', $body);
        unlink($logFile);
    }
}
