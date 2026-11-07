<?php
namespace Ufw\Component\ApiClient;

/**
 * Proxies HTTP verbs to {@see Requester}; optional API-error → {@see Exception}. Magic `getX()` loads sub-API classes; {@see __get()} exposes `helper`.
 *
 * @property-read \Ufw\Component\ApiClient\Helper $helper Via {@see getHelper()}.
 */
trait MagicTrait {

    /**
     * @var list<string>
     */
    protected $requesterMethods = array(
        'delete',
        'get',
        'head',
        'patch',
        'post',
        'put',
        'raw',
        'getLastResponse'
    );

    /**
     * @param string $method
     * @param array<int, mixed> $params
     * @return mixed
     */
    public function __call($method, $params)
    {
        if (in_array($method, $this->requesterMethods, true)) {
            $client = $this->getClient();
            if ($client === null) {
                trigger_error("ApiClient not configured", E_USER_ERROR);
            }
            $requester = $client->getRequester();
            if (!method_exists($requester, $method)) {
                trigger_error("Method $method not found", E_USER_ERROR);
            }
            $response = $requester->$method(...$params);
            if (!$response instanceof Response) {
                trigger_error("Unexpected response from Requester", E_USER_ERROR);
            }
            if ($this->getRaiseExceptionOnApiError() && $response->hasException()) {
                $errorMsg = $response->getMessage('error');
                $exceptionMsg = $this->normalizeExceptionMessage($errorMsg);
                $e = new Exception($exceptionMsg, $response->getCode(), $response->getException());
                $e->setResponse($response);
                throw $e;
            }
            return $response;
        }
        
        if (preg_match('~^get[A-Z]~', $method)) {
            $className = (new \ReflectionClass(static::class))->getNamespaceName() . '\\' . substr($method, 3);
            if (!class_exists($className) || !is_subclass_of($className, Base::class)) {
                trigger_error("Method $method not found", E_USER_ERROR);
            }
            /** @var class-string<Base> $className */
            return $className::getInstance($this->getClient());
        }
        
        /**
         * Sub-resource entry: $client->predmet($data) reads $this->predmet via {@see __get()} → getPredmet().
         */
        $obj = $this->$method;
        if (!$obj) {
            trigger_error("Method $method not found", E_USER_ERROR);
        }
        $formTarget = null;
        if ($obj instanceof Base) {
            $formTarget = $obj;
        } elseif (is_array($obj)) {
            $first = reset($obj);
            if ($first instanceof Base) {
                $formTarget = $first;
            }
        }
        if ($formTarget !== null && isset($params[0])) {
            $firstParam = $params[0];
            if ($firstParam instanceof \Ufw\Form\Base) {
                array_shift($params);
                $formTarget->setForm($firstParam);
            } elseif (is_array($firstParam)) {
                $form = new FormAlike();
                $form->populate(Helper::getInstance()->normalizeStringKeyedArray($firstParam));
                array_shift($params);
                $formTarget->setForm($form);
            }
        }
        if (!is_callable($obj)) {
            trigger_error("Method $method not found", E_USER_ERROR);
        }

        return call_user_func_array($obj, $params);
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
     * @return \Ufw\Component\ApiClient\Helper
     */
    public function getHelper()
    {
        return Helper::getInstance();
    }

    /**
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        $app = \Ufw\Registry::getInstance()->get('application');
        if (!$app instanceof \Ufw\Application) {
            throw new \RuntimeException('Application is not registered.');
        }

        return $app;
    }

    /**
     * @param mixed $errorMsg
     * @return string|list<string>|null
     */
    protected function normalizeExceptionMessage($errorMsg)
    {
        if (is_string($errorMsg)) {
            return $errorMsg;
        }
        if (is_array($errorMsg)) {
            $list = array();
            foreach ($errorMsg as $item) {
                if (is_string($item)) {
                    $list[] = $item;
                }
            }

            return $list !== array() ? $list : null;
        }

        return null;
    }

    /**
     * @return \Ufw\Component\ApiClient\Client|null
     */
    abstract public function getClient();

    /**
     * @return bool
     */
    abstract public function getRaiseExceptionOnApiError();
}
