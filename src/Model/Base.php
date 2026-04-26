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

    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array(
                $this,
                'get' . ucfirst($name)
            ));
        }
        return null;
    }

    public function getDb()
    {
        return \Ufw\Db\Db::instance();
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
        return \Ufw\Registry::getInstance()->get('application');
    }

    public function getUser()
    {
        return $this->getApplication()->getHelper('user')();
    }
}