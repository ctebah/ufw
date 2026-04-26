<?php
namespace Ufw\Route;

/**
 * One route definition from the router: holds {@see $routeDef} (defaults, constraints, URL builder), resolves
 * controller/action class names, validates a regex match ({@see check()}), and pushes attributes onto {@see \Ufw\Request}
 * ({@see provideAttributes()}). Subclasses implement {@see buildUrl()}.
 */
abstract class Base
{

    /**
     *
     * @var array
     */
    protected $routeDef = null;

    /**
     *
     * @var \Ufw\Router
     */
    protected $router = null;

    /**
     *
     * @var string
     */
    protected $provisionalModuleName = null;

    /**
     *
     * @var string
     */
    protected $provisionalControllerName = null;

    /**
     *
     * @var string
     */
    protected $provisionalActionName = null;

    /**
     *
     * @var string
     */
    protected $controllerClass = null;

    /**
     *
     * @var string
     */
    protected $actionMethod = null;

    public function __construct(\Ufw\Router $router, array $routeDef)
    {
        $this->setRouter($router)->setRouteDef($routeDef);
    }

    abstract public function buildUrl($params = array(), $reset = true, $withQS = false, $encode = true);

    /**
     * Try to match the request path. Default implementation runs each entry in the route definition’s
     * {@code patterns} array with {@see preg_match()}. Override for non-regex matching (e.g. {@see Literal}).
     *
     * @param string $path Path only (no query string), same as {@see Router::match()} uses.
     * @return array<int|string, string>|false Match array compatible with {@see check()} / {@see provideAttributes()}, or false.
     */
    public function matchRequestPath($path)
    {
        if (!isset($this->routeDef['patterns'])) {
            return false;
        }
        foreach ($this->routeDef['patterns'] as $pattern) {
            $match = [];
            if (preg_match($pattern, $path, $match)) {
                return $match;
            }
        }

        return false;
    }

    public function check($match)
    {
        $def = $this->getRouteDef();
        if (!isset($def['constraints'])) {
            return true;
        }
        
        if (isset($match['module']) && $match['module']) {
            $this->setProvisionalModuleName($match['module']);
        }
        
        @$this->setProvisionalControllerName($match['controller'] ?: $this->getRouteDef()['defaults']['controller']);
        if ($def['constraints']['checkIfControllerIsRegistered']) {
            if (!@in_array($this->getProvisionalControllerName(), $this->getApplication()->getDispatchControllers($this->getProvisionalModuleName()))) {
                return false;
            }
        }
        
        if (@$def['constraints']['httpMethod']) {
            if (!in_array($this->getRequest()->getMethod(), $def['constraints']['httpMethod'])) {
                return false;
            }
        }
        
        $hasAction = false;
        if (isset($match['action']) && $match['action']) {
            $this->setProvisionalActionName($match['action']);
            $hasAction = $def['constraints']['checkAction'] ? $this->hasAction() : true;
        } elseif ($this->getRouteDef()['defaults']['action']) {
            $this->setProvisionalActionName($this->getRouteDef()['defaults']['action']);
            $hasAction = $def['constraints']['checkAction'] ? $this->hasAction() : true;
        }
        
        if (!$hasAction) {
            return false;
        }
        
        foreach ($def['constraints'] as $key => $constraint) {
            if (in_array($key, [
                'checkIfControllerIsRegistered',
                'checkAction',
                'httpMethod'
            ])) {
                continue;
            }
            
            $value = $match[$key] ?: $this->getRouteDef()['defaults'][$key];
            
            if (!preg_match($constraint, $value)) {
                return false;
            }
        }
        
        return true;
    }

    public function provideAttributes(\Ufw\Request $request, $path, $match)
    {
        $values = [
            'controller' => $this->getProvisionalControllerName(),
            'action' => $this->getProvisionalActionName()
        ];
        $params = [];
        foreach (array_keys($match) as $key) {
            if (is_int($key) || in_array($key, [
                'controller',
                'action'
            ])) {
                continue;
            }
            $params[$key] = $match[$key];
        }
        if (!empty($this->getRouteDef()['options']['allowUriParams'])) {
            $pathparts = explode('/', trim(substr($path, strlen($match[0])), '/'));
            if ($numSegs = count($pathparts)) {
                for ($i = 0; $i < $numSegs; $i = $i + 2) {
                    $key = urldecode($pathparts[$i]);
                    $val = isset($pathparts[$i + 1]) ? urldecode($pathparts[$i + 1]) : null;
                    $params[$key] = (isset($params[$key]) ? (array_merge((array) $params[$key], array(
                        $val
                    ))) : $val);
                }
            }
        }
        $defaults = $this->getRouteDef()['defaults'];
        $request->setAttributes($values + $params + $defaults)->setDefaults($defaults);
        
        return $this;
    }

    protected function hasAction()
    {
        try {
            $method = new \ReflectionMethod($this->getControllerClass(), $this->getActionMethod());
            return $method->isPublic();
        } catch (\Exception $e) {
        }
        return false;
    }

    /**
     *
     * @return array
     */
    public function getRouteDef()
    {
        return $this->routeDef;
    }

    /**
     *
     * @param array $routeDef
     */
    public function setRouteDef($routeDef)
    {
        $this->routeDef = $routeDef;
        return $this;
    }

    /**
     *
     * @return \Ufw\Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     *
     * @param \Ufw\Router $router
     */
    public function setRouter($router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getProvisionalModuleName()
    {
        return $this->provisionalModuleName;
    }

    /**
     *
     * @param string $provisionalModuleName
     */
    public function setProvisionalModuleName($provisionalModuleName)
    {
        $this->provisionalModuleName = $provisionalModuleName;
        $this->controllerClass = null;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getProvisionalControllerName()
    {
        return $this->provisionalControllerName;
    }

    /**
     *
     * @param string $provisionalControllerName
     */
    public function setProvisionalControllerName($provisionalControllerName)
    {
        $this->provisionalControllerName = $provisionalControllerName;
        $this->controllerClass = null;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getControllerClass()
    {
        if (!isset($this->controllerClass)) {
            $moduleName = $this->getProvisionalModuleName();
            $controllerName = $this->getProvisionalControllerName();
            $aliasses = $moduleName ? $this->getApplication()->getConfig()['dispatch']['module'][$moduleName]['controller_aliases'] : $this->getApplication()->getConfig()['dispatch']['controller_aliases'];
            if (isset($aliasses[$controllerName])) {
                $controllerName = $aliasses[$controllerName];
            }
            
            $controllerPhpFriendlyName = ucfirst(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($controllerName, ''));
            $modulePhpFriendlyName = ucfirst(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($moduleName, ''));
            
            $controllerClass = "\\Controller\\" . ($modulePhpFriendlyName ? $modulePhpFriendlyName . '\\' : '') . $controllerPhpFriendlyName . '\\' . ucfirst(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($this->getProvisionalActionName(), ''));
            if (class_exists($controllerClass)) {
                $this->setActionMethod($this->getRouter()
                    ->getRequest()
                    ->getMethod());
            } else {
                $controllerClass = "\\Controller\\" . ($modulePhpFriendlyName ? $modulePhpFriendlyName . '\\' : '') . $controllerPhpFriendlyName;
                $this->setActionMethod(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($this->getProvisionalActionName(), 'Action'));
            }
            $this->setControllerClass($controllerClass);
        }
        return $this->controllerClass;
    }

    /**
     *
     * @param string $controllerClass
     */
    public function setControllerClass($controllerClass)
    {
        $this->controllerClass = $controllerClass;
        return $this;
    }

    /**
     *
     * @return string $provisionalActionName
     */
    public function getProvisionalActionName()
    {
        return $this->provisionalActionName;
    }

    /**
     *
     * @param string $provisionalActionName
     */
    public function setProvisionalActionName($provisionalActionName)
    {
        $this->provisionalActionName = $provisionalActionName;
        $this->controllerClass = null;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getActionMethod()
    {
        return $this->actionMethod;
    }

    /**
     *
     * @param string $actionMethod
     */
    public function setActionMethod($actionMethod)
    {
        $this->actionMethod = $actionMethod;
        return $this;
    }

    /**
     *
     * @return \Ufw\Request
     */
    public function getRequest()
    {
        return $this->getRouter()->getRequest();
    }

    public function getApplication()
    {
        return $this->getRouter()->getApplication();
    }

    /**
     * Shared first half of {@see buildUrl()}: merge caller {@code $params} with request attributes, resolve
     * controller/action, and omit keys equal to defaults (same rules as {@see RegEx} / {@see Literal}).
     *
     * @param array $params
     * @param bool  $reset
     * @return array Params for path rendering (may still include {@code controller} / {@code action}).
     */
    protected function prepareBuildUrlParams(array $params, $reset)
    {
        $values = $reset ? [] : $this->getRequest()->getAttributes();
        $defaults = $this->getRouteDef()['defaults'];

        $controller = isset($params['controller']) && $params['controller'] ? $params['controller'] : ($reset ? null : $values['controller']);
        $action = isset($params['action']) && $params['action'] ? $params['action'] : ($reset ? null : $values['action']);

        $app = \Ufw\Registry::getInstance()->get('application');
        // @todo: module param?!
        if (!$controller && array_intersect(@$app->getDispatchControllers($params['module']), array_keys($params))) {
            $controller = $this->getApplication()
                ->getCurrentController()
                ->getCurrentControllerName();
        }
        unset($params['controller'], $params['action'], $values['controller'], $values['action']);

        // Defaults can normally be dropped from the URL (e.g. "/" instead of "/catalog/index"). When the route
        // uses allowUriParams and there are leftover params that will be appended as "/key/value" tail segments,
        // the bare "/" prefix would produce URLs like "/page/2" which the router then matches as
        // "controller=page, action=2" (404). Keep controller/action explicit in that case so build and match stay symmetric.
        $hasUriTail = !empty($this->getRouteDef()['options']['allowUriParams'])
            && ($params !== [] || (!$reset && array_diff_key($values, ['controller' => 1, 'action' => 1]) !== []));

        if (isset($controller) && !strcmp($controller, $defaults['controller']) && !$hasUriTail) {
            unset($controller);
        }
        if (isset($action) && !strcmp($action, $defaults['action']) && !$hasUriTail) {
            unset($action);
        }

        if (!$reset) {
            $params = $params + $values;
        }

        return $params +
            (@$controller ? ['controller' => $controller] : []) +
            (@$action ? ['action' => $action] : []);
    }

    /**
     * Shared tail of {@see buildUrl()}: append {@code allowUriParams} key/value segments, then optional query string.
     *
     * @param array $pathSegments Initial path pieces (typically one string starting with {@code /}).
     * @param array $params       Remaining parameters; consumed as extra segments when {@code allowUriParams} is set.
     * @param bool  $withQS
     * @param bool  $encode
     * @return string
     */
    protected function finalizeBuildUrlFromSegments(array $pathSegments, array $params, $withQS, $encode)
    {
        $url = $pathSegments;
        $paramsLeftOver = $params;
        if (!empty($this->getRouteDef()['options']['allowUriParams'])) {
            foreach ($params as $key => $value) {
                if (is_null($value)) {
                    continue;
                }
                $key = $encode ? urlencode($key) : $key;
                if (is_array($value)) {
                    foreach ($value as $arrayValue) {
                        $arrayValue = $encode ? urlencode($arrayValue) : $arrayValue;
                        $url[] = $key;
                        $url[] = $arrayValue;
                    }
                } else {
                    if ($encode) {
                        $value = urlencode($value);
                    }
                    $url[] = $key;
                    $url[] = $value;
                }
            }
            $paramsLeftOver = [];
        }
        $path = join('/', $url);
        $qs = $withQS ? array_merge($this->getRequest()->getQueryParams(), $paramsLeftOver) : [];

        return $path . ($qs ? '?' . http_build_query($qs) : '');
    }
}
