<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Request;
use Ufw\ServerRequestFactory;

/**
 *  */
class RequestWrapperTest extends TestCase
{
    public function testGetPostReturnsFullBodyWithNumericKeys()
    {
        $server = ServerRequestFactory::fromGlobals(
            array('REQUEST_METHOD' => 'POST'),
            array(),
            array('title' => 'a', 0 => 'keep-index'),
            array()
        );
        $request = new Request(array(), array(), array(), null, $server);

        $body = $request->getPost();
        $this->assertSame(array('title' => 'a', 0 => 'keep-index'), $body);
        $this->assertSame('keep-index', $request->getPost(0));
    }

    public function testGetParamReadsQueryString()
    {
        $server = ServerRequestFactory::fromGlobals(
            array('REQUEST_METHOD' => 'GET'),
            array('q' => '1'),
            array(),
            array()
        );
        $request = new Request(array(), array(), array(), null, $server);

        $this->assertSame('1', $request->getParam('q'));
    }
}
