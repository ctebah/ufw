<?php

namespace Ufw\Form\Element;

/**
 * Abstract form field: HTML attributes/options, filters, validators, value lifecycle, and rendering through the form helper’s {@see \Ufw\Helper\FormRenderHelperBase::field()}.
 *
 * @method string|null getLabel()
 * @method string|null getLabelClass()
 * @method string|null getDescription()
 * @method string|null getIcon()
 * @method string|null getWrapClass()
 * @method string|null getCaption()
 */
abstract class Base
{
    use \Ufw\Form\Attribs, \Ufw\Form\Options, \Ufw\Form\Filters, \Ufw\Form\Validators, \Ufw\Form\Translate;

    /**
     * @var list<string>
     */
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
     * @var mixed
     */
    protected $value = null;

    /**
     * @var mixed
     */
    protected $valueUnfiltered = null;

    /**
     * @var string|null
     */
    protected $type = 'text';

    /**
     * @var list<string>
     */
    protected $errors = [];

    /**
     * Ignore flag (used when retrieving values at form level)
     *
     * @var bool
     */
    protected $ignore = false;

    /**
     * @var \Ufw\Form\Base|null
     */
    protected $form = null;

    /**
     * @var string
     */
    protected $labelPlacement = 'above';

    /**
     * bootstrap3 / bootstrap4 / bootstrap5 (or any other) helper would use this value to render element itself
     *
     * @var string|null
     */
    protected $renderScript = null;

    /**
     * @var string|null
     */
    protected $renderScriptPath = null;

    /**
     * @var string|null
     */
    protected static $renderHelperName = null;

    /**
     * @var bool
     */
    protected $allowMultiple = false;

    /**
     * @var bool
     */
    protected $autoArray = false;

    /**
     * @param string $name
     * @param array<string, mixed>|null $options
     */
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

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultOptions()
    {
        return [
            'allowMultiple' => $this->getAllowMultiple(),
            'class' => 'form-control',
            'type' => $this->getType(),
            'labelClass' => 'control-label'
        ];
    }

    /**
     * @return $this
     */
    protected function preInit()
    {
        if (null === $this->getAttrib('id')) {
            $name = $this->getAttrib('name');
            $nameStr = is_string($name) ? $name : '';
            $id = trim(strtolower((string) preg_replace(array(
                '~([A-Z][^A-Z])~',
                '~[\-_\[\]]{1,}~',
                '~\-+~'
            ), array(
                '-$1',
                '-',
                '-'
            ), $nameStr)), '-');
            $this->setAttrib('id', $id . '-form-element');
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function init()
    {
        return $this;
    }

    /**
     * Optional client-side metadata (see form `_metadata.php` partials).
     *
     * @return mixed
     */
    public function getMetadata()
    {
        return $this->getOption('metadata');
    }

    /**
     * @return $this
     */
    protected function postInit()
    {
        return $this;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get' . ucfirst($name);
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }
        if ($this->hasOption($name)) {
            return $this->getOption($name);
        }
        if ($this->hasAttrib($name)) {
            return $this->getAttrib($name);
        }
        return null;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value): void
    {
        $setter = 'set' . ucfirst($name);
        if (method_exists($this, $setter)) {
            $this->$setter($value);
        }
    }

    /**
     * @param string $name
     */
    public function __isset($name): bool
    {
        return $this->hasOption($name) || $this->hasAttrib($name);
    }

    /**
     * @param string $method
     * @param array<int, mixed> $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (substr($method, 0, 3) == 'get') {
            $prop = lcfirst(substr($method, 3));
            return $this->$prop;
        }

        if (substr($method, 0, 3) === 'set' && array_key_exists(0, $args)) {
            $prop = lcfirst(substr($method, 3));

            return $this->setOption($prop, $args[0]);
        }

        return null;
    }

    public function __toString()
    {
        $helper = $this->resolveFormRenderHelper();
        return $helper->field($this);
    }

    /**
     * @return \Ufw\Helper\FormRenderHelperBase
     */
    protected function resolveFormRenderHelper()
    {
        $helper = $this->getHelper($this->getRenderHelperName());
        if (!$helper instanceof \Ufw\Helper\FormRenderHelperBase) {
            throw new \RuntimeException(sprintf(
                'Form render helper %s is not available.',
                $this->getRenderHelperName()
            ));
        }
        return $helper;
    }

    /**
     * @param string $message
     * @return $this
     */
    public function addError($message)
    {
        $translated = $this->translate($message);
        $this->errors[] = is_string($translated) ? $translated : $message;
        return $this;
    }

    /**
     * @param array<int|string, string> $messages
     * @return $this
     */
    public function addErrors($messages)
    {
        foreach ($messages as $message) {
            $this->addError($message);
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function clearErrors()
    {
        $this->errors = [];
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($context = null)
    {
        $value = $this->getValue();
        if ($this->getAllowMultiple()) {
            $unfiltered = $this->getValueUnfiltered();
            if (is_array($unfiltered)) {
                $value = $unfiltered;
            }
        }
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
            if ($validator === null) {
                continue;
            }
            if (!$validator->isValid($value, $context)) {
                $result = false;
                $this->addErrors($validator->getErrors());
            }
        }

        return $result;
    }

    /**
     * @param string $name
     * @return \Ufw\Helper\Base|null
     */
    public function getHelper($name)
    {
        $application = \Ufw\Registry::getInstance()->get('application');
        if (!$application instanceof \Ufw\Application) {
            return null;
        }

        return $application->getHelper($name);
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return !!$this->getAttrib('required');
    }

    /**
     * @param string $className
     * @return $this
     */
    public function addClass($className)
    {
        $cls = $this->getAttrib('class');
        $clsStr = is_string($cls) ? $cls : '';
        if (!$clsStr || !preg_match('~(^|\s)' . preg_quote($className, '~') . '($|\s)~', $clsStr)) {
            $this->setAttrib('class', trim("$clsStr $className"));
        }
        return $this;
    }

    /**
     * @param string $className
     * @return $this
     */
    public function removeClass($className)
    {
        $cls = $this->getAttrib('class');
        $clsStr = is_string($cls) ? $cls : '';
        $this->setAttrib('class', preg_replace('~(^|\s)' . preg_quote($className, '~') . '($|\s)~', '$1$2', $clsStr));
        return $this;
    }

    /**
     * @return string|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttribs()
    {
        $attribs = $this->attribs()->getAll();
        if ($this->getAllowMultiple()
            && isset($attribs['name'])
            && is_string($attribs['name'])
            && $attribs['name'] !== ''
            && !preg_match('~\[\]$~', $attribs['name'])
        ) {
            $attribs['name'] = $attribs['name'] . '[]';
        }
        return $attribs;
    }

    /**
     * @return list<string>
     */
    protected function getMultiOptionValues()
    {
        return [];
    }

    /**
     * @return string
     */
    public function getName()
    {
        $name = $this->getAttrib('name');
        return is_string($name) ? $name : '';
    }

    /**
     * @return string
     */
    public function getId()
    {
        $id = $this->getAttrib('id');
        return is_string($id) ? $id : '';
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return $this
     */
    public function setValue($value, $context = null)
    {
        $this->valueUnfiltered = $value;
        if ($this->getFilters()) {
            foreach ($this->getFilterNames() as $filterKey) {
                $filter = $this->getFilter($filterKey);
                if ($filter === null) {
                    continue;
                }
                $value = $filter->filter($value);
            }
        }
        $this->value = $value;

        if ($this->getAllowMultiple()) {
            $multiValues = $this->getMultiOptionValues();
            if ($multiValues) {
                if (is_array($this->value)) {
                    $filtered = array();
                    foreach ($this->value as $selected) {
                        if (in_array($selected, $multiValues)) {
                            $filtered[] = $selected;
                        }
                    }
                    $this->value = $filtered;
                } else {
                    $this->value = in_array($this->value, $multiValues) ? $this->value : null;
                }
            }
        }

        $unfiltered = $this->getValueUnfiltered();
        if ((is_array($unfiltered) && $unfiltered) || (is_scalar($unfiltered) && strlen((string) $unfiltered))) {
            $this->addClass('has-content');
        }

        return $this;
    }

    /**
     * @return \Ufw\Form\Base|null
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @param \Ufw\Form\Base|null $form
     * @return $this
     */
    public function setForm(\Ufw\Form\Base $form = null)
    {
        $this->form = $form;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIgnore()
    {
        return $this->ignore;
    }

    /**
     * @param bool $ignore
     * @return $this
     */
    public function setIgnore($ignore)
    {
        $this->ignore = $ignore;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabelPlacement()
    {
        return $this->labelPlacement;
    }

    /**
     * @param string $labelPlacement
     * @return $this
     */
    public function setLabelPlacement($labelPlacement)
    {
        $this->labelPlacement = $labelPlacement;
        return $this;
    }

    /**
     * @return string
     */
    public function getRenderScript()
    {
        return $this->renderScript ? $this->renderScript : $this->getRenderHelperName() . '/' . $this->renderScriptPath;
    }

    /**
     * @param string $renderScript
     * @return $this
     */
    public function setRenderScript($renderScript)
    {
        $this->renderScript = $renderScript;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowMultiple()
    {
        return $this->allowMultiple;
    }

    /**
     * @param bool $allowMultiple
     * @return $this
     */
    public function setAllowMultiple($allowMultiple)
    {
        $this->allowMultiple = $allowMultiple;
        $this->autoArray = $this->allowMultiple;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getValueUnfiltered()
    {
        return $this->valueUnfiltered;
    }

    /**
     * @param mixed $valueUnfiltered
     * @return $this
     */
    public function setValueUnfiltered($valueUnfiltered)
    {
        $this->valueUnfiltered = $valueUnfiltered;
        return $this;
    }

    /**
     * @return string
     */
    public function getRenderHelperName()
    {
        if (!static::$renderHelperName) {
            $application = \Ufw\Registry::getInstance()->get('application');
            $helperName = 'bootstrap3';
            if ($application instanceof \Ufw\Application) {
                $config = $application->getConfig();
                if (is_array($config) && isset($config['form']) && is_array($config['form'])) {
                    $formConfig = $config['form'];
                    if (isset($formConfig['helper'])
                        && is_string($formConfig['helper'])
                        && $formConfig['helper'] !== ''
                    ) {
                        $helperName = $formConfig['helper'];
                    }
                }
            }
            self::setRenderHelperName($helperName);
        }
        return (string) static::$renderHelperName;
    }

    /**
     * @param string $renderHelperName
     * @return void
     */
    public static function setRenderHelperName($renderHelperName)
    {
        static::$renderHelperName = $renderHelperName;
    }
}
