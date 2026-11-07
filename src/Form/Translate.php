<?php
namespace Ufw\Form;

/**
 * Optional i18n: uses {@see \Ufw\Registry} `translator` or a per-object callable for labels, messages, and translatable attribs/options.
 */
trait Translate {

    /**
     * @var bool
     */
    protected $disableTranslator = false;

    /**
     * @var callable|null
     */
    protected $translator = null;

    /**
     * @param mixed $str
     * @return mixed
     */
    public function translate($str)
    {
        if (!$str || !$this->hasTranslator()) {
            return $str;
        }
        $translator = $this->getTranslator();
        if (!is_callable($translator)) {
            return $str;
        }
        if (is_array($str)) {
            $out = [];
            foreach ($str as $key => $value) {
                $out[$key] = $translator($value);
            }
            return $out;
        }

        return $translator($str);
    }

    /**
     * @return bool
     */
    public function hasTranslator()
    {
        return !$this->disableTranslator && $this->getTranslator();
    }

    /**
     * @return callable|null
     */
    public function getTranslator()
    {
        if ($this->translator !== null) {
            return $this->translator;
        }
        $fromRegistry = \Ufw\Registry::getInstance()->get('translator');
        return is_callable($fromRegistry) ? $fromRegistry : null;
    }

    /**
     * @return bool
     */
    public function getDisableTranslator()
    {
        return $this->disableTranslator;
    }

    /**
     * @param bool $disableTranslator
     * @return $this
     */
    public function setDisableTranslator($disableTranslator)
    {
        $this->disableTranslator = $disableTranslator;
        return $this;
    }
}
