<?php
namespace Ufw\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Invokes the controller action for the current route ({@see \Ufw\Controller\Base::getCurrentActionMethod()}), then
 * delegates to the next handler so later middleware (e.g. layout) can run.
 */
class Dispatch extends Base
{

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        try {
            call_user_func([$this->getController(), $this->getController()->getCurrentActionMethod()]);
        } catch (\Exception $e) {
            throw $e;
        }
        return $handler->handle($request);
    }
}