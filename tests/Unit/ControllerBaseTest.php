<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

class ControllerBaseTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testProcessMiddlewaresRequiresRequest(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(null);
        $pipe = new \ReflectionMethod(ControllerBase::class, 'processMiddlewares');
        $pipe->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $pipe->invoke($controller, array(\Ufw\Middleware\Dispatch::class));
    }

    public function testProcessMiddlewaresWithEmptyListReturnsEarly(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $pipe = new \ReflectionMethod(ControllerBase::class, 'processMiddlewares');
        $pipe->setAccessible(true);
        $result = $pipe->invoke($controller, array());
        $this->assertSame($controller, $result);
        $this->assertNull($app->getRequest());
    }

    public function testProcessMiddlewaresPipesStringMiddlewareClass(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $pipe = new \ReflectionMethod(ControllerBase::class, 'processMiddlewares');
        $pipe->setAccessible(true);
        $pipe->invoke($controller, array(\Ufw\Middleware\Layout::class));
        $this->assertSame(200, $app->getResponse()->getStatusCode());
    }

    public function testMagicGetDelegatesToGetter(): void
    {
        list($app, $controller) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $this->assertSame($app, $controller->application);
    }
}
