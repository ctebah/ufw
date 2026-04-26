<?php
namespace Ufw\Model\Behaviour;

/**
 * Behaviour plugin for a {@see \Ufw\Model\Record\Base}: implements lifecycle hooks named in {@see $behaviourMethods}.
 * The record declares instances in {@see \Ufw\Model\Record\Base::behaviours()}; the stack is built once per record class.
 *
 * Concrete behaviours implement only the hooks they need (registration uses {@see $behaviourMethods} ∩ class methods).
 * Optional hook shapes (match how {@see \Ufw\Model\Record\Base} invokes them):
 *
 * @method array  beforeDataSet(array $data, array $opts = [])
 * @method void   afterDataSet(array $opts = [])
 * @method mixed  beforeDelete(array $opts = [])
 * @method void   afterDelete(mixed $success, array $opts = [])
 * @method mixed  beforeInsert(array $opts = [])
 * @method void   afterInsert(mixed $success, array $opts = [])
 * @method mixed  beforeUpdate(array $opts = [])
 * @method void   afterUpdate(mixed $success, array $opts = [])
 * @method mixed  beforeSave(array $opts = [])
 * @method void   afterSave(mixed $success, array $opts = [])
 */
abstract class Base
{

    public static $behaviourMethods = [
        'beforeDataSet',
        'afterDataSet',
        'beforeDelete',
        'afterDelete',
        'beforeInsert',
        'afterInsert',
        'beforeUpdate',
        'afterUpdate',
        'beforeSave',
        'afterSave'
    ];

    /**
     *
     * @var \Ufw\Model\Record\Base
     */
    protected $owner = null;

    public function __construct(\Ufw\Model\Record\Base $owner)
    {
        $this->setOwner($owner);
    }

    /**
     *
     * @return \Ufw\Model\Record\Base
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     *
     * @param \Ufw\Model\Record\Base $owner
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }
    
    public function getApplication() {
        return \Ufw\Registry::getInstance()->get('application');
    }
    
    public function getHelper($name) {
        return $this->getApplication()->getHelper($name);
    }
}