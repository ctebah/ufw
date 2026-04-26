<?php
namespace Ufw;

/**
 * HTTP application shell: bootstrap config, {@see \Ufw\Request} / {@see \Ufw\Response}, {@see \Ufw\Router}, helpers,
 * and the current {@see \Ufw\Controller\Base} during dispatch.
 *
 * @method static Application getInstance()
 */
class Application
{
    use Singleton;

    const URI_DELIMITER = '/';

    /**
     *
     * @var \Ufw\Controller\Base
     */
    protected $currentController = null;

    protected static $config = null;

    protected $dispatchDefaults = array();

    protected $dispatchControllers = array();

    protected $helpersInstantiated = array();

    /**
     *
     * @var \Ufw\Request|null
     */
    protected $request = null;

    /**
     *
     * @var \Ufw\Response
     */
    protected $response = null;

    /**
     *
     * @var \Ufw\Router
     */
    protected $router;

    public function __construct()
    {
        Registry::getInstance()->set('application', $this);
        if (!$this->getConfig()) {
            $this->setConfig(include Paths::application() . '/config/app.php');
        }
        
        if (isset($this->getConfig()['dispatch'])) {
            if (isset($this->getConfig()['dispatch']['controllers'])) {
                $this->setDispatchControllers($this->getConfig()['dispatch']['controllers']);
            }
            if (isset($this->getConfig()['dispatch']['defaults'])) {
                $this->setDispatchDefaults($this->getConfig()['dispatch']['defaults']);
            }
        }
    }

    public function run($request = null)
    {
        try {
            $this->dispatch($request);
        } catch (\Ufw\Exception\BreakExecution $e) {
            $statusCode = $this->getResponse()->getStatusCode();
            if (($statusCode >= 300) && ($statusCode <= 310)) {
                $this->getResponse()->emit();
                die();
            }
            // HTTP Basic (etc.): keep WWW-Authenticate; do not run dispatchError - it would reset() and drop the header.
            if ($statusCode === 401 && $this->getResponse()->hasHeader('WWW-Authenticate')) {
                $this->getResponse()->emit();
                die();
            }
            $actions = [
                400 => 'bad-request',
                401 => 'unauthorized',
                403 => 'forbidden',
                404 => 'not-found'
            ];
            
            $this->logExceptionToFile($e, $statusCode);
            
            $this->getResponse()->reset($statusCode);
            $this->dispatchError(isset($actions[$statusCode]) ? $actions[$statusCode] : 'not-found');
        } catch (\Exception $e) {
            // 404?
            $this->logExceptionToFile($e, null);
            $this->getResponse()->reset(404);
            if ($this->getRequest()) {
                $this->getRequest()->setRuntimeException($e);
            }
            $this->dispatchError();
        }
        $this->getResponse()->emit();
        return $this;
    }

    /**
     *
     * @param string $name
     * @param \Ufw\Controller\Base $controller
     * @return \Ufw\Helper\Base
     */
    public function getHelper($name, $controller = null)
    {
        $name = ucfirst($name);
        if (isset($this->helpersInstantiated[$name])) {
            $helper = $this->helpersInstantiated[$name];
        } elseif (class_exists("\\Helper\\$name")) {
            $className = "\\Helper\\$name";
            $this->helpersInstantiated[$name] = $helper = new $className();
        } elseif (class_exists("\\Ufw\\Helper\\$name")) {
            $className = "\\Ufw\\Helper\\$name";
            $this->helpersInstantiated[$name] = $helper = new $className();
        }
        if ($helper && $controller) {
            $helper->setController($controller);
        }
        return $helper;
    }

    public function dispatch($request = null)
    {
        $this->setRequest($request ? $request : $this->decodeRequest());
        
        if (!$request && $this->getRouter()->getCurrentRoute()) {
            $controllerClass = $this->getRouter()->getCurrentRoute()->getControllerClass();
            $controller = new $controllerClass($this);
            $actionName = $this->getRouter()
                ->getCurrentRoute()
                ->getActionMethod();
        } else {
            // explicitly set request
            $toInstance = $this->hasAction(
                $this->getRequest()->getController(),
                $this->getRequest()->getAction(),
                $this->getRequest()->getMethod(),
                $this->getRequest()->getModule()
            );
            
            if (!$toInstance) {
                throw new \Ufw\Exception\BreakExecution("Not found", 404);
            }
            
            // let the init go
            $controllerClass = $toInstance['controller'];
            $controller = new $controllerClass($this);
            
            if (!$toInstance['action']) {
                throw new \Ufw\Exception\BreakExecution("Method Not Allowed", 405);
            }
            
            $actionName = $toInstance['action'];
        }
        
        $this->setCurrentController($controller);
        
        $obLevel = ob_get_level();
        
        try {
            $controller->dispatch($actionName);
        } catch (\Exception $e) {
            $curObLevel = ob_get_level();
            if ($curObLevel > $obLevel) {
                do {
                    ob_get_clean();
                    $curObLevel = ob_get_level();
                } while ($curObLevel > $obLevel);
            }
            throw $e;
        }
        
        return $this;
    }

    /**
     * Writes {@see BreakExecution} / uncaught exceptions to the path from {@see getExceptionLogFilePath()}
     * (default: <code>application/log/exceptions.txt</code>) with request context for debugging.
     */
    protected function logExceptionToFile(\Throwable $e, ?int $breakExecutionHttpStatus): void
    {
        $path = $this->getExceptionLogFilePath();
        if ($path === null || $path === '') {
            return;
        }

        $msg = "\n\n" . date('Y-m-d H:i:s') . "\n"
            . $this->formatRequestContextForLog($breakExecutionHttpStatus)
            . $e->getCode() . ' - ' . $e->getMessage() . "\n"
            . 'Line ' . $e->getLine() . ' @ ' . $e->getFile() . "\n"
            . $e->getTraceAsString();
        error_log($msg, 3, $path);
    }

    /**
     * Absolute filesystem path for {@see logExceptionToFile()}, or null to skip file logging entirely.
     *
     * Delegates to {@see Paths::exceptionLogFilePath()} using this instance's config. Override in a subclass
     * when the path must ignore config or be computed dynamically (per-tenant directories, …).
     *
     * @return string|null
     */
    protected function getExceptionLogFilePath()
    {
        $config = $this->getConfig();

        return Paths::exceptionLogFilePath(is_array($config) ? $config : null);
    }

    /**
     * Method, URL, route dispatch hints, client - for exception logs.
     *
     * @param int|null $breakExecutionHttpStatus Response status when {@see \Ufw\Exception\BreakExecution} was caught; null for generic exceptions
     */
    protected function formatRequestContextForLog(?int $breakExecutionHttpStatus): string
    {
        $lines = [];
        if ($breakExecutionHttpStatus !== null) {
            $lines[] = 'HTTP status: ' . $breakExecutionHttpStatus;
        } else {
            $lines[] = 'HTTP status: (uncaught exception; response will be reset to 404)';
        }

        $req = $this->getRequest();
        if ($req) {
            try {
                $lines[] = 'Request: ' . $req->getMethod() . ' ' . (string) $req->getUri();
            } catch (\Throwable $t) {
                $lines[] = 'Request: (could not build URI: ' . $t->getMessage() . ')';
            }

            $requestUri = $req->getServer('REQUEST_URI');
            if ($requestUri !== null && $requestUri !== '') {
                $lines[] = 'REQUEST_URI: ' . $requestUri;
            }

            $q = $req->getQueryParams();
            if (is_array($q) && $q !== []) {
                $lines[] = 'Query: ' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
            }

            $module = $req->getAttribute('module');
            $controller = $req->getAttribute('controller');
            $action = $req->getAttribute('action');
            if (($module !== null && $module !== '')
                || ($controller !== null && $controller !== '')
                || ($action !== null && $action !== '')) {
                $lines[] = sprintf(
                    'Dispatch: module=%s controller=%s action=%s',
                    $module !== null ? (string) $module : '',
                    $controller !== null ? (string) $controller : '',
                    $action !== null ? (string) $action : ''
                );
            }

            $ref = $req->getServer('HTTP_REFERER');
            if (is_string($ref) && $ref !== '') {
                $lines[] = 'Referer: ' . $ref;
            }

            $ip = $req->getServer('REMOTE_ADDR');
            if ($ip !== null && $ip !== '') {
                $lines[] = 'Client: ' . $ip;
            }

            $ua = $req->getServer('HTTP_USER_AGENT');
            if (is_string($ua) && $ua !== '') {
                $lines[] = 'User-Agent: ' . (strlen($ua) > 240 ? substr($ua, 0, 237) . '...' : $ua);
            }
        } else {
            foreach ($this->buildRequestContextLinesFromGlobals() as $line) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * When {@see decodeRequest()} throws before {@see setRequest()}, only PHP superglobals describe the hit URL.
     */
    protected function buildRequestContextLinesFromGlobals(): array
    {
        $lines = [];
        if (!isset($_SERVER) || !is_array($_SERVER)) {
            $lines[] = 'Request: (no Ufw request object; $_SERVER not available)';
            return $lines;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '?';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? (string) $_SERVER['SERVER_NAME'] : '');
        $https = $_SERVER['HTTPS'] ?? '';
        $scheme = ($https !== '' && strtolower((string) $https) !== 'off') ? 'https' : 'http';

        if ($host !== '' && $requestUri !== '') {
            $lines[] = 'Request: ' . $method . ' ' . $scheme . '://' . $host . $requestUri;
        } elseif ($requestUri !== '') {
            $lines[] = 'Request: ' . $method . ' ' . $requestUri;
        } else {
            $lines[] = 'Request: ' . $method . ' (REQUEST_URI empty)';
        }

        if ($requestUri !== '') {
            $lines[] = 'REQUEST_URI: ' . $requestUri;
        }

        if (!empty($_GET) && is_array($_GET)) {
            $lines[] = 'Query: ' . http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            $lines[] = 'Referer: ' . (string) $_SERVER['HTTP_REFERER'];
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $lines[] = 'Client: ' . (string) $_SERVER['REMOTE_ADDR'];
        }
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $ua = (string) $_SERVER['HTTP_USER_AGENT'];
            $lines[] = 'User-Agent: ' . (strlen($ua) > 240 ? substr($ua, 0, 237) . '...' : $ua);
        }

        $lines[] = 'Note: Ufw\\Request not set yet (exception in decodeRequest / before Application::setRequest).';

        return $lines;
    }

    protected function dispatchError($action = 'not-found', $controller = 'error')
    {
        $prev = $this->getRequest();
        $module = $prev ? $prev->getModule() : null;

        // Prefer the active Ufw request; if none or no PSR request (e.g. 404 before setRequest()), use globals so Accept is still visible.
        $forNegotiation = ($prev && $prev->getServerRequest())
            ? $prev->getServerRequest()
            : \Laminas\Diactoros\ServerRequestFactory::fromGlobals();
        if ($forNegotiation && Request::acceptPrefersApplicationJson($forNegotiation)) {
            $this->getResponse()->setFormat('json');
        }

        $seed = [
            'controller' => $controller,
            'action' => $action,
            'module' => $module,
        ];
        if ($prev) {
            $seed = array_merge($prev->getAttributes(), $seed);
        }

        return $this->dispatch(new Request($seed, [], $this->getDispatchDefaults($module), $prev));
    }

    protected function decodeRequest()
    {
        $route = $this->getRouter()->match();
        $routerRequest = $this->getRouter()->getRequest();
        if ($route) {
            return $routerRequest;
        }
        $this->setRequest($routerRequest);
        throw new \Ufw\Exception\BreakExecution("Not found", 404);
    }

    protected function hasAction($controllerName, $actionName, $httpMethod = null, $module = null)
    {
        if (!$controllerName) {
            $controllerName = $this->getDispatchDefaults($module)['controller'];
        }
        if ($module) {
            if (@$this->getConfig()['dispatch']['module'][$module]['controller_aliases'][$controllerName]) {
                $controllerName = $this->getConfig()['dispatch']['module'][$module]['controller_aliases'][$controllerName];
            }
        } else {
            if (@$this->getConfig()['dispatch']['controller_aliases'][$controllerName]) {
                $controllerName = $this->getConfig()['dispatch']['controller_aliases'][$controllerName];
            }
        }
        
        
        $controllerPhpFriendlyName = ucfirst(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($controllerName, ''));
        if ($module) {
            $modulePhpFriendlyName = ucfirst(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($module, ''));
            $controllerClass = "\\Controller\\" . $modulePhpFriendlyName . '\\' . $controllerPhpFriendlyName . '\\' . ucfirst(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($actionName, ''));
        } else {
            $controllerClass = "\\Controller\\" . $controllerPhpFriendlyName . '\\' . ucfirst(\Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($actionName, ''));
        }
        
        if (class_exists($controllerClass)) {
            $actionMethod = $httpMethod;
            $classKind = 'api';
        } else {
            if ($module) {
                $controllerClass = "\\Controller\\" . $modulePhpFriendlyName . '\\' . $controllerPhpFriendlyName;
            } else {
                $controllerClass = "\\Controller\\" . $controllerPhpFriendlyName;
            }
            $actionMethod = \Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($actionName, 'Action');
            $classKind = 'classic';
        }
        
        if (!$actionMethod && !$httpMethod) {
            return [
                'controller' => $controllerClass,
                'action' => null,
                'kind' => $classKind
            ];
        } else {
            try {
                $method = new \ReflectionMethod($controllerClass, $actionMethod);
                return $method->isPublic() ? [
                    'controller' => $controllerClass,
                    'action' => $actionMethod,
                    'kind' => $classKind
                ] : false;
            } catch (\Exception $e) {
                if (class_exists($controllerClass)) {
                    return [
                        'controller' => $controllerClass,
                        'kind' => $classKind,
                        'action' => null
                    ];
                }
                return false;
            }
        }
    }

    /**
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     *
     * @param request $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    public function getDispatchDefaults($module = null)
    {
        $global = $this->dispatchDefaults ?? [];

        if (!$module) {
            return $global;
        }

        // Same key as getDispatchControllers() / hasAction(): dispatch.module[$name]
        $dispatch = $this->getConfig()['dispatch'] ?? [];
        $mod = ($dispatch['module'] ?? [])[$module] ?? [];

        return $mod['defaults'] ?? $global;
    }

    public function setDispatchDefaults($dispatchDefaults)
    {
        $this->dispatchDefaults = $dispatchDefaults;
        return $this;
    }

    /**
     *
     * @return \Ufw\Controller\Base
     */
    public function getCurrentController()
    {
        return $this->currentController;
    }

    public function getScriptName($action)
    {
        $action = \Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName($action, '');
        return strtolower(preg_replace([
            '~(?<=(?:[A-Z]))([A-Z]+)([A-Z][A-z])~',
            '~(?<=(?:[a-z0-9]))([A-Z])~'
        ], [
            '\1-\2',
            '-\1'
        ], $action));
    }

    /**
     *
     * @param \Ufw\Controller\Base $currentController
     */
    public function setCurrentController($currentController)
    {
        $this->currentController = $currentController;
        return $this;
    }

    /**
     *
     * @return array
     */
    public function getDispatchControllers($module = null)
    {
        if (!$module) {
            return $this->dispatchControllers;
        }
        $dispatch = $this->getConfig()['dispatch'] ?? [];
        $mod = ($dispatch['module'] ?? [])[$module] ?? [];
        $list = $mod['controllers'] ?? [];
        if (@is_array($mod['controller_aliases']) && $mod['controller_aliases'] !== []) {
            $list = array_merge($list, array_keys($mod['controller_aliases']));
        }

        return $list;
    }

    /**
     *
     * @param array $dispatchControllers
     */
    public function setDispatchControllers($dispatchControllers)
    {
        $this->dispatchControllers = $dispatchControllers;
        if (@is_array($this->getConfig()['dispatch']['controller_aliases'])) {
            $this->dispatchControllers = array_merge($this->dispatchControllers, array_keys($this->getConfig()['dispatch']['controller_aliases']));
        }
        return $this;
    }

    /**
     *
     * @return response
     */
    public function getResponse()
    {
        if (!isset($this->response)) {
            $this->response = new Response();
        }
        return $this->response;
    }

    /**
     *
     * @return array
     */
    public function getConfig()
    {
        return static::$config;
    }

    /**
     *
     * @param array $config
     */
    public function setConfig(array $config)
    {
        static::$config = $config;
        return $this;
    }

    /**
     *
     * @return \Ufw\Router
     */
    public function getRouter()
    {
        if (!isset($this->router)) {
            $request = new Request([], [], []);
            $this->router = new Router($this, $request, $this->getConfig()['dispatch']['routes']);
        }
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
}
