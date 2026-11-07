<?php
namespace Ufw\Form\Element;

/** Email input (`type=email`) with email-oriented validation when added. */
class Email extends Input
{

    /**
     * @var string
     */
    protected $type = 'email';

    /**
     * @return $this
     */
    public function init()
    {
        $this->addValidator('email');
        return parent::init();
    }
}
