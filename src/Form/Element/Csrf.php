<?php

namespace Ufw\Form\Element;

/**
 * CSRF synchronizer field: hidden input via {@see Hidden}; excluded from {@see \Ufw\Form\Base::getValues()} like buttons.
 * Pair with {@see \Ufw\Form\Validator\Csrf} (added automatically). Token storage defaults to {@see \Ufw\Component\Csrf};
 * override {@see self::getCsrfComponentClass()} or pass option <code>csrfComponent</code> (class-string) to use another implementation.
 */
class Csrf extends Hidden
{
    /**
     * @var bool
     */
    protected $ignore = true;

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/csrf.php';

    /**
     * Class whose static {@code token()} / {@code validate()} are used (typically {@see \Ufw\Component\Csrf}).
     *
     * @return class-string
     */
    protected function getCsrfComponentClass(): string
    {
        if ($this->hasOption('csrfComponent')) {
            $c = $this->getOption('csrfComponent');
            if (is_string($c) && $c !== '' && class_exists($c)) {
                return $c;
            }
        }

        return \Ufw\Component\Csrf::class;
    }

    /**
     * @return $this
     */
    public function init()
    {
        $this->setAttrib('class', '');

        $purpose = $this->getOption('purpose');
        $purpose = is_string($purpose) && $purpose !== '' ? $purpose : 'default';

        $componentClass = $this->getCsrfComponentClass();
        $this->setValue($componentClass::token($purpose));
        $this->addValidator(new \Ufw\Form\Validator\Csrf($purpose, $componentClass));

        return $this;
    }
}
