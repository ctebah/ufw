<?php
namespace Ufw;

/**
 * Per-class singleton cache keyed by constructor args fingerprint; {@see getInstance()} vs fresh {@see newInstance()}.
 */
trait Singleton
{

    protected static $_instance = array();

    /**
     * Singleton model use get_called_class late static binding
     * function to determine name of the called class.
     *
     * @return static
     */
    public static function getInstance()
    {
        $class_name = get_called_class();
        $args = func_get_args();
        $fingerprint = md5($class_name . '|' . serialize($args));
        if (!isset(self::$_instance[$fingerprint])) {
            $ref = new \ReflectionClass($class_name);
            self::$_instance[$fingerprint] = $ref->newInstanceArgs($args);
        }
        return self::$_instance[$fingerprint];
    }

    /**
     * Static factory method.
     * Always produce new instance.
     *
     * @return static
     */
    public static function newInstance()
    {
        $class_name = get_called_class();
        $args = func_get_args();
        $ref = new \ReflectionClass($class_name);
        return $ref->newInstanceArgs($args);
    }
}