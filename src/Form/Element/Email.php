<?php
namespace Ufw\Form\Element;

/** Email input (`type=email`) with email-oriented validation when added. */
class Email extends Input
{

    protected $type = 'email';

    public function init()
    {
        parent::init();
        $this->addValidator('email');
        return $this;
    }
}
