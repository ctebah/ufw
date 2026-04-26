<?php
namespace Ufw\Form\Element;

/**
 * Single-line text input (`type=text` by default); ships with trim + singleline filters.
 */
class Input extends Base
{

    protected $type = 'text';

    protected $renderScriptPath = 'form/input.php';

    public function init()
    {
        $this->addFilter('trim')->addFilter('singleline');
        return $this;
    }
}
