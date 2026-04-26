<?php
namespace Ufw\Form;

/**
 * Named {@see Element\Base} children on a form: add/remove/get and wire into validation/population.
 */
trait Elements {

    /**
     *
     * @var \Ufw\Form\Collection
     */
    protected $elements = null;

    /**
     *
     * @return \Ufw\Form\Collection
     */
    protected function elements()
    {
        if (!isset($this->elements)) {
            $this->elements = new Collection();
        }
        return $this->elements;
    }

    /**
     * 
     * @param string $name
     * @return \Ufw\Form\Element\Base
     */
    public function getElement($name)
    {
        return $this->elements()->get($name);
    }

    public function addElement(\Ufw\Form\Element\Base $el)
    {
        $this->elements()->set($el->getName(), $el);
        $el->setForm($this);
        return $this;
    }

    public function setElement($name, $value)
    {
        $this->elements()->set($name, $value);
        return $this;
    }

    public function hasElement($name)
    {
        return $this->elements()->has($name);
    }

    public function removeElement($name)
    {
        return $this->elements()->remove($name);
    }

    /**
     *
     * @return \Ufw\Form\Element\Base[]
     */
    public function getElements()
    {
        return $this->elements()->getAll();
    }

    /**
     *
     * @param array $values
     */
    public function setElements($values)
    {
        $this->elements()->setAll($values);
        return $this;
    }
}