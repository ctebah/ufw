<?php
namespace Ufw\Form\Filter;

/** Input normalizer run before validation: transforms a single value in {@see filter()}. */
abstract class Base
{

    abstract public function filter($value);

    /**
     * Key for {@see \Ufw\Form\Filters::addFilter()} when passing an instance.
     * Same derivation as {@see \Ufw\Form\Validator\Base::getName()} (FQCN-style key).
     */
    public function getName(): string
    {
        return trim(preg_replace('~\\\\~', '_', get_class($this)), '\\');
    }
}
