<?php
namespace Ufw\Tests\Support;

use GuzzleHttp\Client as GuzzleClient;

class ApiClientRequesterProbe extends \Ufw\Component\ApiClient\Requester
{
    protected static $_instance;

    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new static();
        }

        return self::$_instance;
    }

    public static function resetInstance()
    {
        self::$_instance = null;
    }

    protected function getApiEndpointConfig()
    {
        return array('base_uri' => 'http://api.test');
    }

    public function injectHttpClient(GuzzleClient $client)
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Requester::class, 'httpClient');
        $prop->setAccessible(true);
        $prop->setValue($this, $client);

        return $this;
    }

    public function disableDebugLog()
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Requester::class, 'debuglog');
        $prop->setAccessible(true);
        $prop->setValue($this, false);
    }

    public function exposeGetApplication()
    {
        return $this->getApplication();
    }

    public function exposeGetLogger()
    {
        return $this->getLogger();
    }

    public function enableDebugLog()
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Requester::class, 'debuglog');
        $prop->setAccessible(true);
        $prop->setValue($this, true);

        return $this;
    }

    public function exposeGetHttpClient()
    {
        $method = new \ReflectionMethod(\Ufw\Component\ApiClient\Requester::class, 'getHttpClient');
        $method->setAccessible(true);

        return $method->invoke($this);
    }

    public function exposeCreateLoggingHandlerStack(array $formats)
    {
        $method = new \ReflectionMethod(\Ufw\Component\ApiClient\Requester::class, 'createLoggingHandlerStack');
        $method->setAccessible(true);

        return $method->invoke($this, $formats);
    }
}
