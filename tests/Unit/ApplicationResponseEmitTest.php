<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Exception\BreakExecution;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

class ApplicationResponseEmitTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('UFW_APPLICATION_SUPPRESS_EXIT')) {
            define('UFW_APPLICATION_SUPPRESS_EXIT', true);
        }
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testShouldEmitAndTerminateForRedirectRangeAnd401WithWwwAuthenticate(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $method = new \ReflectionMethod(Application::class, 'shouldEmitAndTerminate');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($app, 302));
        $this->assertTrue($method->invoke($app, 305));
        $this->assertFalse($method->invoke($app, 404));
        $response = $app->getResponse();
        $response->reset(401);
        $response->withHeader('WWW-Authenticate', 'Basic realm="test"');
        $this->assertTrue($response->hasHeader('WWW-Authenticate'));
        $this->assertTrue($method->invoke($app, 401));
        $response->reset(401);
        $this->assertFalse($method->invoke($app, 401));
    }

    public function testRunEmitPathOnRedirectBreakExecutionWithoutDie(): void
    {
        $app = new class() extends Application {
            public function dispatch($request = null)
            {
                $this->getResponse()->reset(302)->withHeader('Location', '/target');
                throw new BreakExecution('', 302);
            }

            protected function dispatchError($action = 'not-found', $controller = 'error')
            {
                return $this;
            }
        };
        $app->setConfig(array('logging' => array('exception_log_path' => false)));
        \Ufw\Registry::getInstance()->set('application', $app);
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        ob_start();
        $app->run();
        ob_end_clean();
        $this->assertSame(302, $app->getResponse()->getStatusCode());
    }

    public function testRenderHelperOverwriteTrueReplacesJsonPayload(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->getResponse()->setFormat('json')->setContent(array('old' => 1));
        $render = new \Ufw\Helper\Render();
        $render->setController($controller);
        $render->setOverwrite(true);
        $render->render(array('new' => 2));
        $this->assertSame(array('new' => 2), $app->getResponse()->getContent());
    }
}
