<?php
namespace Ufw\Helper;

/**
 * Builds path and absolute URLs from named {@see \Ufw\Router} routes and the current HTTP scheme/host.
 */
class Url extends Base
{
    protected const SCHEME_HTTP = 'http';
    protected const SCHEME_HTTPS = 'https';

    public function __invoke($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
    {
        return $this->url($params, $reset, $withQS, $routeName, $encode);
    }

    public function url($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
    {
        /**
         * @var \Ufw\Route\Base $route
         */
        $route = $routeName ?
            $this->getApplication()->getRouter()->getRoute($routeName) :
            $this->getApplication()->getRouter()->getCurrentRoute();
        if (!$route) {
            $route = $this->getApplication()->getRouter()->getRoute('default');
        }
        return $route->buildUrl($params, $reset, $withQS, $encode);
    }

    public function full($params = array(), $reset = true, $withQS = false, $routeName = null, $encode = true)
    {
        return $this->domain() . $this->url($params, $reset, $withQS, $routeName, $encode);
    }
    
    public function domain()
    {
        return $this->getScheme() . '://' . $this->getHttpHost();
    }

    /**
     * Retrieve a member of the $_SERVER superglobal
     *
     * If no $key is passed, returns the entire $_SERVER array.
     *
     * @param string $key
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
        if (!empty($host)) {
            return $host;
        }
        
        $scheme = $this->getScheme();
        $name = $this->getServer('SERVER_NAME');
        $port = $this->getServer('SERVER_PORT');
        
        if (null === $name) {
            return '';
        } elseif (($scheme == self::SCHEME_HTTP && $port == 80) || ($scheme == self::SCHEME_HTTPS && $port == 443)) {
            return $name;
        } else {
            return $name . ':' . $port;
        }
    }
}
