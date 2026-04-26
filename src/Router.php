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
     * @var \Ufw\Route\Base
     */
    protected $currentRoute = null;

    /**
     *
     * @var string
     */
    protected $currentRouteName = null;

    /**
     *
     * @var \Ufw\Request
     */
    protected $request = null;

    protected $routes = null;

    public function __construct(\Ufw\Application $application, \Ufw\Request $request, array $routeDefinitions)
    {
        $this->setApplication($application)
            ->setRequest($request)
            ->setRoutes($routeDefinitions);
    }

    public function match()
    {
        $rawUri = $this->getRequest()->getServer('REQUEST_URI');
        if ($rawUri === null || $rawUri === '') {
            $rawUri = '/';
        }
        $path = preg_replace('~\?.*$~', '', (string) $rawUri);
        foreach ($this->getRoutes() as $routeName => $routeDef) {
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
        if (!isset($this->getRoutes()[$routeName]['route'])) {
            $class = $this->routes[$routeName]['type'];
            $this->routes[$routeName]['route'] = new $class($this, $this->getRoutes()[$routeName]);
        }
        return $this->getRoutes()[$routeName]['route'];
    }

    /**
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     *
     * @param array $routes
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
     */
    public function setRequest($request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     *
     * @return \Ufw\Route\Base $currentRoute
     */
    public function getCurrentRoute()
    {
        return $this->currentRoute;
    }

    /**
     *
     * @param \Ufw\Route\Base $currentRoute
     */
    public function setCurrentRoute($currentRoute)
    {
        $this->currentRoute = $currentRoute;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getCurrentRouteName()
    {
        return $this->currentRouteName;
    }

    /**
     *
     * @param string $currentRouteName
     */
    public function setCurrentRouteName($currentRouteName)
    {
        $this->currentRouteName = $currentRouteName;
        return $this;
    }
}
