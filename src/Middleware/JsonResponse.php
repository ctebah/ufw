<?php
namespace Ufw\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * If the client’s `Accept` header prefers `application/json` over `text/html`, sets the UFW response format to JSON
 * ({@see \Ufw\Response::setFormat()}) so rendering/serialization can follow.
 */
class JsonResponse extends Base
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        if (\Ufw\Request::acceptPrefersApplicationJson($request)) {
            $this->getResponse()->setFormat('json');
        }
        return $handler->handle($request);
    }

}