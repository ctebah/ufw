<?php
namespace Ufw\Form\Element;

/** Password input (`type=password`). */
class Password extends Input
{

    protected $type = 'password';
    
    protected $renderScriptPath = 'form/password.php';
}
