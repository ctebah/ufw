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
        $controller = $this->getController();
        $method = $controller->getCurrentActionMethod();
        if (!is_string($method) || $method === '') {
            throw new \BadMethodCallException('Controller action is not callable: ' . (string) $method);
        }
        try {
            $action = new \ReflectionMethod($controller, $method);
        } catch (\ReflectionException $e) {
            throw new \BadMethodCallException('Controller action is not callable: ' . $method, 0, $e);
        }
        if (!$action->isPublic()) {
            throw new \BadMethodCallException('Controller action is not callable: ' . $method);
        }
        $action->invoke($controller);

        return $handler->handle($request);
    }
}