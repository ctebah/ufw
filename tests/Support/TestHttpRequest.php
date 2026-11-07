<?php
namespace Ufw\Tests\Support;

use Ufw\Request;
use Ufw\ServerRequest;

/**
 * Build {@see Request} with route attributes on the PSR server request (constructor $values alone are ignored when $serverRequest is passed).
 */
final class TestHttpRequest
{
    /**
     * @param array<string, mixed> $attributes controller, action, module, …
     */
    public static function withAttributes(array $attributes, $method = 'GET', $uri = '/')
    {
        $server = new ServerRequest(
            array(
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
            ),
            array(),
            $uri,
            $method,
            'php://input',
            array(),
            array(),
            array(),
            null,
            '1.1',
            $attributes
        );

        return new Request($attributes, array(), array(), null, $server);
    }
}
