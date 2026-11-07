<?php

namespace Ufw\Utils;

/**
 * Hybrid property/array bag: magic access maps to `$_data` unless a real object property exists; implements
 * {@see \ArrayAccess}, {@see \Iterator}, and {@see \Countable} for row-style iteration (used by {@see \Ufw\Model\Record\Base}).
 *
 * @implements \ArrayAccess<string, mixed>
 * @implements \Iterator<string, mixed>
 */
class ArrayAccess implements \ArrayAccess, \Iterator, \Countable
{

    /**
     * @var array<string, mixed>
     */
    protected $_data = [];

    /**
     * @param string $name
     * @return mixed
     */
    public function &__get($name)
    {
        if (property_exists($this, (string) $name)) {
            return $this->$name;
        } else {
            return $this->_data[$name];
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value): void
    {
        if (property_exists($this, (string) $name)) {
            $this->$name = $value;
        } else {
            $this->_data[$name] = $value;
        }
    }

    /**
     * @param string $name
     */
    public function __isset($name): bool
    {
        return array_key_exists($name, $this->_data) && !is_null($this->_data[$name]);
    }

    /**
     * @param string $name
     */
    public function __unset($name): void
    {
        if (property_exists($this, (string) $name)) {
            unset($this->$name);
        } else {
            unset($this->_data[$name]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll()
    {
        return $this->_data;
    }

    /**
     * @param array<string, mixed> $data
     * @return $this
     */
    public function overrideExisting($data)
    {
        $this->_data = $this->_data ? array_merge($this->_data, $data) : $data;
        return $this;
    }

    /**
     * Merge `$data` only into entries that are currently empty/falsy in `$_data`.
     *
     * @param array<string, mixed> $data
     * @return $this
     */
    public function overrideEmpty($data)
    {
        if ($this->_data) {
            $filtered = array_filter($this->_data);
            $this->_data = array_merge($this->_data, $filtered + $data);
        } else {
            $this->_data = $data;
        }
        return $this;
    }

    /**
     * @param array<int|string, mixed>|false|null $data
     * @return $this
     */
    public function setAll($data)
    {
        if (!is_array($data)) {
            $this->_data = [];
            return $this;
        }
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[(string) $key] = $value;
        }
        $this->_data = $normalized;
        return $this;
    }

    /**
     * @param string|null $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->$offset = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->_data[$offset]);
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->_data[$offset]);
    }

    /**
     * @param string $offset
     * @return mixed
     */
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

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->_data);
    }

    /**
     * @return string|null
     */
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
