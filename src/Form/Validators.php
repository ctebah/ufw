<?php
namespace Ufw\Form;

/**
 * Validator bag on forms/elements: register {@see Validator\Base} instances or short names resolved to `Form\Validator\*` / `Ufw\Form\Validator\*`.
 */
trait Validators {

    /**
     * @var \Ufw\Form\Collection|null
     */
    protected $validators = null;

    /**
     * @return \Ufw\Form\Collection
     */
    protected function validators()
    {
        if ($this->validators === null) {
            $this->validators = new Collection();
        }
        return $this->validators;
    }

    /**
     * @param string $name
     * @return \Ufw\Form\Validator\Base|null
     */
    public function getValidator($name)
    {
        $validator = $this->validators()->get($name);
        $out = null;
        if ($validator instanceof \Ufw\Form\Validator\Base) {
            $out = $validator;
        } elseif (is_string($validator)) {
            $class = $this->resolveValidatorClass($validator);
            $validator = new $class();
            $this->setValidator($name, $validator);
            $out = $validator;
        } elseif (is_array($validator)) {
            $validatorName = $name;
            if (isset($validator['name']) && is_string($validator['name']) && $validator['name'] !== '') {
                $validatorName = $validator['name'];
            }
            $class = $this->resolveValidatorClass($validatorName);
            $r = new \ReflectionClass($class);
            if ($r->hasMethod('__construct')) {
                $options = (array) ($validator['options'] ?? []);
                $ctor = $r->getConstructor();
                if ($ctor !== null && $options !== array()) {
                    $params = $ctor->getParameters();
                    if (count($params) > 0 && array_keys($options) !== range(0, count($options) - 1)) {
                        $options = array_values($options);
                    }
                }
                $validator = $r->newInstanceArgs($options);
            } else {
                $validator = $r->newInstance();
            }
            $this->setValidator($name, $validator);
            $out = $validator;
        }
        if ($out === null) {
            return null;
        }
        $out->setElement($this);
        return $out;
    }

    /**
     * @param string $shortName
     * @return class-string<\Ufw\Form\Validator\Base>
     */
    protected function resolveValidatorClass($shortName)
    {
        $class = 'Form\\Validator\\' . ucfirst($shortName);
        if (!class_exists($class)) {
            $class = 'Ufw\\Form\\Validator\\' . ucfirst($shortName);
        }
        if (!class_exists($class) || !is_subclass_of($class, \Ufw\Form\Validator\Base::class)) {
            throw new \InvalidArgumentException(sprintf('Unknown form validator class for "%s".', $shortName));
        }

        return $class;
    }

    /**
     * @param \Ufw\Form\Validator\Base|array<string, mixed>|string $name
     * @param \Ufw\Form\Validator\Base|array<string, mixed>|string|null $value
     * @return $this
     */
    public function addValidator($name, $value = null)
    {
        if ($name instanceof \Ufw\Form\Validator\Base) {
            $value = $name;
            $name = $value->getName();
        } elseif (is_array($name)) {
            $value = $name;
            if (isset($name['name']) && is_string($name['name']) && $name['name'] !== '') {
                $name = $name['name'];
            } else {
                throw new \InvalidArgumentException('Validator definition must include a non-empty string "name".');
            }
        }
        if (!$value) {
            $value = $name;
        }
        $this->validators()->set($name, $value);
        return $this;
    }

    /**
     * @param array<int, \Ufw\Form\Validator\Base|array<string, mixed>|string> $validators
     * @return $this
     */
    public function addValidators(array $validators)
    {
        foreach ($validators as $validator) {
            $this->addValidator($validator);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setValidator($name, $value)
    {
        $this->validators()->set($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasValidator($name)
    {
        return $this->validators()->has($name);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function removeValidator($name)
    {
        return $this->validators()->remove($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidators()
    {
        return $this->validators()->getAll();
    }

    /**
     * @return list<string>
     */
    public function getValidatorNames()
    {
        return array_keys($this->getValidators());
    }

    /**
     * @param array<int, \Ufw\Form\Validator\Base|array<string, mixed>|string> $values
     * @return $this
     */
    public function setValidators(array $values)
    {
        $this->validators()->clear();
        $this->addValidators($values);
        return $this;
    }
}
