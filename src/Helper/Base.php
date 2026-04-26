<?php

namespace Ufw\Helper;

/**
 * Superclass for helpers: binds to the active {@see \Ufw\Controller\Base}, proxies unknown methods via
 * {@see __call}, and exposes request/response/application. Concrete helpers add behaviour (rendering, URLs, …).
 */
class Base
{

    /**
     *
     * @var \Ufw\Controller\Base
     */
    protected $controller = null;

    public function __construct()
    {
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array(
            $this->getController(),
            $method
        ), $args);
    }

    public function __get($name)
    {
        return $this->getController()->$name;
    }

    public function getRequest()
    {
        return $this->getController()->getRequest();
    }

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
     *
     * @return \Ufw\Controller\Base
     */
    public function getController()
    {
        return $this->controller ? $this->controller : \Ufw\Registry::getInstance()->get('application')->getCurrentController();
    }

    /**
     *
     * @param \Ufw\Controller\Base $controller            
     */
    public function setController($controller)
    {
        $this->controller = $controller;
        return $this;
    }
}
