<?php
namespace Ufw\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Exception\BreakExecution;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Route\Literal;
use Ufw\Router;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

class ApplicationRunDispatchTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testHasActionReturnsNullActionWhenMethodAndHttpVerbMissing()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $app->setDispatchControllers(array('ping'));
        Registry::getInstance()->set('application', $app);

        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', '', null, null);
        $this->assertIsArray($result);
        $this->assertNull($result['action']);
    }

    public function testHasActionReturnsApiKindWithoutHttpMethodForMissingRestMethod()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('controllers' => array('ping'))));
        Registry::getInstance()->set('application', $app);
        $has = new \ReflectionMethod(Application::class, 'hasAction');
        $has->setAccessible(true);
        $result = $has->invoke($app, 'ping', 'missingRest', null, null);
        $this->assertIsArray($result);
        $this->assertNull($result['action']);
    }

    public function testRunHandlesBreakExecutionAndResetsResponseStatus()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'controllers' => array('ping'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
            'logging' => array('exception_log_path' => sys_get_temp_dir() . '/ufw-run-gate.log'),
        ));
        $app->setDispatchControllers(array('ping'));
        $request = TestHttpRequest::withAttributes(array('controller' => 'missing', 'action' => 'nope'));
        $router = new Router($app, $request, array());
        $app->setRouter($router);

        $run = new \ReflectionMethod(Application::class, 'run');
        $run->setAccessible(true);
        try {
            $run->invoke($app, $request);
            $this->fail('Expected BreakExecution from dispatchError chain');
        } catch (BreakExecution $e) {
            $this->assertSame(404, $app->getResponse()->getStatusCode());
        }
    }

    public function testGetHelperLoadsUfwHelperWhenAppHelperMissing()
    {
        list($app, $controller) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('controllers' => array('ping')),
        ));
        $flash = $app->getHelper('FlashMessenger', $controller);
        if (class_exists('\\Ufw\\Helper\\FlashMessenger')) {
            $this->assertInstanceOf(\Ufw\Helper\FlashMessenger::class, $flash);
        } else {
            $this->assertNull($flash);
        }
        $this->assertInstanceOf(\Ufw\Helper\Url::class, $app->getHelper('url', $controller));
    }
}
