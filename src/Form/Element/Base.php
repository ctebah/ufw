<?php

namespace Ufw\Form\Element;

/**
 * Abstract form field: HTML attributes/options, filters, validators, value lifecycle, and rendering through the form helper’s {@see \Ufw\Helper\FormRenderHelperBase::field()}.
 */
abstract class Base
{
    use \Ufw\Form\Attribs, \Ufw\Form\Options, \Ufw\Form\Filters, \Ufw\Form\Validators, \Ufw\Form\Translate;

    protected $defaultOptionsToAttribs = [
        'name',
        'id',
        'placeholder',
        'class',
        'type',
        'required',
        'maxlength',
        'title',
        'data-group',
        'data-group-errmsg',
        'style',
        'readonly',
        'disabled',
        'pattern'
    ];

    /**
     *
     * @var string
     */
    protected $value = null;

    protected $valueUnfiltered = null;

    protected $type = 'text';

    protected $errors;

    /**
     * Ignore flag (used when retrieving values at form level)
     *
     * @var bool
     */
    protected $ignore = false;

    /**
     *
     * @var \Ufw\Form\Base
     */
    protected $form = null;

    protected $labelPlacement = 'above';

    /**
     * bootstrap3 / bootstrap4 / bootstrap5 (or any other) helper would use this value to render element itself
     *
     * @var string
     */
    protected $renderScript = null;

    protected $renderScriptPath = null;

    protected static $renderHelperName = null;

    protected $allowMultiple = false;

    public function __construct($name, array $options = null)
    {
        $this->setOptionsToAttribs($this->defaultOptionsToAttribs);
        $options = array_merge($this->getDefaultOptions(), (array) $options, [
            'name' => $name
        ]);
        $this->setOptions($options);
        $this->preInit();
        $this->init();
        $this->postInit();
    }

    protected function getDefaultOptions()
    {
        return [
            'allowMultiple' => $this->getAllowMultiple(),
            'class' => 'form-control',
            'type' => $this->getType(),
            'labelClass' => 'control-label'
        ];
    }

    protected function preInit()
    {
        if (null === $this->getAttrib('id')) {
            $id = trim(strtolower(preg_replace(array(
                '~([A-Z][^A-Z])~',
                '~[\-_\[\]]{1,}~',
                '~\-+~'
            ), array(
                '-$1',
                '-',
                '-'
            ), $this->getAttrib('name'))), '-');
            $this->setAttrib('id', $id . '-form-element');
        }
        return $this;
    }

    public function init()
    {
        return $this;
    }

    protected function postInit()
    {
        return $this;
    }

    public function __get($name)
    {
        if (method_exists($this, 'get' . ucfirst($name))) {
            return call_user_func(array(
                $this,
                'get' . ucfirst($name)
            ));
        }
        if ($this->hasOption($name)) {
            return $this->getOption($name);
        }
        if ($this->hasElement($name)) {
            return $this->getElement($name);
        }
        if ($this->hasAttrib($name)) {
            return $this->getAttrib($name);
        }
        return null;
    }

    public function __set($name, $value)
    {
        if (method_exists($this, 'set' . ucfirst($name))) {
            return call_user_func(array(
                $this,
                'set' . ucfirst($name)
            ), $value);
        }
    }

    public function __isset($name): bool
    {
        return $this->hasOption($name) || $this->hasAttrib($name);
    }

    public function __call($method, $args)
    {
        if (substr($method, 0, 3) == 'get') {
            $prop = lcfirst(substr($method, 3));
            return $this->$prop;
        }

        return null;
    }

    public function __toString()
    {
        return $this->getHelper($this->getRenderHelperName())
            ->field($this);
    }

    public function addError($message)
    {
        $this->errors[] = $this->translate($message);
        return $this;
    }

    public function addErrors($messages)
    {
        foreach ($messages as $message) {
            $this->addError($message);
        }
        return $this;
    }

    public function clearErrors()
    {
        $this->errors = [];
        return $this;
    }

    public function isValid($context = null)
    {
        $value = $this->getValue();
        $this->clearErrors();
        if ($this->isRequired()) {
            $this->addValidator('required', [
                'options' => [
                    $this->getAllowMultiple()
                ]
            ]);
        }
        $result = true;
        foreach ($this->getValidatorNames() as $validatorKey) {
            $validator = $this->getValidator($validatorKey);
            if (!$validator->isValid($value, $context)) {
                $result = false;
                $this->addErrors($validator->getErrors());
            }
        }

        return $result;
    }

    public function getHelper($name)
    {
        return \Ufw\Registry::getInstance()->get('application')->getHelper($name);
    }

    public function isRequired()
    {
        return !!$this->getAttrib('required');
    }

    public function addClass($className)
    {
        $cls = $this->getAttrib('class');
        if (!$cls || !preg_match('~(^|\s)' . preg_quote($className, '~') . '($|\s)~', $cls)) {
            $this->setAttrib('class', "$cls $className");
        }
        return $this;
    }

    public function removeClass($className)
    {
        $cls = $this->getAttrib('class');
        $this->setAttrib('class', preg_replace('~(^|\s)' . preg_quote($className, '~') . '($|\s)~', '$1$2', $cls));
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     *
     * @return string|array
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getAttribs()
    {
        $attribs = $this->attribs()->getAll();
        if ($this->getAllowMultiple() && isset($attribs['name']) && $attribs['name'] && !preg_match('~\[\]$~', $attribs['name'])) {
            $attribs['name'] = $attribs['name'] . '[]';
        }
        return $attribs;
    }

    /**
     *
     * @param string $value
     */
    public function setValue($value, $context = null)
    {
        $this->valueUnfiltered = $value;
        if ($this->getFilters()) {
            foreach ($this->getFilterNames() as $filterKey) {
                $filter = $this->getFilter($filterKey);
                $value = $filter->filter($value);
            }
        }
        $this->value = $value;

        if ($this->getAllowMultiple()) {
            if ($this->getMultiOptionValues()) {
                if (is_array($this->value)) {
                    $this->value = array_intersect($this->value, $this->getMultiOptionValues());
                } else {
                    $this->value = in_array($this->value, $this->getMultiOptionValues()) ? $this->value : null;
                }
            }
        }

        if ((is_array($this->getValueUnfiltered()) && $this->getValueUnfiltered()) || (is_scalar($this->getValueUnfiltered()) && strlen($this->getValueUnfiltered()))) {
            $this->addClass('has-content');
        }

        return $this;
    }

    /**
     *
     * @return \Ufw\Form\Base
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     *
     * @param \Ufw\Form\Base $form
     */
    public function setForm(\Ufw\Form\Base $form = null)
    {
        $this->form = $form;
        return $this;
    }

    /**
     *
     * @return bool
     */
    public function getIgnore()
    {
        return $this->ignore;
    }

    /**
     *
     * @param bool $ignore
     */
    public function setIgnore($ignore)
    {
        $this->ignore = $ignore;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getLabelPlacement()
    {
        return $this->labelPlacement;
    }

    /**
     *
     * @param string $labelPlacement
     */
    public function setLabelPlacement($labelPlacement)
    {
        $this->labelPlacement = $labelPlacement;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getRenderScript()
    {
        return $this->renderScript ? $this->renderScript : $this->getRenderHelperName() . '/' . $this->renderScriptPath;
    }

    /**
     *
     * @param string $renderScript
     */
    public function setRenderScript($renderScript)
    {
        $this->renderScript = $renderScript;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getAllowMultiple()
    {
        return $this->allowMultiple;
    }

    /**
     *
     * @param boolean $allowMultiple
     */
    public function setAllowMultiple($allowMultiple)
    {
        $this->allowMultiple = $allowMultiple;
        $this->autoArray = $this->allowMultiple;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getValueUnfiltered()
    {
        return $this->valueUnfiltered;
    }

    /**
     *
     * @param string $valueUnfiltered
     */
    public function setValueUnfiltered($valueUnfiltered)
    {
        $this->valueUnfiltered = $valueUnfiltered;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getRenderHelperName()
    {
        if (!static::$renderHelperName) {
            $helperName = @\Ufw\Registry::getInstance()->get('application')->getConfig()['form']['helper'];
            if (!$helperName) {
                $helperName = 'bootstrap3';
            }
            self::setRenderHelperName($helperName);
        }
        return static::$renderHelperName;
    }

    /**
     *
     * @param string $renderHelperName
     */
    public static function setRenderHelperName($renderHelperName)
    {
        static::$renderHelperName = $renderHelperName;
    }
}
