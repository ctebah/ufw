<?php
namespace Ufw\Form;

/**
 * HTML attribute map for forms/elements; selected keys (`placeholder`, `title`) pass through the translator when enabled.
 */
trait Attribs {

    /**
     * @var \Ufw\Form\Collection|null
     */
    protected $attribs = null;

    /**
     * @var list<string>
     */
    protected static $translatableAttribs = [
        'placeholder',
        'title',
    ];

    /**
     * @return \Ufw\Form\Collection
     */
    protected function attribs()
    {
        if ($this->attribs === null) {
            $this->attribs = new Collection();
        }
        return $this->attribs;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttrib($name)
    {
        $out = $this->attribs()->get($name);
        return $out && in_array($name, static::$translatableAttribs, true) ? $this->translate($out) : $out;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setAttrib($name, $value)
    {
        $this->attribs()->set($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttrib($name)
    {
        return $this->attribs()->has($name);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function removeAttrib($name)
    {
        return $this->attribs()->remove($name);
    }

    /**
     * @return array<string, mixed>
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
     * @param array<string, mixed> $values
     * @return $this
     */
    public function setAttribs(array $values)
    {
        $this->attribs()->setAll($values);
        return $this;
    }
}
