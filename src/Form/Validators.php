<?php
namespace Ufw\Form;

/**
 * Validator bag on forms/elements: register {@see Validator\Base} instances or short names resolved to `Form\Validator\*` / `Ufw\Form\Validator\*`.
 */
trait Validators {

    /**
     *
     * @var \Ufw\Form\Collection
     */
    protected $validators = null;

    /**
     *
     * @return \Ufw\Form\Collection
     */
    protected function validators()
    {
        if (!isset($this->validators)) {
            $this->validators = new Collection();
        }
        return $this->validators;
    }

    public function getValidator($name)
    {
        $validator = $this->validators()->get($name);
        $out = null;
        if ($validator instanceof \Ufw\Form\Validator\Base) {
            $out = $validator;
        }
        if (is_string($validator)) {
            $class = 'Form\\Validator\\' . ucfirst($validator);
            if (!class_exists($class)) {
                $class = 'Ufw\\Form\\Validator\\' . ucfirst($validator);
            }
            $validator = new $class();
            $this->setValidator($name, $validator);
            $out = $validator;
        }
        if (is_array($validator)) {
            $class = 'Form\\Validator\\' . ucfirst(isset($validator['name']) && $validator['name'] ? $validator['name'] : $name);
            if (!class_exists($class)) {
                $class = 'Ufw\\Form\\Validator\\' . ucfirst(isset($validator['name']) && $validator['name'] ? $validator['name'] : $name);
            }
            $r = new \ReflectionClass($class);
            if ($r->hasMethod('__construct')) {
                $validator = $r->newInstanceArgs((array) $validator['options']);
            } else {
                $validator = $r->newInstance();
            }
            $this->setValidator($name, $validator);
            $out = $validator;
        }
        $out->setElement($this);
        return $out;
    }

    public function addValidator($name, $value = null)
    {
        if ($name instanceof \Ufw\Form\Validator\Base) {
            $value = $name;
            $name = $value->getName();
        } elseif (is_array($name)) {
            $value = $name;
            $name = $name['name'];
        }
        if (!$value) {
            $value = $name;
        }
        $this->validators()->set($name, $value);
        return $this;
    }
    
    public function addValidators($validators)
    {
        foreach ($validators as $validator) {
            $this->addValidator($validator);
        }
        return $this;
    }
    
    public function setValidator($name, $value)
    {
        $this->validators()->set($name, $value);
        return $this;
    }

    public function hasValidator($name)
    {
        return $this->validators()->has($name);
    }

    public function removeValidator($name)
    {
        return $this->validators()->remove($name);
    }

    /**
     *
     * @return array
     */
    public function getValidators()
    {
        return $this->validators()->getAll();
    }

    public function getValidatorNames()
    {
        return array_keys($this->getValidators());
    }

    /**
     *
     * @param array $values
     */
    public function setValidators($values)
    {
        $this->validators()->clear();
        $this->addValidators($values);
        return $this;
    }
}