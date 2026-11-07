<?php
namespace Ufw;

/**
 * Matches the request URI against configured routes: each {@see \Ufw\Route\Base} implements
 * {@see \Ufw\Route\Base::matchRequestPath()} (PCRE via {@see \Ufw\Route\RegEx}, exact path via {@see \Ufw\Route\Literal}), then {@see \Ufw\Route\Base::check()}.
 */
class Router
{

    /**
     *
     * @var \Ufw\Application
     */
    protected $application = null;

    /**
     *
     * @var \Ufw\Route\Base|null
     */
    protected $currentRoute = null;

    /**
     *
     * @var string|null
     */
    protected $currentRouteName = null;

    /**
     *
     * @var \Ufw\Request
     */
    protected $request = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected $routes = null;

    /**
     * @param array<string, array<string, mixed>> $routeDefinitions
     */
    public function __construct(\Ufw\Application $application, \Ufw\Request $request, array $routeDefinitions)
    {
        $this->setApplication($application)
            ->setRequest($request)
            ->setRoutes($routeDefinitions);
    }

    /**
     * @return \Ufw\Route\Base|null
     */
    public function match()
    {
        $rawUri = $this->getRequest()->getServer('REQUEST_URI');
        if (!is_string($rawUri) || $rawUri === '') {
            $rawUri = '/';
        }
        $stripped = preg_replace('~\?.*$~', '', $rawUri);
        $path = is_string($stripped) ? $stripped : $rawUri;
        $routes = $this->getRoutes();
        if (!is_array($routes)) {
            return null;
        }
        foreach ($routes as $routeName => $routeDef) {
            $route = $this->getRoute($routeName);
            $match = $route->matchRequestPath($path);
            if ($match === false) {
                continue;
            }
            if ($route->check($match)) {
                $this->setCurrentRouteName($routeName)->setCurrentRoute($route);
                $route->provideAttributes($this->getRequest(), $path, $match);
                break;
            }
            $this->mergeRegexNamedGroupsIntoRequest($match);
        }

        return $this->getCurrentRoute();
    }

    /**
     * When a pattern matches the path but {@see Route\Base::check()} fails, keep named capture groups (e.g. {@code lang})
     * on the router request so {@see Application::decodeRequest()} / {@see Application::dispatchError()} still see URL
     * context for 404 handling.
     *
     * @param array<int|string, string> $match PCRE match array from {@see preg_match()}
     */
    protected function mergeRegexNamedGroupsIntoRequest(array $match): void
    {
        $named = [];
        foreach ($match as $key => $value) {
            if (is_string($key) && $key !== '') {
                $named[$key] = $value;
            }
        }
        if ($named === []) {
            return;
        }
        $req = $this->getRequest();
        $req->setAttributes(array_merge($req->getAttributes(), $named));
    }

    /**
     *
     * @param string $routeName
     * @return \Ufw\Route\Base
     */
    public function getRoute($routeName)
    {
        $routes = $this->routes;
        if (!is_array($routes) || !isset($routes[$routeName])) {
            throw new \LogicException('Unknown route');
        }
        if (!isset($routes[$routeName]['route'])) {
            $type = $routes[$routeName]['type'] ?? null;
            if (!is_string($type) || !is_a($type, \Ufw\Route\Base::class, true)) {
                throw new \LogicException('Invalid route type');
            }
            /** @var class-string<\Ufw\Route\Base> $type */
            $routes[$routeName]['route'] = new $type($this, $routes[$routeName]);
            $this->routes = $routes;
        }
        $route = $routes[$routeName]['route'];
        if (!$route instanceof \Ufw\Route\Base) {
            throw new \LogicException('Invalid route instance');
        }

        return $route;
    }

    /**
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     *
     * @param array<string, array<string, mixed>> $routes
     * @return $this
     */
    public function setRoutes($routes)
    {
        $this->routes = $routes;
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
     * @return $this
     */
    public function setApplication($application)
    {
        $this->application = $application;
        return $this;
    }

    /**
     *
     * @return \Ufw\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     *
     * @param \Ufw\Request $request
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     *
     * @return \Ufw\Route\Base|null
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    /**
     *
     * @param \Ufw\Route\Base|null $currentRoute
     * @return $this
     */
    public function setCurrentRoute($currentRoute)
    {
        $this->currentRoute = $currentRoute;
        return $this;
    }

    /**
     *
     * @return string|null
     */
    public function getCurrentRouteName()
    {
        return $this->currentRouteName;
    }

    /**
     *
     * @param string|null $currentRouteName
     * @return $this
     */
    public function setCurrentRouteName($currentRouteName)
    {
        $this->currentRouteName = $currentRouteName;
        return $this;
    }
}
