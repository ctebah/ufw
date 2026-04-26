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

    protected $defaultOptionsToAttribs = [
        'action',
        'class',
        'method',
        'name',
        'id',
        'style'
    ];

    protected static $renderHelperName = null;

    /**
     * When true, {@see getViewScript()} derives a partial path from {@see static::class} if the
     * `viewScript` option is not set. Subclasses may set this to true for convention-based partial
     * paths; the default keeps the form render helper's stock form layout when no explicit script is set.
     *
     * @var bool
     */
    protected $autoViewScript = false;

    protected $validated = false;

    public function __construct(array $options = null)
    {
        $this->setOptionsToAttribs($this->defaultOptionsToAttribs);
        $this->setOptions($options);
        $this->preInit();
        $this->init();
        $this->postInit();
    }

    protected function preInit()
    {
        if (null === $this->getAttrib('id')) {
            $id = trim(strtolower(preg_replace(array(
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

    protected function init()
    {
        return $this;
    }

    protected function postInit()
    {
        if (!$this->getAttrib('method')) {
            $this->setAttrib('method', 'post');
        }
        if (!$this->getAttrib('action')) {
            // Falsy $routeName lets {@see \Ufw\Helper\Url::url()} use {@see \Ufw\Router::getCurrentRoute()}; see there.
            $routeName = $this->getApplication()->getRouter()->getCurrentRouteName() ?: null;
            if ($this->getMethod() == 'post') {
                // Same route as current request so module-prefixed paths (e.g. staff) are not built with the default route.
                $qs = $_SERVER['QUERY_STRING'] ?? '';
                $this->setAction($this->getHelper('url')
                    ->url([], false, false, $routeName) . ($qs !== '' ? '?' . $qs : ''));
            } else {
                $this->setAction($this->getHelper('url')
                    ->url([], false, false, $routeName));
            }
        }
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
        if ($value instanceof \Ufw\Form\Element\Base) {
            return $this->setElement($name, $value);
        }
    }

    public function __isset($name): bool
    {
        return $this->hasOption($name) || $this->hasElement($name) || $this->hasAttrib($name);
    }

    public function __toString()
    {
        return $this->getHelper($this->getRenderHelperName())
            ->form($this);
    }

    public function getForm()
    {
        return $this;
    }

    public function getId()
    {
        return $this->getAttrib('id');
    }

    public function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    /**
     *
     * @return \Ufw\Application
     */
    public function getApplication()
    {
        return \Ufw\Registry::getInstance()->get('application');
    }

    public function populate($values)
    {
        foreach ($this->getElements() as $element) {
            if (strpos($element->getName(), '[')) {
                $val = $values;
                $found = true;
                foreach (preg_split('~[\[\]]~', $element->getName(), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    if (isset($val[$part])) {
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
                if (isset($values[$element->getName()])) {
                    $element->setValue($values[$element->getName()], $values);
                }
            }
        }
        return $this;
    }

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
     *
     * @return string
     */
    public function getAction()
    {
        return $this->getAttrib('action');
    }

    /**
     *
     * @param string $action
     */
    public function setAction($action)
    {
        $this->setAttrib('action', $action);
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->getAttrib('method');
    }

    /**
     *
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->setAttrib('method', $method);
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function getValidated()
    {
        return $this->validated;
    }

    /**
     *
     * @param boolean $validated
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
            return $this->getOption('viewScript');
        }
        if ($this->getAutoViewScript()) {
            return strtolower(preg_replace([
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
