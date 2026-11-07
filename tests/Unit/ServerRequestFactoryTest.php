<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\ServerRequestFactory;

class ServerRequestFactoryTest extends TestCase
{
    public function testFromGlobalsPreservesSuperglobalArraysLikeRequestWrapper()
    {
        $request = ServerRequestFactory::fromGlobals(
            array(
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'localhost',
                'REQUEST_URI' => '/path?ok=1',
                'SERVER_PROTOCOL' => 'HTTP/1.1',
            ),
            array('keep' => '1', 0 => 'drop-query'),
            array('field' => 'x', 0 => 'drop-body'),
            array('sid' => 'abc', 0 => 'drop-cookie')
        );

        $this->assertSame(array('keep' => '1', 0 => 'drop-query'), $request->getQueryParams());
        $this->assertSame(array('field' => 'x', 0 => 'drop-body'), $request->getParsedBody());
        $this->assertSame(array('sid' => 'abc', 0 => 'drop-cookie'), $request->getCookieParams());
        $this->assertSame('GET', $request->getMethod());
    }
}
