<?php

namespace Ufw\Middleware;

use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware bound to the active {@see \Ufw\Controller\Base}: same request/response/application as the
 * controller. {@see __invoke()} adapts Stratigility’s `(request, next)` shape to {@see process()}.
 * Subclasses use helpers for the UFW response, raw POST body, and a shared content negotiator (static caches below).
 */
abstract class Base implements MiddlewareInterface
{

    /**
     *
     * @var \Laminas\Diactoros\PhpInputStream
     */
    protected static $phpInputStream = null;

    /**
     *
     * @var \Negotiation\Negotiator
     */
    protected static $negotiator = null;

    /**
     *
     * @var \Ufw\Controller\Base
     */
    protected $controller;

    public function __construct(\Ufw\Controller\Base $controller)
    {
        $this->setController($controller);
    }

    public function __invoke(\Ufw\Request $request, callable $next)
    {
        // TODO(legacy): Old double-pass `(request, callable $next)` entry point. `process()` expects
        // `RequestHandlerInterface` as 2nd arg; `CallableMiddlewareDecorator` is `MiddlewareInterface` - the pipeline
        // in {@see \Ufw\Controller\Base::processMiddlewares()} uses {@see process()} only, not this. Revisit if unused.
        return $this->process($request, new CallableMiddlewareDecorator($next));
    }

    abstract public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;

    /**
     *
     * @return \Ufw\Response
     */
    protected function getResponse()
    {
        return $this->getController()->getResponse();
    }

    protected function getPhpInput()
    {
        if (!isset(self::$phpInputStream)) {
            self::$phpInputStream = new \Laminas\Diactoros\PhpInputStream();
        }
        return self::$phpInputStream->__toString();
    }

    protected function getNegotiator()
    {
        if (!isset(self::$negotiator)) {
            self::$negotiator = new \Negotiation\Negotiator();
        }
        return self::$negotiator;
    }

    /**
     *
     * @return \Ufw\Application
     */
    protected function getApplication()
    {
        return $this->getController()->getApplication();
    }

    /**
     *
     * @return \Ufw\Controller\Base
     */
    protected function getController()
    {
        return $this->controller;
    }

    protected function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    /**
     *
     * @param
     *            $controller
     */
    protected function setController(\Ufw\Controller\Base $controller)
    {
        $this->controller = $controller;
        return $this;
    }
}
