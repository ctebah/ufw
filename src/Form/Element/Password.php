<?php
namespace Ufw\Form\Element;

/** Password input (`type=password`). */
class Password extends Input
{

    /**
     * @var string
     */
    protected $type = 'password';

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/password.php';
}
