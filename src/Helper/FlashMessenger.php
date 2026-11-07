<?php
namespace Ufw\Helper;

/**
 * Session flash messages keyed by namespace (`info`, `error`, `warning`). On first construction per request,
 * messages from the previous request are copied for reading; new {@see addMessage} calls target the next request
 * unless read in the same request.
 */
class FlashMessenger extends Base
{

    const NAMESPACE_INFO = 'info';

    const NAMESPACE_ERROR = 'error';

    const NAMESPACE_WARNING = 'warning';

    /**
     * $_messages - Messages from previous request
     *
     * @var array<string, list<string>>
     */
    protected static $_messages = array();

    /**
     * @var int
     */
    protected $off = 0;

    /**
     *
     * @var string Instance namespace, default is 'info'
     */
    protected $namespace = self::NAMESPACE_INFO;

    /**
     * $_session - storage object
     *
     * @var array<string, list<string>>|null
     */
    protected static $_session = null;

    /**
     * __construct() - Instance constructor, needed to get iterators, etc
     *
     * @return void
     */
    public function __construct()
    {
        if (!isset(self::$_session)) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $previous = isset($_SESSION['ufw__fM']) && is_array($_SESSION['ufw__fM']) ? $_SESSION['ufw__fM'] : array();
            foreach ($previous as $namespace => $messages) {
                if (!is_string($namespace)) {
                    continue;
                }
                self::$_messages[$namespace] = self::normalizeMessageList($messages);
            }
            self::$_session = array();
            $_SESSION['ufw__fM'] = &self::$_session;
        }
    }

    /**
     * @param mixed $messages
     * @return list<string>
     */
    protected static function normalizeMessageList($messages)
    {
        if (!is_array($messages)) {
            return array();
        }
        $out = array();
        foreach ($messages as $msg) {
            if (is_string($msg)) {
                $out[] = $msg;
            }
        }

        return $out;
    }

    /**
     * @return $this
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * @param string|list<string>|null $message
     * @param string|null $namespace
     * @param bool $translatable
     * @return $this
     */
    public function addMessage($message, $namespace = null, $translatable = true)
    {
        if ($this->isOff() || $message === null) {
            return $this;
        }

        if (self::$_session === null) {
            self::$_session = array();
        }
        
        foreach ((array) $message as $msg) {
            if (!is_string($namespace) || $namespace == '') {
                $namespace = $this->getNamespace();
            }
            if (!in_array($msg, array_merge($this->getMessages($namespace), $this->getCurrentMessages($namespace)))) {
                if (!array_key_exists($namespace, self::$_session)) {
                    self::$_session[$namespace] = array();
                }
                
                self::$_session[$namespace][] = $translatable ? $this->translate((string) $msg) : (string) $msg;
            }
        }
        
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getErrors()
    {
        $out = array_merge($this->getCurrentMessages(self::NAMESPACE_ERROR), $this->getMessages(self::NAMESPACE_ERROR));
        $this->clearCurrentMessages(self::NAMESPACE_ERROR);
        return $out;
    }

    /**
     * @return list<string>
     */
    public function getInfos()
    {
        $out = array_merge($this->getCurrentMessages(self::NAMESPACE_INFO), $this->getMessages(self::NAMESPACE_INFO));
        $this->clearCurrentMessages(self::NAMESPACE_INFO);
        return $out;
    }

    /**
     * @return list<string>
     */
    public function getWarnings()
    {
        $out = array_merge($this->getCurrentMessages(self::NAMESPACE_WARNING), $this->getMessages(self::NAMESPACE_WARNING));
        $this->clearCurrentMessages(self::NAMESPACE_WARNING);
        return $out;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return $this->hasMessages(self::NAMESPACE_ERROR) || $this->hasCurrentMessages(self::NAMESPACE_ERROR);
    }

    /**
     * @return bool
     */
    public function hasInfos()
    {
        return $this->hasMessages(self::NAMESPACE_INFO) || $this->hasCurrentMessages(self::NAMESPACE_INFO);
    }

    /**
     * @return bool
     */
    public function hasWarnings()
    {
        return $this->hasMessages(self::NAMESPACE_WARNING) || $this->hasCurrentMessages(self::NAMESPACE_WARNING);
    }

    /**
     * @return bool
     */
    public function isOff()
    {
        return !!$this->off;
    }

    /**
     * @return $this
     */
    public function off()
    {
        $this->off++;
        return $this;
    }

    /**
     * @param bool $force
     * @return $this
     */
    public function on($force = false)
    {
        if ($force)
            $this->off = 0;
        else
            $this->off--;
        return $this;
    }

    /**
     * setNamespace() - change the namespace messages are added to, useful for
     * per action controller messaging between requests
     *
     * @param string $namespace            
     * @return $this
     */
    public function setNamespace($namespace = 'info')
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * getNamespace() - return the current namepsace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * getCurrentMessages() - get messages that have been added to the current
     * namespace within this request
     *
     * @param string|null $namespace
     * @return list<string>
     */
    public function getCurrentMessages($namespace = null)
    {
        if (!is_string($namespace) || $namespace == '') {
            $namespace = $this->getNamespace();
        }
        
        if ($this->hasCurrentMessages($namespace) && self::$_session !== null) {
            return self::$_session[$namespace];
        }
        
        return array();
    }

    /**
     * getMessages() - Get messages from a specific namespace
     *
     * @param string|null $namespace
     * @return list<string>
     */
    public function getMessages($namespace = null)
    {
        if (!is_string($namespace) || $namespace == '') {
            $namespace = $this->getNamespace();
        }
        
        if ($this->hasMessages($namespace)) {
            return self::$_messages[$namespace];
        }
        
        return array();
    }

    /**
     * clear messages from the current request & current namespace
     *
     * @param string|null $namespace
     * @return bool
     */
    public function clearCurrentMessages($namespace = null)
    {
        if (!is_string($namespace) || $namespace == '') {
            $namespace = $this->getNamespace();
        }
        
        if ($this->hasCurrentMessages($namespace)) {
            unset(self::$_session[$namespace]);
            return true;
        }
        
        return false;
    }

    /**
     * hasCurrentMessages() - check to see if messages have been added to current
     * namespace within this request
     *
     * @param string|null $namespace
     * @return bool
     */
    public function hasCurrentMessages($namespace = null)
    {
        if (!is_string($namespace) || $namespace == '') {
            $namespace = $this->getNamespace();
        }
        
        return isset(self::$_session[$namespace]);
    }

    /**
     * hasMessages() - Wether a specific namespace has messages
     *
     * @param string|null $namespace
     * @return bool
     */
    public function hasMessages($namespace = null)
    {
        if (!is_string($namespace) || $namespace == '') {
            $namespace = $this->getNamespace();
        }
        
        return isset(self::$_messages[$namespace]);
    }
}
