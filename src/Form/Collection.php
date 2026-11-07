<?php
namespace Ufw\Form;

/**
 * Key–value bag used for form/element attributes, options, nested elements, validators, and filters.
 */
class Collection extends \Ufw\Utils\ArrayAccess
{

    /**
     * @return $this
     */
    public function clear()
    {
        $this->_data = [];
        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        return isset($this[$name]) ? $this[$name] : null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has($name)
    {
        return $this->offsetExists($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function set($name, $value)
    {
        $this[$name] = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function remove($name)
    {
        $el = $this[$name];
        unset($this[$name]);
        return $el;
    }
}
