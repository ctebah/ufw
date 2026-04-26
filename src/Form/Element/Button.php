<?php
namespace Ufw\Form\Element;

/** Submit/button control; ignored for {@see \Ufw\Form\Base::getValues()}. */
class Button extends Base
{

    protected $type = 'submit';

    protected $ignore = true;

    protected $renderScriptPath = 'form/button.php';

}
