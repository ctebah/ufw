<?php
namespace Ufw\Form;

/**
 * Key–value bag used for form/element attributes, options, nested elements, validators, and filters.
 */
class Collection extends \Ufw\Utils\ArrayAccess
{

    public function clear() {
        $this->_data = [];
        return $this;
    }
    
    public function get($name)
    {
        return isset($this[$name]) ? $this[$name] : null;
    }

    public function has($name)
    {
        return $this->offsetExists($name);
    }

    public function set($name, $value)
    {
        $this[$name] = $value;
        return $this;
    }

    public function remove($name)
    {
        $el = $this[$name];
        unset($this[$name]);
        return $el;
    }
}