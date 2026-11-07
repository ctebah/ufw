<?php
namespace Ufw\Form\Element;

/** Submit/button control; ignored for {@see \Ufw\Form\Base::getValues()}. */
class Button extends Base
{

    /**
     * @var string
     */
    protected $type = 'submit';

    /**
     * @var bool
     */
    protected $ignore = true;

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/button.php';

}
