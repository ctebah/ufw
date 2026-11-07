<?php
namespace Ufw\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Ufw\Response;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

class ResponseHttpTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testRawFormatUsesProvisionalResponseWhenContentEmpty()
    {
        $response = new Response();
        $response->setFormat('raw');
        $response->setContent('');
        $inner = $response->getInnerResponse();
        $this->assertSame(200, $inner->getStatusCode());
    }

    public function testStringifyCoversNumericAndObjectContent()
    {
        $method = new \ReflectionMethod(Response::class, 'stringifyResponseContent');
        $method->setAccessible(true);
        $response = new Response();
        $this->assertSame('42', $method->invoke($response, 42));
        $obj = new class {
            public function __toString()
            {
                return 'obj';
            }
        };
        $this->assertSame('obj', $method->invoke($response, $obj));
    }

    public function testGetRequestRequiresRegisteredApplicationRequest()
    {
        list($app) = ApplicationFixtures::bareWithController(array());
        $app->setRequest(TestHttpRequest::withAttributes(array('controller' => 'ping', 'action' => 'index')));
        $response = new Response();
        $this->assertSame($app->getRequest(), $response->getRequest());
    }

    public function testGetRequestThrowsWhenApplicationHasNoRequest()
    {
        list($app) = ApplicationFixtures::bareWithController(array());
        $app->setRequest(null);
        $response = new Response();
        $this->expectException(\RuntimeException::class);
        $response->getRequest();
    }

    public function testGetInnerResponseThrowsWhenHandlerDidNotProducePsrResponse()
    {
        $response = new Response();
        $prop = new \ReflectionProperty(Response::class, 'innerResponse');
        $prop->setAccessible(true);
        $prop->setValue($response, new \stdClass());
        $this->expectException(\RuntimeException::class);
        $response->getInnerResponse();
    }
}
