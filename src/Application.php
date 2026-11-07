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
     * @var \Ufw\Controller\Base|null
     */
    protected $currentController = null;

    /**
     * @var array<string, mixed>|null
     */
    protected static $config = null;

    /**
     * @var array<string, mixed>
     */
    protected $dispatchDefaults = array();

    /**
     * @var list<string>
     */
    protected $dispatchControllers = array();

    /**
     * @var array<string, \Ufw\Helper\Base>
     */
    protected $helpersInstantiated = array();

    /**
     *
     * @var \Ufw\Request|null
     */
    protected $request = null;

    /**
     *
     * @var \Ufw\Response|null
     */
    protected $response = null;

    /**
     *
     * @var \Ufw\Router|null
     */
    protected $router;

    public function __construct()
    {
        Registry::getInstance()->set('application', $this);
        if (!$this->getConfig()) {
            $appConfig = include Paths::application() . '/config/app.php';
            if (!is_array($appConfig)) {
                throw new \RuntimeException('Application config must return an array.');
            }
            $this->setConfig($this->normalizeStringKeyedArray($appConfig));
        }

        $dispatch = $this->getDispatchConfig();
        if (isset($dispatch['controllers'])) {
            $this->setDispatchControllers($this->normalizeStringList($dispatch['controllers']));
        }
        if (isset($dispatch['defaults'])) {
            $this->setDispatchDefaults($this->normalizeStringKeyedArray($dispatch['defaults']));
        }
    }

    /**
     * @param \Ufw\Request|null $request
     * @return $this
     */
    public function run($request = null)
    {
        try {
            $this->dispatch($request);
        } catch (\Ufw\Exception\BreakExecution $e) {
            $statusCode = $this->getResponse()->getStatusCode();
            if ($this->shouldEmitAndTerminate($statusCode)) {
                $this->getResponse()->emit();
                $this->terminateAfterEmit();

                return $this;
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
     * Redirects (3xx) and 401 with an existing WWW-Authenticate header: emit only, no error dispatch.
     *
     * @param int $statusCode
     * @return bool
     */
    protected function shouldEmitAndTerminate($statusCode)
    {
        if ($statusCode >= 300 && $statusCode <= 310) {
            return true;
        }
        if ($statusCode === 401 && $this->getResponse()->hasHeader('WWW-Authenticate')) {
            return true;
        }

        return false;
    }

    /**
     * Ends the request after {@see Response::emit()}. Tests may define UFW_APPLICATION_SUPPRESS_EXIT.
     *
     * @return void
     */
    protected function terminateAfterEmit()
    {
        if (defined('UFW_APPLICATION_SUPPRESS_EXIT') && UFW_APPLICATION_SUPPRESS_EXIT) {
            return;
        }
        die();
    }

    /**
     *
     * @param string $name
     * @param \Ufw\Controller\Base|null $controller
     * @return \Ufw\Helper\Base|null
     */
    public function getHelper($name, $controller = null)
    {
        $helper = null;
        $name = ucfirst($name);
        if (isset($this->helpersInstantiated[$name])) {
            $helper = $this->helpersInstantiated[$name];
        } elseif (class_exists("\\Helper\\$name")) {
            $className = "\\Helper\\$name";
            if (is_subclass_of($className, \Ufw\Helper\Base::class)) {
                $this->helpersInstantiated[$name] = $helper = new $className();
            }
        } elseif (class_exists("\\Ufw\\Helper\\$name")) {
            $className = "\\Ufw\\Helper\\$name";
            if (is_subclass_of($className, \Ufw\Helper\Base::class)) {
                $this->helpersInstantiated[$name] = $helper = new $className();
            }
        }
        if ($helper !== null && $controller) {
            $helper->setController($controller);
        }
        return $helper;
    }

    /**
     * @param \Ufw\Request|null $request
     * @return $this
     */
    public function dispatch($request = null)
    {
        $this->setRequest($request ? $request : $this->decodeRequest());
        $activeRequest = $this->getRequest();
        if ($activeRequest === null) {
            throw new \RuntimeException('Dispatch requires a request.');
        }
        
        if (!$request && $this->getRouter()->getCurrentRoute()) {
            $controllerClass = $this->getRouter()->getCurrentRoute()->getControllerClass();
            $controller = new $controllerClass($this);
            $actionName = $this->getRouter()
                ->getCurrentRoute()
                ->getActionMethod();
        } else {
            // explicitly set request
            $toInstance = $this->hasAction(
                $activeRequest->getController(),
                $activeRequest->getAction(),
                $activeRequest->getMethod(),
                $activeRequest->getModule()
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

        if (!is_string($actionName) || $actionName === '') {
            throw new \Ufw\Exception\BreakExecution('Method Not Allowed', 405);
        }
        
        if (!$controller instanceof \Ufw\Controller\Base) {
            throw new \RuntimeException('Controller must extend \\Ufw\\Controller\\Base.');
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
            if (is_string($requestUri) && $requestUri !== '') {
                $lines[] = 'REQUEST_URI: ' . $requestUri;
            }

            $q = $req->getQueryParams();
            if ($q !== []) {
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
                    self::scalarToString($module),
                    self::scalarToString($controller),
                    self::scalarToString($action)
                );
            }

            $ref = $req->getServer('HTTP_REFERER');
            if (is_string($ref) && $ref !== '') {
                $lines[] = 'Referer: ' . $ref;
            }

            $ip = $req->getServer('REMOTE_ADDR');
            if (is_string($ip) && $ip !== '') {
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
    /**
     * @return list<string>
     */
    protected function buildRequestContextLinesFromGlobals(): array
    {
        $lines = [];
        $server = $_SERVER;

        $method = '?';
        if (isset($server['REQUEST_METHOD']) && is_string($server['REQUEST_METHOD'])) {
            $method = $server['REQUEST_METHOD'];
        }
        $requestUri = isset($server['REQUEST_URI']) && is_string($server['REQUEST_URI']) ? $server['REQUEST_URI'] : '';
        $host = '';
        if (isset($server['HTTP_HOST']) && is_string($server['HTTP_HOST'])) {
            $host = $server['HTTP_HOST'];
        } elseif (isset($server['SERVER_NAME']) && is_string($server['SERVER_NAME'])) {
            $host = $server['SERVER_NAME'];
        }
        $https = $server['HTTPS'] ?? '';
        $scheme = (is_string($https) && $https !== '' && strtolower($https) !== 'off') ? 'https' : 'http';

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

        if ($_GET !== []) {
            $lines[] = 'Query: ' . http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);
        }

        if (!empty($server['HTTP_REFERER']) && is_string($server['HTTP_REFERER'])) {
            $lines[] = 'Referer: ' . $server['HTTP_REFERER'];
        }
        if (!empty($server['REMOTE_ADDR']) && is_string($server['REMOTE_ADDR'])) {
            $lines[] = 'Client: ' . $server['REMOTE_ADDR'];
        }
        if (!empty($server['HTTP_USER_AGENT']) && is_string($server['HTTP_USER_AGENT'])) {
            $ua = $server['HTTP_USER_AGENT'];
            $lines[] = 'User-Agent: ' . (strlen($ua) > 240 ? substr($ua, 0, 237) . '...' : $ua);
        }

        $lines[] = 'Note: Ufw\\Request not set yet (exception in decodeRequest / before Application::setRequest).';

        return $lines;
    }

    /**
     * @param string $action
     * @param string $controller
     * @return $this
     */
    protected function dispatchError($action = 'not-found', $controller = 'error')
    {
        $prev = $this->getRequest();
        $module = $prev !== null ? $prev->getModule() : null;

        $forNegotiation = \Laminas\Diactoros\ServerRequestFactory::fromGlobals();
        if ($prev !== null) {
            $sr = $prev->getServerRequest();
            if ($sr !== null) {
                $forNegotiation = $sr;
            }
        }
        if (Request::acceptPrefersApplicationJson($forNegotiation)) {
            $this->getResponse()->setFormat('json');
        }

        $seed = [
            'controller' => $controller,
            'action' => $action,
            'module' => $module,
        ];
        if ($prev !== null) {
            $seed = array_merge($prev->getAttributes(), $seed);
        }

        return $this->dispatch(new Request($seed, [], $this->getDispatchDefaults($module), $prev));
    }

    /**
     * @return \Ufw\Request
     */
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

    /**
     * @param string|null $controllerName
     * @param string|null $actionName
     * @param string|null $httpMethod
     * @param string|null $module
     * @return array{controller: string, action: string|null, kind: string}|false
     */
    protected function hasAction($controllerName, $actionName, $httpMethod = null, $module = null)
    {
        $controllerName = (string) ($controllerName ?? '');
        $actionName = (string) ($actionName ?? '');
        if ($controllerName === '') {
            $controllerName = self::scalarToString($this->getDispatchDefaults($module)['controller'] ?? null);
        }
        $dispatch = $this->getDispatchConfig();
        if ($module) {
            $moduleDispatch = $this->getModuleDispatchConfig($dispatch, $module);
            $aliases = $moduleDispatch['controller_aliases'] ?? [];
            if (is_array($aliases) && isset($aliases[$controllerName]) && is_string($aliases[$controllerName])) {
                $controllerName = $aliases[$controllerName];
            }
        } else {
            $aliases = $dispatch['controller_aliases'] ?? [];
            if (is_array($aliases) && isset($aliases[$controllerName]) && is_string($aliases[$controllerName])) {
                $controllerName = $aliases[$controllerName];
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
            if (!is_string($actionMethod) || $actionMethod === '') {
                return false;
            }
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
     * @return \Ufw\Request|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param \Ufw\Request|null $request
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @param string|null $module
     * @return array<string, mixed>
     */
    public function getDispatchDefaults($module = null)
    {
        $global = $this->dispatchDefaults;

        if (!$module) {
            return $global;
        }

        $mod = $this->getModuleDispatchConfig($this->getDispatchConfig(), $module);
        $defaults = $mod['defaults'] ?? $global;

        return is_array($defaults) ? $this->normalizeStringKeyedArray($defaults) : $global;
    }

    /**
     * @param array<string, mixed> $dispatchDefaults
     * @return $this
     */
    public function setDispatchDefaults($dispatchDefaults)
    {
        $this->dispatchDefaults = $dispatchDefaults;
        return $this;
    }

    /**
     * @return \Ufw\Controller\Base|null
     */
    public function getCurrentController()
    {
        return $this->currentController;
    }

    /**
     * @param string $action
     * @return string
     */
    public function getScriptName($action)
    {
        $action = \Ufw\Utils\Strings::getInstance()->formatToPhpFriendlyName((string) $action, '');
        return strtolower((string) preg_replace([
            '~(?<=(?:[A-Z]))([A-Z]+)([A-Z][A-z])~',
            '~(?<=(?:[a-z0-9]))([A-Z])~'
        ], [
            '\1-\2',
            '-\1'
        ], (string) $action));
    }

    /**
     * @param \Ufw\Controller\Base $currentController
     * @return $this
     */
    public function setCurrentController($currentController)
    {
        $this->currentController = $currentController;
        return $this;
    }

    /**
     * @param string|null $module
     * @return list<string>
     */
    public function getDispatchControllers($module = null)
    {
        if (!$module) {
            return $this->dispatchControllers;
        }
        $mod = $this->getModuleDispatchConfig($this->getDispatchConfig(), $module);
        $list = $this->normalizeStringList($mod['controllers'] ?? []);
        $aliases = $mod['controller_aliases'] ?? [];
        if (is_array($aliases) && $aliases !== []) {
            $list = array_merge($list, array_keys($aliases));
        }

        return $list;
    }

    /**
     * @param list<string> $dispatchControllers
     * @return $this
     */
    public function setDispatchControllers($dispatchControllers)
    {
        $this->dispatchControllers = $dispatchControllers;
        $dispatch = $this->getDispatchConfig();
        $aliases = $dispatch['controller_aliases'] ?? null;
        if (is_array($aliases)) {
            $this->dispatchControllers = array_merge($this->dispatchControllers, array_keys($aliases));
        }
        return $this;
    }

    /**
     * @return \Ufw\Response
     */
    public function getResponse()
    {
        if ($this->response === null) {
            $this->response = new Response();
        }
        return $this->response;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getConfig()
    {
        return static::$config;
    }

    /**
     * @param array<string, mixed> $config
     * @return $this
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
        if ($this->router === null) {
            $request = new Request([], [], []);
            $dispatch = $this->getDispatchConfig();
            $routes = $dispatch['routes'] ?? [];
            if (!is_array($routes)) {
                $routes = [];
            }
            $routeDefinitions = [];
            foreach ($routes as $routeName => $routeDef) {
                if (is_string($routeName) && is_array($routeDef)) {
                    $routeDefinitions[$routeName] = $this->normalizeStringKeyedArray($routeDef);
                }
            }
            $this->router = new Router($this, $request, $routeDefinitions);
        }
        return $this->router;
    }

    /**
     * @param \Ufw\Router $router
     * @return $this
     */
    public function setRouter($router)
    {
        $this->router = $router;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDispatchConfig()
    {
        $config = $this->getConfig() ?? [];
        $dispatch = $config['dispatch'] ?? [];

        return is_array($dispatch) ? $this->normalizeStringKeyedArray($dispatch) : [];
    }

    /**
     * @param array<string, mixed> $dispatch
     * @param string $module
     * @return array<string, mixed>
     */
    protected function getModuleDispatchConfig(array $dispatch, $module)
    {
        $modules = $dispatch['module'] ?? [];
        if (!is_array($modules)) {
            return [];
        }
        $mod = $modules[$module] ?? [];

        return is_array($mod) ? $this->normalizeStringKeyedArray($mod) : [];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    protected function normalizeStringList($value)
    {
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected static function scalarToString($value)
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return '';
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function normalizeStringKeyedArray($value)
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key => $val) {
            if (is_string($key)) {
                $out[$key] = $val;
            }
        }

        return $out;
    }
}
