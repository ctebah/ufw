<?php
namespace Ufw\Controller;

use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;

/**
 * Base HTTP controller: owns layout/middleware around the action, resolves helpers by name via {@see __call},
 * and exposes the current {@see \Ufw\Application}, request, and response for the dispatch lifecycle.
 *
 * @method string partial(string $script, array<string, mixed> $vars = array()) render some partial script
 * @method void redirect(string $url) redirect to url
 * @method string render(array<string, mixed> $vars = array(), string $script = null, bool $controllerDir = true) render view script, pass $vars, if script is omitted use script name after action name in controller name directory (3rd param true)
 * @method \Ufw\Helper\User user() current logged on user helper
 * @method string url(array<string, mixed> $params = [], bool $reset = true, bool $withQS = false, string $routeName = 'default', bool $encode = true) Same as {@see \Ufw\Helper\Url::__invoke} - returns a URL string; the {@see \Ufw\Helper\Url} instance is not exposed through {@see __call()}
 * @method \Ufw\Helper\FlashMessenger flashMessenger() Flash Messenger - implement session-based messages
 * @method string translate(string $str) pass $str through Registry translator (see \Ufw\Helper\Translate)
 *
 * @property \Ufw\Helper\User $user
 */
abstract class Base
{
    const PER_PAGE = 24;

    /**
     * @var \Ufw\Application
     */
    protected $application;

    /**
     * @var bool
     */
    protected $disableLayout = false;

    /**
     * @var string
     */
    protected $layout = 'site';

    /**
     * @var string|null
     */
    protected $currentActionMethod = null;

    /**
     * Controller ident - url friendly name, kebab case
     * Alias controller does not apply here, used by render helper
     *
     * @var string|null
     */
    protected $ident = null;

    public function __construct(\Ufw\Application $application)
    {
        $this->setApplication($application);
        $this->init();
    }

    /**
     * @return void
     */
    public function init()
    {
    }

    /**
     * @return list<class-string|callable>
     */
    protected function middlewaresPre()
    {
        return [];
    }

    /**
     * @return list<class-string|callable>
     */
    protected function middlewaresPost()
    {
        return [];
    }

    /**
     * @return \Ufw\Db\Db
     */
    protected function getDb()
    {
        return \Ufw\Db\Db::forApp();
    }

    /**
     * @param string $method
     * @param array<int, mixed> $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        $helper = null;
        try {
            $helper = \Ufw\Registry::getInstance()->getApplication()->getHelper($method, $this);
        } catch (\Exception $e) {
        }
        
        if ($helper instanceof \Ufw\Helper\Base) {
            $helper->setController($this);
            if (is_callable($helper)) {
                return call_user_func_array($helper, $args);
            }
        }
        
        throw new \Exception("Method $method in " . get_class($this) . " not found", -1);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get' . ucfirst($name);

        return $this->$getter();
    }

    /**
     * @param string $action
     * @return void
     */
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

    /**
     * @return string|null
     */
    public function getCurrentActionName()
    {
        $request = $this->getRequest();
        return $request !== null ? $request->getAction() : null;
    }

    /**
     * @return string|null
     */
    public function getCurrentControllerName()
    {
        $request = $this->getRequest();
        return $request !== null ? $request->getController() : null;
    }

    /**
     * @return string|null
     */
    public function getCurrentModuleName()
    {
        $request = $this->getRequest();
        if ($request === null) {
            return null;
        }
        $module = $request->getAttribute('module');
        return is_string($module) ? $module : null;
    }

    /**
     * @param string|null $action
     * @return string
     */
    public function getScriptName($action = null)
    {
        if (!$action) {
            $action = $this->getCurrentActionName();
        }
        return $this->getApplication()->getScriptName((string) ($action ?? ''));
    }

    /**
     * @param string $name
     * @return \Ufw\Helper\Base|null
     */
    public function getHelper($name)
    {
        return $this->getApplication()->getHelper($name, $this);
    }

    /**
     * @return $this
     */
    protected function preDispatch()
    {
        return $this;
    }

    /**
     * @return $this
     */
    protected function postDispatch()
    {
        return $this;
    }

    /**
     * @param array<int, mixed> $middlewares
     * @return $this
     */
    protected function processMiddlewares(array $middlewares)
    {
        if (!$middlewares) {
            return $this;
        }
        $pipeline = new \Laminas\Stratigility\MiddlewarePipe();
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof \Ufw\Middleware\Base) {
                $pipeline->pipe($middleware);
            } elseif (is_callable($middleware)) {
                $pipeline->pipe(new CallableMiddlewareDecorator($middleware));
            } elseif (is_string($middleware) && is_subclass_of($middleware, \Ufw\Middleware\Base::class)) {
                $pipeline->pipe(new $middleware($this));
            }
        }
        $pipeline->pipe(new \Ufw\Middleware\DummyFinal($this));
        $request = $this->getRequest();
        if ($request === null) {
            throw new \RuntimeException('Controller dispatch requires a request.');
        }
        $pipeline->handle($request);
        return $this;
    }

    /**
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * @param \Ufw\Application $application
     * @return $this
     */
    public function setApplication(\Ufw\Application $application)
    {
        $this->application = $application;
        return $this;
    }

    /**
     * @return \Ufw\Request|null
     */
    public function getRequest()
    {
        return $this->getApplication()->getRequest();
    }

    /**
     * @return \Ufw\Response
     */
    public function getResponse()
    {
        return $this->getApplication()->getResponse();
    }

    /**
     * @return string
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * @param string $layout
     * @return $this
     */
    public function setLayout($layout)
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDisableLayout()
    {
        return $this->disableLayout;
    }

    /**
     * @param bool $disableLayout
     * @return $this
     */
    public function setDisableLayout($disableLayout)
    {
        $this->disableLayout = $disableLayout;
        return $this;
    }

    /**
     * @return \Ufw\Helper\User
     */
    public function getUser()
    {
        return $this->user();
    }

    /**
     * @return string|null
     */
    public function getCurrentActionMethod()
    {
        return $this->currentActionMethod;
    }

    /**
     * @param string|null $currentActionMethod
     * @return $this
     */
    public function setCurrentActionMethod($currentActionMethod)
    {
        $this->currentActionMethod = $currentActionMethod;
        return $this;
    }

    /**
     * @return string
     */
    public function getIdent()
    {
        if ($this->ident === null) {
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
            $classIdent = preg_replace($patterns, $replacements, static::class);
            $this->ident = strtolower($classIdent !== null ? $classIdent : static::class);
            $module = $this->getCurrentModuleName();
            if ($module !== null && $module !== '') {
                $moduleIdent = preg_replace($patterns, $replacements, $module);
                $moduleSlug = strtolower($moduleIdent !== null ? $moduleIdent : $module);
                $this->ident = $moduleSlug . '/' . $this->ident;
            }
        }
        return $this->ident;
    }
}
