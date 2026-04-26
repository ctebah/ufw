<?php
namespace Ufw\Controller;

use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;

/**
 * Base HTTP controller: owns layout/middleware around the action, resolves helpers by name via {@see __call},
 * and exposes the current {@see \Ufw\Application}, request, and response for the dispatch lifecycle.
 *
 * @method string partial(string $script, array $vars = array()) render some partial script
 * @method void redirect(string $url) redirect to url
 * @method string render(array $vars = array(), string $script = null, boolean $controllerDir = true) render view script, pass $vars, if script is omitted use script name after action name in controller name directory (3rd param true)
 * @method \Ufw\Helper\User user() current logged on user helper
 * @method string url(array $params = [], bool $reset = true, bool $withQS = false, string $routeName = 'default', bool $encode = true) Same as {@see \Ufw\Helper\Url::__invoke} - returns a URL string; the {@see \Ufw\Helper\Url} instance is not exposed through {@see __call()}
 * @method \Ufw\Helper\FlashMessenger flashMessenger() Flash Messenger - implement session-based messages
 * @method string translate(string $str) pass $str through Registry translator (see \Ufw\Helper\Translate)
 *
 * @property \Ufw\Helper\User $user
 *
 */
abstract class Base
{
    const PER_PAGE = 24;

    /**
     *
     * @var \Ufw\Application
     */
    protected $application;

    protected $disableLayout = false;

    protected $layout = 'site';

    protected $currentActionMethod = null;

    /**
     * Controller ident - url friendly name, kebab case
     * Alias controller does not apply here, used by render helper
     *
     * @var string
     */
    protected $ident = null;

    public function __construct(\Ufw\Application $application)
    {
        $this->setApplication($application);
        $this->init();
    }

    public function init()
    {
    }

    protected function middlewaresPre()
    {
        return [];
    }

    protected function middlewaresPost()
    {
        return [];
    }

    protected function getDb()
    {
        return \Ufw\Db\Db::instance();
    }

    public function __call($method, $args)
    {
        $helper = null;
        try {
            $helper = \Ufw\Registry::getInstance()->get('application')->getHelper($method, $this);
        } catch (\Exception $e) {
        }
        
        if ($helper) {
            $helper->setController($this);
            return call_user_func_array($helper, $args);
        }
        
        throw new \Exception("Method $method in " . get_class($this) . " not found", -1);
    }

    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array(
                $this,
                'get' . ucfirst($name)
            ));
        }
        return null;
    }

    public function dispatch($action)
    {
        $this->setCurrentActionMethod($action);
        
        $this->preDispatch();
        
        $this->processMiddlewares(array_merge($this->middlewaresPre(), [
            \Ufw\Middleware\Dispatch::class,
            \Ufw\Middleware\Layout::class
        ], $this->middlewaresPost()));
        
        $this->postDispatch();
    }

    public function getCurrentActionName()
    {
        return $this->getRequest()->action;
    }

    public function getCurrentControllerName()
    {
        return $this->getRequest()->controller;
    }

    public function getCurrentModuleName()
    {
        return $this->getRequest()->module;
    }

    public function getScriptName($action = null)
    {
        if (!$action) {
            $action = $this->getCurrentActionName();
        }
        return $this->getApplication()->getScriptName($action);
    }

    public function getHelper($name)
    {
        return $this->getApplication()->getHelper($name, $this);
    }

    protected function preDispatch()
    {
        return $this;
    }

    protected function postDispatch()
    {
        return $this;
    }

    protected function processMiddlewares($middlewares)
    {
        if (!$middlewares) {
            return $this;
        }
        $pipeline = new \Laminas\Stratigility\MiddlewarePipe();
        // $pipeline->setResponsePrototype($this->getResponse());
        foreach ($middlewares as $middleware) {
            if (is_callable($middleware)) {
                $pipeline->pipe(new CallableMiddlewareDecorator($middleware));
            } elseif ($middleware instanceof \Ufw\Middleware\Base) {
                $pipeline->pipe($middleware);
            } else {
                $pipeline->pipe(new $middleware($this));
            }
        }
        $pipeline->pipe(new \Ufw\Middleware\DummyFinal($this));
        $pipeline->handle($this->getRequest());
        return $this;
    }

    /**
     *
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     *
     * @param \Ufw\Application $application
     */
    public function setApplication(\Ufw\Application $application)
    {
        $this->application = $application;
        return $this;
    }

    public function getRequest()
    {
        return $this->getApplication()->getRequest();
    }

    public function getResponse()
    {
        return $this->getApplication()->getResponse();
    }

    /**
     *
     * @return string
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     *
     * @param string $layout
     */
    public function setLayout($layout)
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getDisableLayout()
    {
        return $this->disableLayout;
    }

    /**
     *
     * @param
     *            boolean
     */
    public function setDisableLayout($disableLayout)
    {
        $this->disableLayout = $disableLayout;
        return $this;
    }

    public function getUser()
    {
        return $this->user();
    }

    public function getCurrentActionMethod()
    {
        return $this->currentActionMethod;
    }

    public function setCurrentActionMethod($currentActionMethod)
    {
        $this->currentActionMethod = $currentActionMethod;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getIdent()
    {
        if (!isset($this->ident)) {
            $patterns = [
                '~.*\\\\([^\\\\]+)$~',
                '~(?<=(?:[A-Z]))([A-Z]+)([A-Z][A-z])~',
                '~(?<=(?:[a-z0-9]))([A-Z])~'
            ];
            $replacements = [
                '$1',
                '$1-$2',
                '-$1'
            ];
            $this->ident = strtolower(preg_replace($patterns, $replacements, static::class));
            if ($module = $this->getRequest()->getModule()) {
                $this->ident = strtolower(preg_replace($patterns, $replacements, $module)) . '/' . $this->ident;
            }
        }
        return $this->ident;
    }
}
