<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Middleware\Dispatch;
use Ufw\Request;
use Ufw\Response;
use Ufw\ServerRequest;

class DispatchMiddlewareTest extends TestCase
{
    public function testProcessInvokesTheCurrentActionThenDelegates()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $response = new Response();
        $controller = new DispatchProbeController($app, $response);
        $controller->setCurrentActionMethod('probeAction');

        $middleware = new Dispatch($controller);
        $server = new ServerRequest(array(), array(), '/', 'GET', 'php://input');
        $request = new Request(array(), array(), array(), null, $server);

        $out = $middleware->process($request, new class($response) implements \Psr\Http\Server\RequestHandlerInterface {
            /**
             * @var Response
             */
            private $response;

            public function __construct(Response $response)
            {
                $this->response = $response;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return $this->response;
            }
        });

        $this->assertTrue($controller->probeRan);
        $this->assertSame($response, $out);
    }

    public function testProcessRejectsMissingAction()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $controller = new DispatchProbeController($app, new Response());
        $controller->setCurrentActionMethod('missingAction');

        $middleware = new Dispatch($controller);
        $this->expectException(\Throwable::class);
        $middleware->process(
            new Request(array(), array(), array(), null, new ServerRequest(array(), array(), '/', 'GET', 'php://input')),
            new class implements \Psr\Http\Server\RequestHandlerInterface {
                public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    return new Response();
                }
            }
        );
    }
}

class DispatchProbeController extends ControllerBase
{
    /**
     * @var bool
     */
    public $probeRan = false;

    /**
     * @var Response
     */
    private $localResponse;

    public function __construct(Application $application, Response $response)
    {
        $this->localResponse = $response;
        parent::__construct($application);
    }

    public function getResponse()
    {
        return $this->localResponse;
    }

    public function probeAction()
    {
        $this->probeRan = true;
    }
}
