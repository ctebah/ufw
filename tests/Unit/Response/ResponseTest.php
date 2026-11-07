<?php
namespace Ufw\Tests\Unit\Response;

use PHPUnit\Framework\TestCase;
use Ufw\Response;

class ResponseTest extends TestCase
{
    public function testDefaultHandlersAndInnerResponseStringify()
    {
        $response = new Response();
        $response->setContent('hello');
        $response->setFormat('html');

        $htmlHandler = new \ReflectionMethod(Response::class, 'defaultHtmlHandler');
        $htmlHandler->setAccessible(true);
        $this->assertIsCallable($htmlHandler->getClosure());
        $psr = $htmlHandler->invoke(null, 'hello', $response);
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $psr);

        $rawHandler = new \ReflectionMethod(Response::class, 'defaultRawHandler');
        $rawHandler->setAccessible(true);
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $rawHandler->invoke(null, 'x', $response));

        $inner = new \ReflectionMethod(Response::class, 'getInnerResponse');
        $inner->setAccessible(true);
        $this->assertNotNull($inner->invoke($response));

        $stringify = new \ReflectionMethod(Response::class, 'stringifyResponseContent');
        $stringify->setAccessible(true);
        $this->assertSame('hello', $stringify->invoke($response, 'hello'));
    }
}
