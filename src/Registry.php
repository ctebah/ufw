<?php
namespace Ufw;

/**
 * Process-wide key/value store (extends {@see \ArrayObject}) with {@see Singleton} access and optional {@see \SplObserver} notifications.
 *
 * @extends \ArrayObject<string, mixed>
 * @method static Registry getInstance()
 */
class Registry extends \ArrayObject implements \SplSubject
{
    use Singleton;

    /**
     * @var array<string, array<int, \SplObserver>>
     */
    private $observers = [
        '' => []
    ];

    public function __construct()
    {
        parent::__construct(array(), \ArrayObject::STD_PROP_LIST | \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * @param string $index
     * @return mixed
     */
    public function get($index)
    {
        if (!$this->offsetExists($index)) {
            return null;
        }

        return $this->offsetGet($index);
    }

    /**
     * @return Application
     */
    public function getApplication()
    {
        $application = $this->get('application');
        if (!$application instanceof Application) {
            throw new \RuntimeException('Application is not registered.');
        }
        return $application;
    }

    /**
     * @param string $index
     * @return void
     */
    public function remove($index)
    {
        $this->offsetUnset($index);
    }

    /**
     * @param string $index
     * @param mixed $value
     * @return $this
     */
    public function set($index, $value)
    {
        $this->offsetSet($index, $value);
        $this->notify($index);
        return $this;
    }

    /**
     * @param string $index
     * @return bool
     */
    public function has($index)
    {
        return $this->offsetExists($index);
    }

    /**
     * @param string $regkey
     */
    #[\ReturnTypeWillChange]
    public function attach(\SplObserver $observer, $regkey = '')
    {
        if (!isset($this->observers[$regkey])) {
            $this->observers[$regkey] = [];
        }
        $key = array_search($observer, $this->observers[$regkey], true);
        if (false === $key) {
            $this->observers[$regkey][] = $observer;
        }
    }

    /**
     * @param string $regkey
     */
    #[\ReturnTypeWillChange]
    public function detach(\SplObserver $observer, $regkey = '')
    {
        if (!isset($this->observers[$regkey])) {
            return;
        }
        $key = array_search($observer, $this->observers[$regkey], true);
        if (false !== $key) {
            unset($this->observers[$regkey][$key]);
        }
    }

    /**
     * @param string $regkey
     */
    #[\ReturnTypeWillChange]
    public function notify($regkey = '')
    {
        if (isset($this->observers[$regkey])) {
            foreach ($this->observers[$regkey] as $value) {
                $value->update($this);
            }
        }
        foreach ($this->observers[''] as $value) {
            $value->update($this);
        }
    }
}
