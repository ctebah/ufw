<?php
namespace Ufw\Component\ApiClient;


/**
 * Proxies HTTP verbs to {@see Requester}; optional API-error → {@see Exception}. Magic `getX()` loads sub-API classes; {@see __get()} exposes `helper`.
 *
 * @property-read \Ufw\Component\ApiClient\Helper $helper Via {@see getHelper()}.
 */
trait MagicTrait {

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

    public function __call($method, $params)
    {
        if (in_array($method, $this->requesterMethods)) {
            /**
             *
             * @var \Ufw\Component\ApiClient\Response $response
             */
            $response = call_user_func_array(array(
                $this->getClient()->getRequester(),
                $method
            ), $params);
            if ($this->getRaiseExceptionOnApiError() && $response->hasException()) {
                $e = new Exception($response->getMessage('error'), $response->getCode(), $response->getException());
                $e->setResponse($response);
                throw $e;
            }
            return $response;
        }
        
        /**
         * get sub object
         */
        if (preg_match('~^get[A-Z]~', $method)) {
            $className = (new \ReflectionClass(static::class))->getNamespaceName() . '\\' . substr($method, 3);
            return $className::getInstance($this->getClient());
        }
        
        $obj = $this->$method;
        if ($obj) {
            if (isset($params) && isset($params[0])) {
                if ($params[0] instanceof \Ufw\Form\Base) {
                    $form = array_shift($params);
                    $obj->setForm($form);
                } elseif (is_array($params[0])) {
                    $form = new FormAlike();
                    $form->populate($params[0]);
                    array_shift($params);
                    $obj->setForm($form);
                }
            }
            return call_user_func_array($obj, $params);
        }
        
        trigger_error("Method $method not found", E_USER_ERROR);
    }

    public function __get($name)
    {
        $out = null;
        switch ($name) {
            default:
                $out = call_user_func(array(
                    $this,
                    'get' . ucfirst($name)
                ));
                break;
        }
        return $out;
    }

    public function getHelper()
    {
        return Helper::getInstance();
    }

    /**
     *
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return \Ufw\Registry::getInstance()->get('application');
    }
}