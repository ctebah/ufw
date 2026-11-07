<?php
namespace Ufw\Form\Element;

/** Google reCAPTCHA field: wires {@see Validator\ReCaptcha} with `site_key` / `secret` options. */
class ReCaptcha extends Base
{
    /**
     * @var string
     */
    protected $type = 'text';

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/recaptcha.php';

    /**
     * @var bool|null
     */
    protected $validationResult = null;

    /**
     * @return $this
     */
    public function init()
    {
        $this->addValidator('reCaptcha', [
            'options' => [
                'secret' => $this->getOption('secret'),
                'site_key' => $this->getOption('site_key')
            ]
        ]);
        return parent::init();
    }

    /**
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($context = null)
    {
        if ($this->validationResult === null) {
            $this->validationResult = parent::isValid($context);
        }

        return (bool) $this->validationResult;
    }
}
