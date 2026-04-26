<?php
namespace Ufw\Form;

/**
 * Optional i18n: uses {@see \Ufw\Registry} `translator` or a per-object callable for labels, messages, and translatable attribs/options.
 */
trait Translate {

    /**
     *
     * @var boolean
     */
    protected $disableTranslator = false;

    protected $translator = null;

    public function translate($str)
    {
        if (!$str || !$this->hasTranslator()) {
            return $str;
        }
        $translator = $this->getTranslator();
        if (is_array($str)) {
            $out = [];
            foreach ($str as $key => $value) {
                $out[$key] = $translator($value);
            }
            return $out;
        } else {
            return $translator($str);
        }
    }

    public function hasTranslator()
    {
        return !$this->disableTranslator && $this->getTranslator();
    }

    public function getTranslator()
    {
        return $this->translator ? $this->translator : \Ufw\Registry::getInstance()->get('translator');
    }

    /**
     *
     * @return boolean
     */
    public function getDisableTranslator()
    {
        return $this->disableTranslator;
    }

    /**
     *
     * @param boolean $disableTranslator
     */
    public function setDisableTranslator($disableTranslator)
    {
        $this->disableTranslator = $disableTranslator;
        return $this;
    }
}
