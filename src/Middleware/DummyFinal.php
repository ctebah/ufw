<?php
namespace Ufw\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Stratigility pipeline terminator: returns the controller’s {@see \Ufw\Response} without invoking the
 * inner handler (the “final” middleware after dispatch).
 */
class DummyFinal extends Base
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        return $this->getResponse();
    }
}