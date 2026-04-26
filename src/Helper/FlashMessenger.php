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
     * @var array
     */
    protected static $_messages = array();

    protected $off = 0;

    /**
     *
     * @var string Instance namespace, default is 'info'
     */
    protected $namespace = self::NAMESPACE_INFO;

    /**
     * $_session - storage object
     */
    protected static $_session = null;

    /**
     * __construct() - Instance constructor, needed to get iterators, etc
     *
     * @param string $namespace            
     * @return void
     */
    public function __construct()
    {
        if (!isset(self::$_session)) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            self::$_session = & $_SESSION['ufw__fM'];
            if (!isset(self::$_session) || !is_array(self::$_session)) {
                self::$_session = array();
            }
            foreach (self::$_session as $namespace => $messages) {
                self::$_messages[$namespace] = $messages;
                unset(self::$_session[$namespace]);
            }
        }
    }

    public function __invoke()
    {
        return $this;
    }

    public function addMessage($message, $namespace = null, $translatable = true)
    {
        if ($this->isOff()) {
            return $this;
        }
        
        foreach ((array) $message as $msg) {
            if (!is_string($namespace) || $namespace == '') {
                $namespace = $this->getNamespace();
            }
            if (!in_array($msg, array_merge($this->getMessages($namespace), $this->getCurrentMessages($namespace)))) {
                if (!array_key_exists($namespace, self::$_session)) {
                    self::$_session[$namespace] = array();
                }
                
                self::$_session[$namespace][] = $translatable ? $this->translate($message) : $message;
            }
        }
        
        return $this;
    }

    public function getErrors()
    {
        $out = array_merge($this->getCurrentMessages(self::NAMESPACE_ERROR), $this->getMessages(self::NAMESPACE_ERROR));
        $this->clearCurrentMessages(self::NAMESPACE_ERROR);
        return $out;
    }

    public function getInfos()
    {
        $out = array_merge($this->getCurrentMessages(self::NAMESPACE_INFO), $this->getMessages(self::NAMESPACE_INFO));
        $this->clearCurrentMessages(self::NAMESPACE_INFO);
        return $out;
    }

    public function getWarnings()
    {
        $out = array_merge($this->getCurrentMessages(self::NAMESPACE_WARNING), $this->getMessages(self::NAMESPACE_WARNING));
        $this->clearCurrentMessages(self::NAMESPACE_WARNING);
        return $out;
    }

    public function hasErrors()
    {
        return $this->hasMessages(self::NAMESPACE_ERROR) || $this->hasCurrentMessages(self::NAMESPACE_ERROR);
    }

    public function hasInfos()
    {
        return $this->hasMessages(self::NAMESPACE_INFO) || $this->hasCurrentMessages(self::NAMESPACE_INFO);
    }

    public function hasWarnings()
    {
        return $this->hasMessages(self::NAMESPACE_WARNING) || $this->hasCurrentMessages(self::NAMESPACE_WARNING);
    }

    public function isOff()
    {
        return !!$this->off;
    }

    public function off()
    {
        $this->off++;
        return $this;
    }

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
     * @return array
     */
    public function getCurrentMessages($namespace = null)
    {
        if (!is_string($namespace) || $namespace == '') {
            $namespace = $this->getNamespace();
        }
        
        if ($this->hasCurrentMessages($namespace)) {
            return self::$_session[$namespace];
        }
        
        return array();
    }

    /**
     * getMessages() - Get messages from a specific namespace
     *
     * @return array
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
     * @return boolean
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
     * @return boolean
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
     * @return boolean
     */
    public function hasMessages($namespace = null)
    {
        if (!is_string($namespace) || $namespace == '') {
            $namespace = $this->getNamespace();
        }
        
        return isset(self::$_messages[$namespace]);
    }
}
