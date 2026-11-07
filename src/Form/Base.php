<?php

namespace Ufw\Form;

use Ufw\Form\Element\SubElementsInterface;

/**
 * Abstract form: child {@see Element\Base} elements, attributes/options, filters/validators, population and
 * {@see isValid()}, plus view script resolution and rendering through the configured form render helper.
 */
abstract class Base
{
    use \Ufw\Form\Attribs, \Ufw\Form\Options, \Ufw\Form\Elements, \Ufw\Form\Translate;

    /**
     * @var list<string>
     */
    protected $defaultOptionsToAttribs = [
        'action',
        'class',
        'method',
        'name',
        'id',
        'style'
    ];

    /**
     * @var string|null
     */
    protected static $renderHelperName = null;

    /**
     * When true, {@see getViewScript()} derives a partial path from {@see static::class} if the
     * `viewScript` option is not set. Subclasses may set this to true for convention-based partial
     * paths; the default keeps the form render helper's stock form layout when no explicit script is set.
     *
     * @var bool
     */
    protected $autoViewScript = false;

    /**
     * @var bool
     */
    protected $validated = false;

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(array $options = null)
    {
        $this->setOptionsToAttribs($this->defaultOptionsToAttribs);
        $this->setOptions($options);
        $this->preInit();
        $this->init();
        $this->postInit();
    }

    /**
     * @return $this
     */
    protected function preInit()
    {
        if (null === $this->getAttrib('id')) {
            $id = trim(strtolower((string) preg_replace(array(
                '~^(Ufw\\\\Form\\\\|Form\\\\)~i',
                '~([A-Z][^A-Z])~',
                '~[\\\-_]{1,}~'
            ), array(
                '',
                '-$1',
                '-'
            ), get_class($this))), '-');
            $this->setAttrib('id', $id . '-form');
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function init()
    {
        return $this;
    }

    /**
     * @return $this
     */
    protected function postInit()
    {
        if (!$this->getAttrib('method')) {
            $this->setAttrib('method', 'post');
        }
        if (!$this->getAttrib('action')) {
            // Falsy $routeName lets {@see \Ufw\Helper\Url::url()} use {@see \Ufw\Router::getCurrentRoute()}; see there.
            $routeName = $this->getApplication()->getRouter()->getCurrentRouteName() ?: null;
            $urlHelper = $this->getHelper('url');
            if ($urlHelper === null) {
                return $this;
            }
            if ($this->getMethod() == 'post') {
                // Same route as current request so module-prefixed paths (e.g. staff) are not built with the default route.
                $qs = '';
                if (isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING'])) {
                    $qs = $_SERVER['QUERY_STRING'];
                }
                $this->setAction($urlHelper->url([], false, false, $routeName) . ($qs !== '' ? '?' . $qs : ''));
            } else {
                $this->setAction($urlHelper->url([], false, false, $routeName));
            }
        }
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
        if ($this->hasElement($name)) {
            return $this->getElement($name);
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
            return;
        }
        if ($value instanceof \Ufw\Form\Element\Base) {
            $this->setElement($name, $value);
        }
    }

    /**
     * @param string $name
     */
    public function __isset($name): bool
    {
        return $this->hasOption($name) || $this->hasElement($name) || $this->hasAttrib($name);
    }

    public function __toString()
    {
        $helper = $this->resolveFormRenderHelper();
        return $helper->form($this);
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
     * @return $this
     */
    public function getForm()
    {
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->getAttrib('id');
    }

    /**
     * @param string $name
     * @return \Ufw\Helper\Base|null
     */
    public function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    /**
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        $application = \Ufw\Registry::getInstance()->get('application');
        if (!$application instanceof \Ufw\Application) {
            throw new \RuntimeException('Application is not registered.');
        }
        return $application;
    }

    /**
     * @param array<string, mixed> $values
     * @return $this
     */
    public function populate($values)
    {
        foreach ($this->getElements() as $element) {
            $elementName = $element->getName();
            if (strpos($elementName, '[') !== false) {
                $val = $values;
                $found = true;
                $parts = $this->splitBracketElementNameParts($elementName);
                foreach ($parts as $part) {
                    if (is_array($val) && isset($val[$part])) {
                        $val = $val[$part];
                    } else {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    $element->setValue($val, $values);
                }
            } else {
                if (isset($values[$elementName])) {
                    $element->setValue($values[$elementName], $values);
                }
            }
        }
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValues()
    {
        $out = [];
        foreach ($this->getElements() as $element) {
            if (!$element->getIgnore()) {
                $out[$element->getName()] = $element->getValue();
                if ($element instanceof SubElementsInterface) {
                    foreach ($element->yieldSubValues() as $key => $value) {
                        $out[$key] = $value;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        $valid = true;
        $context = $this->getValues();
        foreach ($this->getElements() as $element) {
            $validElement = $element->isValid($context);
            $valid = $valid && $validElement; // avoid optimization
        }
        $this->validated = true;
        return $valid;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors()
    {
        $errors = [];
        foreach ($this->getElements() as $key => $element) {
            $err = $element->getErrors();
            if ($err) {
                $errors[$key] = $err;
            }
        }
        return $errors;
    }

    /**
     * @return string|null
     */
    public function getAction()
    {
        $action = $this->getAttrib('action');
        return is_string($action) ? $action : null;
    }

    /**
     * @param string $action
     * @return $this
     */
    public function setAction($action)
    {
        $this->setAttrib('action', $action);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getMethod()
    {
        $method = $this->getAttrib('method');
        return is_string($method) ? $method : null;
    }

    /**
     * @param string $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->setAttrib('method', $method);
        return $this;
    }

    /**
     * @return bool
     */
    public function getValidated()
    {
        return $this->validated;
    }

    /**
     * @param bool $validated
     * @return $this
     */
    public function setValidated($validated)
    {
        $this->validated = $validated;
        return $this;
    }

    /**
     * View script path for the form (relative to the application's `views/partials/` tree).
     *
     * Resolution order:
     * 1. If the `viewScript` option is set, that value wins (full override).
     * 2. Otherwise, when {@see $autoViewScript} is true, the path is derived from the class name
     *    (`static::class`): namespace `\` → `/`, PascalCase segments → kebab-case, lowercased,
     *    with the `.html.php` suffix.
     * 3. If auto view is off and there is no `viewScript`, returns null (form render helper uses its
     *    default layout partial).
     *
     * Example: `Form\Admin\User` → `form/admin/user.html.php`
     *
     * @return string|null
     */
    public function getViewScript()
    {
        if ($this->options()->get('viewScript')) {
            $viewScript = $this->getOption('viewScript');
            return is_string($viewScript) ? $viewScript : null;
        }
        if ($this->getAutoViewScript()) {
            return strtolower((string) preg_replace([
                '~(?<=(?:[A-Z]))([A-Z]+)([A-Z][A-z])~',
                '~(?<=(?:[a-z0-9]))([A-Z])~',
                '~\\\\~'
            ], [
                '$1-$2',
                '-$1',
                '/'
            ], static::class)) . '.html.php';
        }
        return null;
    }

    /**
     * @param string $path Partial path under `views/partials/`
     * @return $this
     */
    public function setViewScript($path)
    {
        return $this->setOption('viewScript', $path);
    }

    /**
     * @return bool
     */
    public function getAutoViewScript()
    {
        return $this->autoViewScript;
    }

    /**
     * @param bool $autoViewScript
     * @return $this
     */
    public function setAutoViewScript($autoViewScript)
    {
        $this->autoViewScript = (bool) $autoViewScript;
        return $this;
    }

    /**
     * Helper name used with {@see \Ufw\Application::getHelper()} for form rendering (e.g. `bootstrap5`).
     * Implementations typically extend {@see \Ufw\Helper\FormRenderHelperBase}.
     *
     * @return string
     */
    public function getRenderHelperName()
    {
        if (!static::$renderHelperName) {
            $helperName = 'bootstrap3';
            $application = \Ufw\Registry::getInstance()->get('application');
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

    /**
     * @param string $elementName
     * @return list<string>
     */
    protected function splitBracketElementNameParts($elementName)
    {
        $parts = $this->pregSplitBracketElementName($elementName);
        if ($parts === false) {
            return array();
        }

        return $parts;
    }

    /**
     * @param string $elementName
     * @return list<string>|false
     */
    protected function pregSplitBracketElementName($elementName)
    {
        return preg_split('~[\[\]]~', $elementName, -1, PREG_SPLIT_NO_EMPTY);
    }
}
