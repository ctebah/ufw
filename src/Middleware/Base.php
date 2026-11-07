<?php

namespace Ufw\Middleware;

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
     * @var \Laminas\Diactoros\PhpInputStream|null
     */
    protected static $phpInputStream = null;

    /**
     * @var \Negotiation\Negotiator|null
     */
    protected static $negotiator = null;

    /**
     * @var \Ufw\Controller\Base
     */
    protected $controller;

    public function __construct(\Ufw\Controller\Base $controller)
    {
        $this->setController($controller);
    }

    /**
     * @param callable(ServerRequestInterface): ResponseInterface $next
     */
    public function __invoke(\Ufw\Request $request, callable $next): ResponseInterface
    {
        $handler = new class($next) implements RequestHandlerInterface {
            /**
             * @var callable(ServerRequestInterface): ResponseInterface
             */
            private $next;

            /**
             * @param callable(ServerRequestInterface): ResponseInterface $next
             */
            public function __construct(callable $next)
            {
                $this->next = $next;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->next)($request);
            }
        };

        return $this->process($request, $handler);
    }

    abstract public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;

    /**
     * @return \Ufw\Response
     */
    protected function getResponse()
    {
        return $this->getController()->getResponse();
    }

    /**
     * @return string
     */
    protected function getPhpInput()
    {
        if (self::$phpInputStream === null) {
            self::$phpInputStream = new \Laminas\Diactoros\PhpInputStream();
        }
        return self::$phpInputStream->__toString();
    }

    /**
     * @return \Negotiation\Negotiator
     */
    protected function getNegotiator()
    {
        if (self::$negotiator === null) {
            self::$negotiator = new \Negotiation\Negotiator();
        }
        return self::$negotiator;
    }

    /**
     * @return \Ufw\Application
     */
    protected function getApplication()
    {
        return $this->getController()->getApplication();
    }

    /**
     * @return \Ufw\Controller\Base
     */
    protected function getController()
    {
        return $this->controller;
    }

    /**
     * @param string $name
     * @return \Ufw\Helper\Base|null
     */
    protected function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    /**
     * @return $this
     */
    protected function setController(\Ufw\Controller\Base $controller)
    {
        $this->controller = $controller;
        return $this;
    }
}
