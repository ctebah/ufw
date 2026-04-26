<?php

namespace Ufw\Component\ApiClient;

/**
 * HTTP API façade: {@see $fieldsMap} / form wiring, {@see MagicTrait} verbs, and sub-resource getters (`getFoo()` → `Foo::getInstance`).
 * Concrete APIs usually extend {@see \Ufw\Component\ApiClient\Base\R}, {@see \Ufw\Component\ApiClient\Base\W}, {@see \Ufw\Component\ApiClient\Base\RW}, …
 *
 * @method \Ufw\Component\ApiClient\Response delete(string $url, array $body = array(), array $headers = array())
 * @method \Ufw\Component\ApiClient\Response get(string $url, array $headers = array())
 * @method \Ufw\Component\ApiClient\Response head(string $url, array $headers = array())
 * @method \Ufw\Component\ApiClient\Response patch(string $url, array $body = array(), array $headers = array())
 * @method \Ufw\Component\ApiClient\Response post(string $url, array $body = array(), array $headers = array())
 * @method \Ufw\Component\ApiClient\Response put(string $url, array $body = array(), array $headers = array())
 * @method \Ufw\Component\ApiClient\Response getLastResponse()
 */
abstract class Base
{
    use \Ufw\Component\ApiClient\MagicTrait;

    public function __invoke()
    {
        return $this;
    }

    protected static $_instance = array();

    /**
     *
     * @var \Ufw\Component\ApiClient\Client
     */
    protected $client = null;

    /**
     * $key - api field name
     * $value - form field name
     *
     * @var array
     */
    protected $fieldsMap = array();

    /**
     *
     * @var \Ufw\Form\Base
     */
    protected $form;

    /**
     *
     * @var boolean
     */
    protected $raiseExceptionOnApiError = true;

    /**
     *
     * @var \Ufw\Component\ApiClient\Response
     */
    protected $response = null;

    protected function __construct()
    {
    }

    /**
     * Singleton model use get_called_class late static binding
     * function to determine name of the called class.
     *
     * @return \Ufw\Component\ApiClient\Base
     */
    public static function getInstance(\Ufw\Component\ApiClient\Client $client = null)
    {
        $className = get_called_class();
        if (!isset(self::$_instance[$className])) {
            self::$_instance[$className] = (new $className())->setClient($client);
        }
        return self::$_instance[$className];
    }

    protected function isLoggedOn()
    {
        return $this->_user->isLoggedOn();
    }

    protected function _run($func)
    {
        if ($this->getForm() && !$this->getForm()->isValid()) {
            throw new \Ufw\Component\ApiClient\Exception("Invalid data");
        }
        try {
            $response = $func();
            $this->setResponse($response);
        } catch (\Ufw\Component\ApiClient\Exception $e) {
            $skipFlashMessengerErrors = false;
            if ($e->getResponse()->isClientException() && $this->getForm()) {
                if ($errors = $e->getResponse()->getMessage('errors')) {
                    $this->setFormErrors($errors);
                    $skipFlashMessengerErrors = $e->getCode() == 422;
                }
            }
            if (!$skipFlashMessengerErrors) {
                $this->getApplication()
                    ->getHelper('flashMessenger')
                    ->addMessage($e->getMessages(), 'error');
            }
            throw $e;
        }
        return $this;
    }

    protected function setFormErrors($errors)
    {
        $form = $this->getForm();

        if (!$form) {
            return $this;
        }
        $map = $this->getFieldsMap();
        $errPaths = $this->helper->getErrorPaths($map, $errors);
        foreach ($errPaths as $errPath) {
            $mkey = $this->helper->getMappedKey($map, $errPath);
            $el = $this->helper->getElement($mkey, $form);
            if (!$el) {
                continue;
            }
            $messages = $errors;
            foreach ($errPath as $key) {
                $messages = isset($messages[$key]) ? $messages[$key] : null;
            }
            if ($messages) {
                $el->setErrors($messages);
            }
        }
        return $this;
    }

    /**
     *
     * @return \Ufw\Component\ApiClient\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     *
     * @param \Ufw\Component\ApiClient\Client $client
     */
    public function setClient(\Ufw\Component\ApiClient\Client $client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     *
     * @return \Ufw\Form\Base
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     *
     * @param \Ufw\Form\Base $form
     */
    public function setForm(\Ufw\Form\Base $form)
    {
        $this->form = $form;
        return $this;
    }

    /**
     *
     * @return \Ufw\Component\ApiClient\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     *
     * @param \Ufw\Component\ApiClient\Response $response
     */
    public function setResponse(\Ufw\Component\ApiClient\Response $response = null)
    {
        $this->response = $response;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getRaiseExceptionOnApiError()
    {
        return $this->raiseExceptionOnApiError;
    }

    /**
     *
     * @param boolean $raiseExceptionOnApiError
     */
    public function setRaiseExceptionOnApiError($raiseExceptionOnApiError)
    {
        $this->raiseExceptionOnApiError = $raiseExceptionOnApiError;
        return $this;
    }

    /**
     *
     * @return array
     */
    public function getFieldsMap()
    {
        return $this->fieldsMap;
    }

    /**
     *
     * @param array $fieldsMap
     */
    public function setFieldsMap(array $fieldsMap)
    {
        $this->fieldsMap = $fieldsMap;
        return $this;
    }
}
