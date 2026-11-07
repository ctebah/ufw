<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Middleware\Base as MiddlewareBase;
use Ufw\Middleware\JsonRequest;
use Ufw\Request;
use Ufw\Response;

class JsonRequestMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        $prop = new \ReflectionProperty(MiddlewareBase::class, 'phpInputStream');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        parent::tearDown();
    }

    public function testSkipsNonJsonContentType()
    {
        $middleware = new JsonRequest(new JsonRequestProbeController(new Response()));
        $psr = new \Ufw\ServerRequest(array(), array(), '/', 'POST', 'php://input', array('HTTP_CONTENT_TYPE' => 'text/plain'));
        $request = new Request(array(), array(), array(), null, $psr);

        $middleware->process($request, new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response();
            }
        });

        $this->assertTrue(true);
    }

    public function testThrowsWhenJsonBodyIsInvalid()
    {
        $this->stubPhpInput('not-json');
        $middleware = new JsonRequest(new JsonRequestProbeController(new Response()));
        $psr = new \Ufw\ServerRequest(array(), array(), '/', 'POST', 'php://input', array('content-type' => array('application/json')));
        $request = new Request(array(), array(), array(), null, $psr);

        $this->expectException(\InvalidArgumentException::class);
        $middleware->process($request, new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response();
            }
        });
    }

    public function testAcceptsValidJsonPayload()
    {
        $this->stubPhpInput('{"ok":true}');
        $middleware = new JsonRequest(new JsonRequestProbeController(new Response()));
        $psr = new \Ufw\ServerRequest(array(), array(), '/', 'POST', 'php://input', array('content-type' => array('application/json')));
        $request = new Request(array(), array(), array(), null, $psr);

        $middleware->process($request, new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response();
            }
        });

        $this->assertTrue(true);
    }

    private function stubPhpInput($payload)
    {
        $prop = new \ReflectionProperty(MiddlewareBase::class, 'phpInputStream');
        $prop->setAccessible(true);
        $prop->setValue(null, new JsonRequestInputStub($payload));
    }
}

class JsonRequestInputStub
{
    /** @var string */
    private $payload;

    public function __construct($payload)
    {
        $this->payload = (string) $payload;
    }

    public function __toString()
    {
        return $this->payload;
    }
}

class JsonRequestProbeController extends \Ufw\Controller\Base
{
    /** @var Response */
    private $localResponse;

    public function __construct(Response $response)
    {
        $this->localResponse = $response;
        parent::__construct((new \ReflectionClass(\Ufw\Application::class))->newInstanceWithoutConstructor());
    }

    public function getResponse()
    {
        return $this->localResponse;
    }
}
