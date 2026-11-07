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
     * @var array<string, mixed>
     */
    protected $routeDef = array();

    /**
     *
     * @var \Ufw\Router
     */
    protected $router = null;

    /**
     *
     * @var string|null
     */
    protected $provisionalModuleName = null;

    /**
     *
     * @var string|null
     */
    protected $provisionalControllerName = null;

    /**
     *
     * @var string|null
     */
    protected $provisionalActionName = null;

    /**
     *
     * @var string|null
     */
    protected $controllerClass = null;

    /**
     *
     * @var string|null
     */
    protected $actionMethod = null;

    /**
     * @param array<string, mixed> $routeDef
     */
    public function __construct(\Ufw\Router $router, array $routeDef)
    {
        $this->setRouter($router)->setRouteDef($routeDef);
    }

    /**
     * @param array<string, mixed> $params
     * @param bool $reset
     * @param bool $withQS
     * @param bool $encode
     * @return string
     */
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
        $patterns = $this->routeDef['patterns'];
        if (!is_array($patterns)) {
            return false;
        }
        foreach ($patterns as $pattern) {
            if (!is_string($pattern)) {
                continue;
            }
            $match = array();
            if (preg_match($pattern, $path, $match)) {
                return $match;
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $match
     * @return bool
     */
    public function check($match)
    {
        $def = $this->getRouteDef();
        $constraints = $this->getConstraints();
        if ($constraints === array()) {
            return true;
        }

        if (isset($match['module']) && $match['module']) {
            $this->setProvisionalModuleName($this->optionalRouteString($match['module']));
        }

        $defaults = $this->getDefaults();
        $controllerFromMatch = isset($match['controller']) ? $this->optionalRouteString($match['controller']) : null;
        $defaultController = isset($defaults['controller']) ? $this->optionalRouteString($defaults['controller']) : null;
        $this->setProvisionalControllerName($controllerFromMatch ?: $defaultController);

        if (!empty($constraints['checkIfControllerIsRegistered'])) {
            $controllers = $this->getApplication()->getDispatchControllers($this->getProvisionalModuleName());
            if (!in_array($this->getProvisionalControllerName(), $controllers, true)) {
                return false;
            }
        }

        $httpMethods = isset($constraints['httpMethod']) && is_array($constraints['httpMethod']) ? $constraints['httpMethod'] : null;
        if (is_array($httpMethods) && $httpMethods !== array()) {
            if (!in_array($this->getRequest()->getMethod(), $httpMethods, true)) {
                return false;
            }
        }

        $hasAction = false;
        if (isset($match['action']) && $match['action']) {
            $this->setProvisionalActionName($this->optionalRouteString($match['action']));
            $hasAction = !empty($constraints['checkAction']) ? $this->hasAction() : true;
        } elseif (!empty($defaults['action'])) {
            $this->setProvisionalActionName($this->optionalRouteString($defaults['action']));
            $hasAction = !empty($constraints['checkAction']) ? $this->hasAction() : true;
        }

        if (!$hasAction) {
            return false;
        }

        foreach ($constraints as $key => $constraint) {
            if (in_array($key, array(
                'checkIfControllerIsRegistered',
                'checkAction',
                'httpMethod',
            ), true)) {
                continue;
            }
            if (!is_string($constraint)) {
                return false;
            }

            $valueRaw = isset($match[$key]) && $match[$key] ? $match[$key] : ($defaults[$key] ?? null);
            $value = $this->optionalRouteString($valueRaw);
            if ($value === null) {
                $value = '';
            }

            if (!preg_match($constraint, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $path
     * @param array<int|string, mixed> $match
     * @return $this
     */
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
        if (!empty($this->getOptions()['allowUriParams'])) {
            $matchedPrefix = isset($match[0]) && is_string($match[0]) ? $match[0] : '';
            $pathparts = explode('/', trim(substr($path, strlen($matchedPrefix)), '/'));
            $numSegs = count($pathparts);
            for ($i = 0; $i < $numSegs; $i = $i + 2) {
                    $key = urldecode($pathparts[$i]);
                    $val = isset($pathparts[$i + 1]) ? urldecode($pathparts[$i + 1]) : null;
                    $params[$key] = (isset($params[$key]) ? (array_merge((array) $params[$key], array(
                        $val
                    ))) : $val);
            }
        }
        $defaults = $this->getDefaults();
        $request->setAttributes($values + $params + $defaults)->setDefaults($defaults);
        
        return $this;
    }

    /**
     * @return bool
     */
    protected function hasAction()
    {
        $class = $this->getControllerClass();
        $methodName = $this->getActionMethod();
        if (!is_string($class) || $class === '' || !is_string($methodName) || $methodName === '') {
            return false;
        }
        try {
            $method = new \ReflectionMethod($class, $methodName);
            return $method->isPublic();
        } catch (\Exception $e) {
        }
        return false;
    }

    /**
     *
     * @return array<string, mixed>
     */
    public function getRouteDef()
    {
        return $this->routeDef;
    }

    /**
     *
     * @param array<string, mixed> $routeDef
     * @return $this
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
     * @return $this
     */
    public function setRouter($router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     *
     * @return string|null
     */
    public function getProvisionalModuleName()
    {
        return $this->provisionalModuleName;
    }

    /**
     *
     * @param string|null $provisionalModuleName
     * @return $this
     */
    public function setProvisionalModuleName($provisionalModuleName)
    {
        $this->provisionalModuleName = $provisionalModuleName;
        $this->controllerClass = null;
        return $this;
    }

    /**
     *
     * @return string|null
     */
    public function getProvisionalControllerName()
    {
        return $this->provisionalControllerName;
    }

    /**
     *
     * @param string|null $provisionalControllerName
     * @return $this
     */
    public function setProvisionalControllerName($provisionalControllerName)
    {
        $this->provisionalControllerName = $provisionalControllerName;
        $this->controllerClass = null;
        return $this;
    }

    /**
     *
     * @return string|null
     */
    public function getControllerClass()
    {
        if (!isset($this->controllerClass)) {
            $moduleName = $this->getProvisionalModuleName();
            $controllerName = $this->getProvisionalControllerName();
            $config = $this->getApplication()->getConfig();
            $dispatch = (is_array($config) && isset($config['dispatch']) && is_array($config['dispatch'])) ? $config['dispatch'] : array();
            $aliasses = array();
            if ($moduleName) {
                $modules = (isset($dispatch['module']) && is_array($dispatch['module'])) ? $dispatch['module'] : array();
                $module = (isset($modules[$moduleName]) && is_array($modules[$moduleName])) ? $modules[$moduleName] : array();
                if (isset($module['controller_aliases']) && is_array($module['controller_aliases'])) {
                    $aliasses = $module['controller_aliases'];
                }
            } elseif (isset($dispatch['controller_aliases']) && is_array($dispatch['controller_aliases'])) {
                $aliasses = $dispatch['controller_aliases'];
            }
            if (isset($aliasses[$controllerName]) && is_string($aliasses[$controllerName])) {
                $controllerName = $aliasses[$controllerName];
            }

            $strings = \Ufw\Utils\Strings::getInstance();
            $controllerPhpFriendlyName = ucfirst($strings->formatToPhpFriendlyName($this->optionalRouteString($controllerName) ?: '', ''));
            $modulePhpFriendlyName = ucfirst($strings->formatToPhpFriendlyName($this->optionalRouteString($moduleName) ?: '', ''));

            $controllerClass = "\\Controller\\" . ($modulePhpFriendlyName ? $modulePhpFriendlyName . '\\' : '') . $controllerPhpFriendlyName . '\\' . ucfirst($strings->formatToPhpFriendlyName((string) $this->getProvisionalActionName(), ''));
            if (class_exists($controllerClass)) {
                $this->setActionMethod($this->getRouter()
                    ->getRequest()
                    ->getMethod());
            } else {
                $controllerClass = "\\Controller\\" . ($modulePhpFriendlyName ? $modulePhpFriendlyName . '\\' : '') . $controllerPhpFriendlyName;
                $this->setActionMethod($strings->formatToPhpFriendlyName((string) $this->getProvisionalActionName(), 'Action'));
            }
            $this->setControllerClass($controllerClass);
        }
        return $this->controllerClass;
    }

    /**
     *
     * @param string $controllerClass
     * @return $this
     */
    public function setControllerClass($controllerClass)
    {
        $this->controllerClass = $controllerClass;
        return $this;
    }

    /**
     *
     * @return string|null
     */
    public function getProvisionalActionName()
    {
        return $this->provisionalActionName;
    }

    /**
     *
     * @param string|null $provisionalActionName
     * @return $this
     */
    public function setProvisionalActionName($provisionalActionName)
    {
        $this->provisionalActionName = $provisionalActionName;
        $this->controllerClass = null;
        return $this;
    }

    /**
     *
     * @return string|null
     */
    public function getActionMethod()
    {
        return $this->actionMethod;
    }

    /**
     *
     * @param string|null $actionMethod
     * @return $this
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

    /**
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return $this->getRouter()->getApplication();
    }

    /**
     * Shared first half of {@see buildUrl()}: merge caller {@code $params} with request attributes, resolve
     * controller/action, and omit keys equal to defaults (same rules as {@see RegEx} / {@see Literal}).
     *
     * @param array<string, mixed> $params
     * @param bool  $reset
     * @return array<string, mixed> Params for path rendering (may still include {@code controller} / {@code action}).
     */
    protected function prepareBuildUrlParams(array $params, $reset)
    {
        $values = $reset ? array() : $this->getRequest()->getAttributes();
        $defaults = $this->getDefaults();

        $controller = isset($params['controller']) && $params['controller'] ? $params['controller'] : ($reset ? null : ($values['controller'] ?? null));
        $action = isset($params['action']) && $params['action'] ? $params['action'] : ($reset ? null : ($values['action'] ?? null));

        $app = \Ufw\Registry::getInstance()->get('application');
        $module = isset($params['module']) && is_string($params['module']) ? $params['module'] : null;
        if (!$controller && $app instanceof \Ufw\Application && array_intersect($app->getDispatchControllers($module), array_keys($params))) {
            $currentController = $this->getApplication()->getCurrentController();
            if ($currentController !== null) {
                $controller = $currentController->getCurrentControllerName();
            }
        }
        unset($params['controller'], $params['action'], $values['controller'], $values['action']);

        // Defaults can normally be dropped from the URL (e.g. "/" instead of "/catalog/index"). When the route
        // uses allowUriParams and there are leftover params that will be appended as "/key/value" tail segments,
        // the bare "/" prefix would produce URLs like "/page/2" which the router then matches as
        // "controller=page, action=2" (404). Keep controller/action explicit in that case so build and match stay symmetric.
        $hasUriTail = !empty($this->getOptions()['allowUriParams'])
            && ($params !== array() || (!$reset && array_diff_key($values, array('controller' => 1, 'action' => 1)) !== array()));

        $defaultController = isset($defaults['controller']) && is_string($defaults['controller']) ? $defaults['controller'] : '';
        $defaultAction = isset($defaults['action']) && is_string($defaults['action']) ? $defaults['action'] : '';

        if (isset($controller) && is_string($controller) && strcmp($controller, $defaultController) === 0 && !$hasUriTail) {
            $controller = null;
        }
        if (isset($action) && is_string($action) && strcmp($action, $defaultAction) === 0 && !$hasUriTail) {
            $action = null;
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
     * @param array<int, string> $pathSegments Initial path pieces (typically one string starting with {@code /}).
     * @param array<string, mixed> $params       Remaining parameters; consumed as extra segments when {@code allowUriParams} is set.
     * @param bool  $withQS
     * @param bool  $encode
     * @return string
     */
    protected function finalizeBuildUrlFromSegments(array $pathSegments, array $params, $withQS, $encode)
    {
        $url = $pathSegments;
        $paramsLeftOver = $params;
        if (!empty($this->getOptions()['allowUriParams'])) {
            foreach ($params as $key => $value) {
                if (is_null($value)) {
                    continue;
                }
                $keyEncoded = $encode ? urlencode($key) : $key;
                if (is_array($value)) {
                    foreach ($value as $arrayValue) {
                        $segment = $this->urlSegmentString($arrayValue);
                        if ($segment === null) {
                            continue;
                        }
                        $url[] = $keyEncoded;
                        $url[] = $encode ? urlencode($segment) : $segment;
                    }
                } else {
                    $segment = $this->urlSegmentString($value);
                    if ($segment === null) {
                        continue;
                    }
                    $url[] = $keyEncoded;
                    $url[] = $encode ? urlencode($segment) : $segment;
                }
            }
            $paramsLeftOver = array();
        }
        $path = join('/', $url);
        $qs = $withQS ? array_merge($this->getRequest()->getQueryParams(), $paramsLeftOver) : [];

        return $path . ($qs ? '?' . http_build_query($qs) : '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConstraints()
    {
        $constraints = $this->routeDef['constraints'] ?? array();
        if (!is_array($constraints)) {
            return array();
        }

        return $this->normalizeStringKeyedArray($constraints);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaults()
    {
        $defaults = $this->routeDef['defaults'] ?? array();
        if (!is_array($defaults)) {
            return array();
        }

        return $this->normalizeStringKeyedArray($defaults);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions()
    {
        $options = $this->routeDef['options'] ?? array();
        if (!is_array($options)) {
            return array();
        }

        return $this->normalizeStringKeyedArray($options);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function normalizeStringKeyedArray($value)
    {
        if (!is_array($value)) {
            return array();
        }
        $out = array();
        foreach ($value as $key => $val) {
            if (is_string($key)) {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    protected function optionalRouteString($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    protected function urlSegmentString($value)
    {
        return $this->optionalRouteString($value);
    }
}
