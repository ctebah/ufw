<?php
namespace Ufw\Helper;

/**
 * Builds path and absolute URLs from named {@see \Ufw\Router} routes and the current HTTP scheme/host.
 */
class Url extends Base
{
    protected const SCHEME_HTTP = 'http';
    protected const SCHEME_HTTPS = 'https';

    /**
     * @param array<int|string, mixed> $params
     * @param bool $reset
     * @param bool $withQS
     * @param string|null $routeName
     * @param bool $encode
     * @return string
     */
    public function __invoke($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
    {
        return $this->url($params, $reset, $withQS, $routeName, $encode);
    }

    /**
     * @param array<int|string, mixed> $params
     * @param bool $reset
     * @param bool $withQS
     * @param string|null $routeName
     * @param bool $encode
     * @return string
     */
    public function url($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
    {
        $router = $this->getApplication()->getRouter();
        if ($routeName) {
            $route = $router->getRoute($routeName);
        } else {
            $route = $router->getCurrentRoute();
            if ($route === null) {
                $route = $router->getRoute('default');
            }
        }
        $stringParams = array();
        foreach ($params as $key => $value) {
            $stringParams[(string) $key] = $value;
        }
        return $route->buildUrl($stringParams, $reset, $withQS, $encode);
    }

    /**
     * @param array<int|string, mixed> $params
     * @param bool $reset
     * @param bool $withQS
     * @param string|null $routeName
     * @param bool $encode
     * @return string
     */
    public function full($params = array(), $reset = true, $withQS = false, $routeName = null, $encode = true)
    {
        return $this->domain() . $this->url($params, $reset, $withQS, $routeName, $encode);
    }
    
    /**
     * @return string
     */
    public function domain()
    {
        return $this->getScheme() . '://' . $this->getHttpHost();
    }

    /**
     * Retrieve a member of the $_SERVER superglobal
     *
     * If no $key is passed, returns the entire $_SERVER array.
     *
     * @param string|null $key
     * @param mixed $default
     *            Default value to use if key not found
     * @return mixed Returns null if key does not exist
     */
    protected function getServer($key = null, $default = null)
    {
        if (null === $key) {
            return $_SERVER;
        }
        
        return (isset($_SERVER[$key])) ? $_SERVER[$key] : $default;
    }

    /**
     * Get the request URI scheme
     *
     * @return string
     */
    protected function getScheme()
    {
        return ($this->getServer('HTTPS') == 'on') ? 'https' : 'http';
    }

    /**
     * Get the HTTP host.
     *
     * "Host" ":" host [ ":" port ] ; Section 3.2.2
     * Note the HTTP Host header is not the same as the URI host.
     * It includes the port while the URI host doesn't.
     *
     * @return string
     */
    protected function getHttpHost()
    {
        $host = $this->getServer('HTTP_HOST');
        if (is_string($host) && $host !== '') {
            return $host;
        }

        $scheme = $this->getScheme();
        $name = $this->getServer('SERVER_NAME');
        $port = $this->getServer('SERVER_PORT');

        if (!is_string($name)) {
            return '';
        }
        $portNum = is_int($port) ? $port : (is_string($port) && ctype_digit($port) ? (int) $port : null);
        if (($scheme == self::SCHEME_HTTP && $portNum === 80) || ($scheme == self::SCHEME_HTTPS && $portNum === 443) || $portNum === null) {
            return $name;
        }

        return $name . ':' . (string) $portNum;
    }
}
