<?php
namespace Ufw\Form;

/**
 * Named {@see Element\Base} children on a form: add/remove/get and wire into validation/population.
 */
trait Elements {

    /**
     * @var \Ufw\Form\Collection|null
     */
    protected $elements = null;

    /**
     * @return \Ufw\Form\Collection
     */
    protected function elements()
    {
        if ($this->elements === null) {
            $this->elements = new Collection();
        }
        return $this->elements;
    }

    /**
     * @param string $name
     * @return \Ufw\Form\Element\Base|null
     */
    public function getElement($name)
    {
        $element = $this->elements()->get($name);
        return $element instanceof \Ufw\Form\Element\Base ? $element : null;
    }

    /**
     * @return $this
     */
    public function addElement(\Ufw\Form\Element\Base $el)
    {
        $this->elements()->set($el->getName(), $el);
        $el->setForm($this);
        return $this;
    }

    /**
     * @param string $name
     * @param \Ufw\Form\Element\Base $value
     * @return $this
     */
    public function setElement($name, $value)
    {
        $this->elements()->set($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasElement($name)
    {
        return $this->elements()->has($name);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function removeElement($name)
    {
        return $this->elements()->remove($name);
    }

    /**
     * @return array<string, \Ufw\Form\Element\Base>
     */
    public function getElements()
    {
        $all = $this->elements()->getAll();
        $out = [];
        foreach ($all as $key => $element) {
            if ($element instanceof \Ufw\Form\Element\Base) {
                $out[(string) $key] = $element;
            }
        }
        return $out;
    }

    /**
     * @param array<string, \Ufw\Form\Element\Base> $values
     * @return $this
     */
    public function setElements(array $values)
    {
        $this->elements()->setAll($values);
        return $this;
    }
}
