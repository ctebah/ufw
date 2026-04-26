<?php
namespace Ufw\Form\Element;

/**
 * Non-input content block inside the form layout (markup-only; {@see $ignore} excludes from submitted values).
 */
class Html extends Base
{

    protected $type = null;

    protected $renderScriptPath = 'form/html.php';
    
    protected $ignore = true;

    protected function getDefaultOptions()
    {
        return [
            'class' => 'form-control has-content',
            'labelClass' => 'control-label'
        ];
    }
}
