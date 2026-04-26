<?php
namespace Ufw\Form;

/**
 * Non-attribute options (`label`, `caption`, `description`, …) with optional translation and mapping into attribs where configured.
 */
trait Options {

    /**
     *
     * @var \Ufw\Form\Collection
     */
    protected $options = null;

    protected $optionsToAttribs = [];

    protected static $translatableOptions = [
        'label',
        'caption',
        'description'
    ];

    /**
     *
     * @return \Ufw\Form\Collection
     */
    protected function options()
    {
        if (!isset($this->options)) {
            $this->options = new Collection();
        }
        return $this->options;
    }

    public function getOption($name)
    {
        $out = $this->options()->get($name);
        return $out && in_array($name, static::$translatableOptions) ? $this->translate($out) : $out;
    }

    public function setOption($name, $value)
    {
        $this->options()->set($name, $value);
        return $this;
    }

    public function hasOption($name)
    {
        return $this->options()->has($name);
    }

    public function removeOption($name)
    {
        return $this->options()->remove($name);
    }

    /**
     *
     * @return array
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
     *
     * @param array $options
     */
    public function setOptions($options)
    {
        if (method_exists($this, 'setAttrib')) {
            if (!isset($options['attribs'])) {
                $options['attribs'] = array();
            }
            $options['attribs'] += $this->getAttribs();
            foreach ($options as $key => $value) {
                if ($key == 'attribs')
                    continue;
                if (in_array($key, $this->optionsToAttribs)) {
                    if (isset($value)) {
                        $options['attribs'][$key] = $value;
                        unset($options[$key]);
                    }
                } elseif (method_exists($this, 'set' . ucfirst($key))) {
                    call_user_func(array(
                        $this,
                        'set' . ucfirst($key)
                    ), $value);
                    unset($options[$key]);
                }
            }
            $this->setAttribs($options['attribs']);
            unset($options['attribs']);
        }
        $this->options()->setAll($options);
        return $this;
    }

    /**
     *
     * @return array
     */
    public function getOptionsToAttribs()
    {
        return $this->optionsToAttribs;
    }

    /**
     *
     * @param array $optionsToAttribs
     */
    public function setOptionsToAttribs($optionsToAttribs)
    {
        $this->optionsToAttribs = $optionsToAttribs;
        return $this;
    }
}