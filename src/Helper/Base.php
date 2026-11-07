<?php

namespace Ufw\Helper;

/**
 * Superclass for helpers: binds to the active {@see \Ufw\Controller\Base}, proxies unknown methods via
 * {@see __call}, and exposes request/response/application. Concrete helpers add behaviour (rendering, URLs, …).
 *
 * @property-read \Ufw\Helper\User $user
 * @method string partial(string $script, array<string, mixed> $vars = array())
 * @method \Ufw\Placeholder placeholder(string $name)
 * @method string translate(string $str)
 * @method string url(array<int|string, mixed> $params = array(), bool $reset = true, bool $withQS = false, ?string $routeName = 'default', bool $encode = true)
 * @method \Ufw\Helper\FlashMessenger flashMessenger()
 * @method void redirect(string $url)
 * @method string html5Attribs(array<string, mixed> $attribs)
 */
class Base
{

    /**
     * @var \Ufw\Controller\Base|null
     */
    protected $controller = null;

    public function __construct()
    {
    }

    /**
     * @param string $method
     * @param array<int, mixed> $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $controller = $this->getController();
        /** @var callable $callable */
        $callable = array($controller, $method);
        return call_user_func_array($callable, $args);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->getController()->$name;
    }

    /**
     * @return \Ufw\Request
     */
    public function getRequest()
    {
        $request = $this->getController()->getRequest();
        if ($request === null) {
            throw new \RuntimeException('Request is not available.');
        }
        return $request;
    }

    /**
     * @return \Ufw\Response
     */
    public function getResponse()
    {
        return $this->getController()->getResponse();
    }

    /**
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return $this->getController()->getApplication();
    }

    /**
     * @return \Ufw\Controller\Base
     */
    public function getController()
    {
        if ($this->controller !== null) {
            return $this->controller;
        }
        $application = \Ufw\Registry::getInstance()->get('application');
        if (!$application instanceof \Ufw\Application) {
            throw new \RuntimeException('Application is not registered.');
        }
        $controller = $application->getCurrentController();
        if ($controller === null) {
            throw new \RuntimeException('No active controller.');
        }
        return $controller;
    }

    /**
     * @param \Ufw\Controller\Base $controller
     * @return $this
     */
    public function setController($controller)
    {
        $this->controller = $controller;
        return $this;
    }
}
