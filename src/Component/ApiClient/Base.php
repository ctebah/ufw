<?php

namespace Ufw\Component\ApiClient;

/**
 * HTTP API façade: {@see $fieldsMap} / form wiring, {@see MagicTrait} verbs, and sub-resource getters (`getFoo()` → `Foo::getInstance`).
 * Concrete APIs usually extend {@see \Ufw\Component\ApiClient\Base\R}, {@see \Ufw\Component\ApiClient\Base\W}, {@see \Ufw\Component\ApiClient\Base\RW}, …
 *
 * @property-read \Ufw\Component\ApiClient\Helper $helper Via {@see MagicTrait::getHelper()}.
 * @property object|null $_user Application API subclasses may set a user object with {@see isLoggedOn()}.
 *
 * @method \Ufw\Component\ApiClient\Response delete(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response get(string $url, array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response head(string $url, array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response patch(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response post(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response put(string $url, array<string, mixed> $body = array(), array<string, mixed> $headers = array())
 * @method \Ufw\Component\ApiClient\Response getLastResponse()
 *
 * @phpstan-consistent-constructor
 */
abstract class Base
{
    use \Ufw\Component\ApiClient\MagicTrait;

    /**
     * @return $this
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * @var array<class-string<static>, static>
     */
    protected static $_instance = array();

    /**
     * @var \Ufw\Component\ApiClient\Client|null
     */
    protected $client = null;

    /**
     * API field name => form field name
     *
     * @var array<string, mixed>
     */
    protected $fieldsMap = array();

    /**
     * @var \Ufw\Form\Base|null
     */
    protected $form;

    /**
     * @var bool
     */
    protected $raiseExceptionOnApiError = true;

    /**
     * @var \Ufw\Component\ApiClient\Response|null
     */
    protected $response = null;

    /**
     * @var object|null
     */
    protected $_user = null;

    protected function __construct()
    {
    }

    /**
     * @param \Ufw\Component\ApiClient\Client|null $client
     * @return static
     */
    public static function getInstance(\Ufw\Component\ApiClient\Client $client = null)
    {
        $className = get_called_class();
        if (!isset(self::$_instance[$className])) {
            $instance = new $className();
            if ($client !== null) {
                $instance->setClient($client);
            }
            self::$_instance[$className] = $instance;
        } elseif ($client !== null) {
            self::$_instance[$className]->setClient($client);
        }
        return self::$_instance[$className];
    }

    /**
     * @return bool
     */
    protected function isLoggedOn()
    {
        return $this->_user !== null
            && method_exists($this->_user, 'isLoggedOn')
            && $this->_user->isLoggedOn();
    }

    /**
     * @param callable(): (\Ufw\Component\ApiClient\Response|null|void) $func
     * @return $this
     */
    protected function _run($func)
    {
        $form = $this->getForm();
        if ($form !== null && !$form->isValid()) {
            throw new \Ufw\Component\ApiClient\Exception("Invalid data");
        }
        try {
            $response = $func();
            if ($response instanceof Response) {
                $this->setResponse($response);
            }
        } catch (\Ufw\Component\ApiClient\Exception $e) {
            $skipFlashMessengerErrors = false;
            $response = $e->getResponse();
            if ($response !== null && $response->isClientException() && $form !== null) {
                $errors = $response->getMessage('errors');
                if (is_array($errors)) {
                    $this->setFormErrors($this->getHelper()->normalizeErrorTree($errors));
                    $skipFlashMessengerErrors = $e->getCode() == 422;
                }
            }
            if (!$skipFlashMessengerErrors) {
                $flash = $this->getApplication()->getHelper('flashMessenger');
                if ($flash instanceof \Ufw\Helper\FlashMessenger) {
                    $flash->addMessage($e->getMessages(), 'error');
                }
            }
            throw $e;
        }
        return $this;
    }

    /**
     * @param array<string, mixed> $errors
     * @return $this
     */
    protected function setFormErrors($errors)
    {
        $form = $this->getForm();

        if ($form === null) {
            return $this;
        }
        $map = $this->getFieldsMap();
        $helper = $this->getHelper();
        $errPaths = $helper->getErrorPaths($map, $errors);
        foreach ($errPaths as $errPath) {
            $mkey = $helper->getMappedKey($map, $errPath);
            $el = $helper->getElement($mkey, $form);
            if (!$el instanceof \Ufw\Form\Element\Base) {
                continue;
            }
            $messages = $errors;
            foreach ($errPath as $pathKey) {
                if (!is_array($messages) || !array_key_exists($pathKey, $messages)) {
                    $messages = null;
                    break;
                }
                $messages = $messages[$pathKey];
            }
            $errorLines = $this->normalizeFieldErrorMessages($messages);
            if ($errorLines !== array()) {
                $el->clearErrors();
                $el->addErrors($errorLines);
            }
        }
        return $this;
    }

    /**
     * @param mixed $messages
     * @return list<string>
     */
    protected function normalizeFieldErrorMessages($messages)
    {
        if (is_string($messages)) {
            return $messages !== '' ? array($messages) : array();
        }
        if (!is_array($messages)) {
            return array();
        }
        $out = array();
        foreach ($messages as $message) {
            if (is_string($message) && $message !== '') {
                $out[] = $message;
            }
        }

        return $out;
    }

    /**
     * @return \Ufw\Component\ApiClient\Client|null
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param \Ufw\Component\ApiClient\Client $client
     * @return $this
     */
    public function setClient(\Ufw\Component\ApiClient\Client $client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return \Ufw\Form\Base|null
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param \Ufw\Form\Base|null $form
     * @return $this
     */
    public function setForm(\Ufw\Form\Base $form = null)
    {
        $this->form = $form;
        return $this;
    }

    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param \Ufw\Component\ApiClient\Response|null $response
     * @return $this
     */
    public function setResponse(\Ufw\Component\ApiClient\Response $response = null)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRaiseExceptionOnApiError()
    {
        return $this->raiseExceptionOnApiError;
    }

    /**
     * @param bool $raiseExceptionOnApiError
     * @return $this
     */
    public function setRaiseExceptionOnApiError($raiseExceptionOnApiError)
    {
        $this->raiseExceptionOnApiError = $raiseExceptionOnApiError;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFieldsMap()
    {
        return $this->fieldsMap;
    }

    /**
     * @param array<string, mixed> $fieldsMap
     * @return $this
     */
    public function setFieldsMap(array $fieldsMap)
    {
        $this->fieldsMap = $fieldsMap;
        return $this;
    }
}
