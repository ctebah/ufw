<?php
namespace Ufw\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Middleware\Dispatch;
use Ufw\Response;
use Ufw\ServerRequest;
use Ufw\Tests\Support\TestHttpRequest;

class MiddlewareTest extends TestCase
{
    public function testMiddlewareInvokeHandleAndNegotiator()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $controller = new class($app) extends ControllerBase {
            public function probeAction()
            {
            }
        };
        $controller->setCurrentActionMethod('probeAction');
        $app->setRequest(TestHttpRequest::withAttributes(array(
            'controller' => 'ping',
            'action' => 'probe',
        )));

        $middleware = new Dispatch($controller);
        $request = TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'probe'));
        $response = new Response();

        $out = $middleware($request, function () use ($response) {
            return $response;
        });
        $this->assertSame($response, $out);

        $negotiator = new \ReflectionMethod(\Ufw\Middleware\Base::class, 'getNegotiator');
        $negotiator->setAccessible(true);
        $this->assertNotNull($negotiator->invoke($middleware));
    }
}
