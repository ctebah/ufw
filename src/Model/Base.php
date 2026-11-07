<?php
namespace Ufw\Model;

/**
 * Thin application model: points at the concrete {@see $recordClassName} record class, exposes {@see getDb()},
 * {@see getApplication()}, and {@see getUser()}. Magic {@see __get()} delegates to `get*` methods (e.g. `user` → {@see getUser()}).
 *
 * @property \Ufw\Helper\User $user Via {@see __get()} and {@see getUser()}.
 */
abstract class Base
{

    /**
     * Record class name - must be re-declared in each derived class
     * 
     * @var string
     */
    protected $recordClassName = '\Model\Record\Base';

    public function __construct()
    {}

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }

    /**
     * @return \Ufw\Db\Db
     */
    public function getDb()
    {
        return \Ufw\Db\Db::forApp();
    }
    
    /**
     *
     * @return string
     */
    public function getRecordClassName()
    {
        return $this->recordClassName;
    }

    /**
     * 
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return \Ufw\Registry::getInstance()->getApplication();
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        $helper = $this->getApplication()->getHelper('user');
        if (is_callable($helper)) {
            return $helper();
        }
        return $helper;
    }
}