<?php
namespace Ufw\Form;

/**
 * HTML attribute map for forms/elements; selected keys (`placeholder`, `title`) pass through the translator when enabled.
 */
trait Attribs {

    /**
     *
     * @var \Ufw\Form\Collection
     */
    protected $attribs = null;

    protected static $translatableAttribs = [
        'placeholder',
        'title',
    ];

    /**
     *
     * @return \Ufw\Form\Collection
     */
    protected function Attribs()
    {
        if (!isset($this->attribs)) {
            $this->attribs = new Collection();
        }
        return $this->attribs;
    }

    public function getAttrib($name)
    {
        $out = $this->attribs()->get($name);
        return $out && in_array($name, static::$translatableAttribs) ? $this->translate($out) : $out;
    }

    public function setAttrib($name, $value)
    {
        $this->attribs()->set($name, $value);
        return $this;
    }

    public function hasAttrib($name)
    {
        return $this->attribs()->has($name);
    }

    public function removeAttrib($name)
    {
        return $this->attribs()->remove($name);
    }

    /**
     *
     * @return array
     */
    public function getAttribs()
    {
        $out = $this->attribs()->getAll();
        foreach (array_intersect(array_keys($out), static::$translatableAttribs) as $key) {
            $out[$key] = $this->getAttrib($key);
        }
        return $out;
    }

    /**
     *
     * @param array $values
     */
    public function setAttribs($values)
    {
        return $this->attribs()->setAll($values);
    }
}