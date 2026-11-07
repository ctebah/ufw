<?php
namespace Ufw\Model\Behaviour;

/**
 * Behaviour plugin for a {@see \Ufw\Model\Record\Base}: implements lifecycle hooks named in {@see $behaviourMethods}.
 * The record declares instances in {@see \Ufw\Model\Record\Base::behaviours()}; the stack is built once per record class.
 *
 * Concrete behaviours implement only the hooks they need (registration uses {@see $behaviourMethods} ∩ class methods).
 * Optional hook shapes (match how {@see \Ufw\Model\Record\Base} invokes them):
 *
 * @method array<string, mixed> beforeDataSet(array<string, mixed> $data, array<string, mixed> $opts = [])
 * @method void   afterDataSet(array<string, mixed> $opts = [])
 * @method mixed  beforeDelete(array<string, mixed> $opts = [])
 * @method void   afterDelete(mixed $success, array<string, mixed> $opts = [])
 * @method mixed  beforeInsert(array<string, mixed> $opts = [])
 * @method void   afterInsert(mixed $success, array<string, mixed> $opts = [])
 * @method mixed  beforeUpdate(array<string, mixed> &$opts = [])
 * @method void   afterUpdate(mixed $success, array<string, mixed> $opts = [])
 * @method mixed  beforeSave(array<string, mixed> $opts = [])
 * @method void   afterSave(mixed $success, array<string, mixed> $opts = [])
 */
abstract class Base
{

    /**
     * @var array<int, string>
     */
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
     * Lower runs first on every hook. Record config key <code>order</code> overrides this.
     * Behaviours that do not set one land at 500, after the bundled 100–150 block.
     *
     * @var int
     */
    public $order = 500;

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
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return \Ufw\Application
     */
    public function getApplication() {
        return \Ufw\Registry::getInstance()->getApplication();
    }

    /**
     * @param string $name
     * @return \Ufw\Helper\Base|null
     */
    public function getHelper($name) {
        return $this->getApplication()->getHelper($name);
    }
}