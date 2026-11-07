<?php
namespace Ufw\Form\Filter;

/** Input normalizer run before validation: transforms a single value in {@see filter()}. */
abstract class Base
{

    /**
     * @param mixed $value
     * @return mixed
     */
    abstract public function filter($value);

    /**
     * Key for {@see \Ufw\Form\Filters::addFilter()} when passing an instance.
     */
    public function getName(): string
    {
        return trim((string) preg_replace('~\\\\~', '_', get_class($this)), '\\');
    }
}
