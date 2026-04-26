<?php
namespace Ufw\Form\Element;

/** Google reCAPTCHA field: wires {@see Validator\ReCaptcha} with `site_key` / `secret` options. */
class ReCaptcha extends Base
{
    protected $type = 'text';

    protected $renderScriptPath = 'form/recaptcha.php';

    protected $validationResult;

    public function init()
    {
        parent::init();
        $this->addValidator('reCaptcha', [
            'options' => [
                'secret' => $this->getOption('secret'),
                'site_key' => $this->getOption('site_key')
            ]
        ]);
        return $this;
    }

    public function isValid($context = null)
    {
        if (is_null($this->validationResult)) {
            $this->validationResult = parent::isValid($context);
        }
        
        return $this->validationResult;
    }
}
