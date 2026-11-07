<?php
namespace Ufw\Form\Element;

/**
 * Non-input content block inside the form layout (markup-only; {@see $ignore} excludes from submitted values).
 */
class Html extends Base
{

    /**
     * @var string|null
     */
    protected $type = null;

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/html.php';

    /**
     * @var bool
     */
    protected $ignore = true;

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultOptions()
    {
        return [
            'class' => 'form-control has-content',
            'labelClass' => 'control-label'
        ];
    }
}
