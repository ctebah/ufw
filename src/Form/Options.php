<?php
namespace Ufw\Form;

/**
 * Non-attribute options (`label`, `caption`, `description`, …) with optional translation and mapping into attribs where configured.
 */
trait Options {

    /**
     * @var \Ufw\Form\Collection|null
     */
    protected $options = null;

    /**
     * @var list<string>
     */
    protected $optionsToAttribs = [];

    /**
     * @var list<string>
     */
    protected static $translatableOptions = [
        'label',
        'caption',
        'description'
    ];

    /**
     * @return \Ufw\Form\Collection
     */
    protected function options()
    {
        if ($this->options === null) {
            $this->options = new Collection();
        }
        return $this->options;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getOption($name)
    {
        $out = $this->options()->get($name);
        return $out && in_array($name, static::$translatableOptions, true) ? $this->translate($out) : $out;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setOption($name, $value)
    {
        $this->options()->set($name, $value);
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasOption($name)
    {
        return $this->options()->has($name);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function removeOption($name)
    {
        return $this->options()->remove($name);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions()
    {
        $out = $this->options()->getAll();
        foreach (array_intersect(array_keys($out), static::$translatableOptions) as $key) {
            $out[$key] = $this->getOption($key);
        }
        return $out;
    }

    /**
     * @param array<string, mixed>|null $options
     * @return $this
     */
    public function setOptions($options)
    {
        if ($options === null) {
            return $this;
        }
        $mergedAttribs = $this->getAttribs();
        if (isset($options['attribs']) && is_array($options['attribs'])) {
            foreach ($options['attribs'] as $attribKey => $attribValue) {
                if (is_string($attribKey) && !array_key_exists($attribKey, $mergedAttribs)) {
                    $mergedAttribs[$attribKey] = $attribValue;
                }
            }
        }
        $options['attribs'] = $mergedAttribs;
        foreach ($options as $key => $value) {
            if ($key == 'attribs') {
                continue;
            }
            if (in_array($key, $this->optionsToAttribs, true)) {
                if (isset($value)) {
                    $options['attribs'][$key] = $value;
                    unset($options[$key]);
                }
            } else {
                $setter = 'set' . ucfirst($key);
                if (method_exists($this, $setter)) {
                    $this->$setter($value);
                    unset($options[$key]);
                }
            }
        }
        $this->setAttribs($options['attribs']);
        unset($options['attribs']);
        foreach ($options as $key => $value) {
            $this->options()->set((string) $key, $value);
        }
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getOptionsToAttribs()
    {
        return $this->optionsToAttribs;
    }

    /**
     * @param list<string> $optionsToAttribs
     * @return $this
     */
    public function setOptionsToAttribs(array $optionsToAttribs)
    {
        $this->optionsToAttribs = $optionsToAttribs;
        return $this;
    }
}
