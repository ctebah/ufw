<?php
namespace Ufw\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\ServerRequestFactory;

class RequestTest extends TestCase
{
    public function testPsr7ProxyMethodsAndJsonPreference()
    {
        $psr = new ServerRequest(
            array('REQUEST_METHOD' => 'POST'),
            array(),
            '/api/item',
            'POST',
            'php://input',
            array('Content-Type' => 'application/json', 'Accept' => 'application/json'),
            array(),
            array('q' => '1'),
            null,
            '1.1',
            array('controller' => 'api', 'action' => 'save')
        );
        $request = new Request(array(), array(), array(), null, $psr);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('1.1', $request->getProtocolVersion());
        $this->assertSame('/api/item', $request->getRequestTarget());
        $this->assertTrue($request->hasHeader('Content-Type'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertIsArray($request->getHeaders());
        $this->assertSame(array('q' => '1'), $request->getQueryParams());
        $this->assertSame(array(), $request->getCookieParams());
        $this->assertIsString((string) $request->getBody());
        $this->assertIsArray($request->getUploadedFiles());
        $this->assertTrue($request->prefersApplicationJson());

        $withAttr = $request->withAttribute('id', 9);
        $this->assertSame(9, $withAttr->getAttribute('id'));
        $without = $withAttr->withoutAttribute('id');
        $this->assertNull($without->getAttribute('id'));

        $withMethod = $request->withMethod('PATCH');
        $this->assertSame('PATCH', $withMethod->getMethod());

        $withHeader = $request->withHeader('X-Test', 'yes');
        $this->assertSame('yes', $withHeader->getHeaderLine('X-Test'));
        $added = $withHeader->withAddedHeader('X-Test', 'also');
        $this->assertStringContainsString('yes', $added->getHeaderLine('X-Test'));
        $noHeader = $withHeader->withoutHeader('X-Test');
        $this->assertFalse($noHeader->hasHeader('X-Test'));

        $request->offsetSet('token', 'abc');
        $this->assertSame('abc', $request->offsetGet('token'));
        $request->offsetUnset('token');
        $this->assertFalse($request->offsetExists('token'));

        $this->assertSame('save', $request->__call('getAction', array()));
        $this->assertSame('api', $request->getController());
        $this->assertSame('api', $request->controller);
    }

    public function testHttpVerbHelpersAndJsonPreference()
    {
        $psr = new ServerRequest(
            array('REQUEST_METHOD' => 'PATCH'),
            array(),
            '/api',
            'PATCH',
            'php://input',
            array('Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest')
        );
        $request = new Request(array(), array(), array(), null, $psr);
        $this->assertTrue($request->isPatch());
        $this->assertFalse($request->isOptions());
        $this->assertTrue($request->isXmlHttpRequest());
        $this->assertTrue($request->prefersApplicationJson());
    }

    public function testFactoryBuiltRequestSupportsWithUriAndBody()
    {
        $server = ServerRequestFactory::fromGlobals(
            array('REQUEST_METHOD' => 'GET'),
            array(),
            array(),
            array()
        );
        $request = new Request(array(), array(), array(), null, $server);
        $uri = $request->getUri()->withPath('/other');
        $withUri = $request->withUri($uri);
        $this->assertStringContainsString('/other', (string) $withUri->getUri());

        $stream = new \Laminas\Diactoros\Stream('php://temp', 'r+');
        $stream->write('payload');
        $stream->rewind();
        $withBody = $request->withBody($stream);
        $this->assertSame('payload', (string) $withBody->getBody()->getContents());

        $withCookies = $request->withCookieParams(array('sid' => '1'));
        $this->assertSame(array('sid' => '1'), $withCookies->getCookieParams());

        $withQuery = $request->withQueryParams(array('page' => '2'));
        $this->assertSame(array('page' => '2'), $withQuery->getQueryParams());

        $withProtocol = $request->withProtocolVersion('2');
        $this->assertSame('2', $withProtocol->getProtocolVersion());

        $withTarget = $request->withRequestTarget('/target?x=1');
        $this->assertSame('/target?x=1', $withTarget->getRequestTarget());
    }
}
