<?php
namespace Ufw;

/**
 * Lazy map of named instances of a single class: {@see get()} builds each key once via reflection ({@see $dataType}, {@see $contructArgs}).
 */
class Container
{

    protected $items = array();

    /**
     *
     * Items' type (name of the class)
     * 
     * @var string
     */
    protected $dataType;
    
    protected $contructArgs = [];

    public function __construct($dataType, $contructArgs = [])
    {
        $this->dataType = $dataType;
        $this->contructArgs = $contructArgs;
    }

    public function get($name)
    {
        if (!isset($this->items[$name])) {
            $class_name = $this->dataType;
            $ref = new \ReflectionClass($class_name);
            $this->items[$name] = $ref->newInstanceArgs($this->contructArgs);
        }
        return $this->items[$name];
    }

    public function set($name, $item) {
        $this->items[$name] = $item;
        return $this;
    }
    
    public function has($name) {
        return isset($this->items[$name]);
    }
    
    public function getAll()
    {
        return $this->items;
    }
}