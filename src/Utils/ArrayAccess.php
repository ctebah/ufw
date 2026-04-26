<?php

namespace Ufw\Utils;

/**
 * Hybrid property/array bag: magic access maps to `$_data` unless a real object property exists; implements
 * {@see \ArrayAccess}, {@see \Iterator}, and {@see \Countable} for row-style iteration (used by {@see \Ufw\Model\Record\Base}).
 */
class ArrayAccess implements \ArrayAccess, \Iterator, \Countable
{

    protected $_data = [];

    public function &__get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        } else {
            return $this->_data[$name];
        }
    }

    public function __set($name, $value): void
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        } else {
            $this->_data[$name] = $value;
        }
    }

    public function __isset($name): bool
    {
        return array_key_exists($name, $this->_data) && !is_null($this->_data[$name]);
    }

    public function __unset($name): void
    {
        if (property_exists($this, $name)) {
            unset($this->$name);
        } else {
            unset($this->_data[$name]);
        }
    }

    public function getAll()
    {
        return $this->_data;
    }

    public function overrideExisting($data)
    {
        $this->_data = $this->_data ? array_merge($this->_data, $data) : $data;
        return $this;
    }

    public function overrideEmpty($data)
    {
        $this->_data = $this->_data ? array_merge($this->_data, array_filter($this->_data) + $data) : $data;
        return $this;
    }

    public function setAll($data)
    {
        $this->_data = $data;
        return $this;
    }

    /**
     * * interface implementation **
     */
    public function offsetSet($offset, $value): void
    {
        $this->$offset = $value;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->_data[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->_data[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    public function count(): int
    {
        return count($this->_data);
    }

    public function rewind(): void
    {
        reset($this->_data);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->_data);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->_data);
    }

    public function next(): void
    {
        next($this->_data);
    }

    public function valid(): bool
    {
        return !is_null($this->key());
    }
}
