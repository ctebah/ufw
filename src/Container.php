<?php
namespace Ufw;

/**
 * Lazy map of named instances of a single class: {@see get()} builds each key once via reflection ({@see $dataType}, {@see $contructArgs}).
 *
 * @template T of object
 */
class Container
{

    /**
     * @var array<string, T>
     */
    protected $items = array();

    /**
     * @var class-string<T>
     */
    protected $dataType;

    /**
     * @var array<int, mixed>
     */
    protected $contructArgs = [];

    /**
     * @param class-string<T> $dataType
     * @param array<int, mixed> $contructArgs
     */
    public function __construct($dataType, $contructArgs = [])
    {
        $this->dataType = $dataType;
        $this->contructArgs = $contructArgs;
    }

    /**
     * @param string $name
     * @return T
     */
    public function get($name)
    {
        if (!isset($this->items[$name])) {
            $class_name = $this->dataType;
            $ref = new \ReflectionClass($class_name);
            $this->items[$name] = $ref->newInstanceArgs($this->contructArgs);
        }
        return $this->items[$name];
    }

    /**
     * @param string $name
     * @param T $item
     * @return $this
     */
    public function set($name, $item) {
        $this->items[$name] = $item;
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has($name) {
        return isset($this->items[$name]);
    }

    /**
     * @return array<string, T>
     */
    public function getAll()
    {
        return $this->items;
    }
}
