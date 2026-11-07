<?php
namespace Ufw\Form\Element;

/**
 * Single-line text input (`type=text` by default); ships with trim + singleline filters.
 */
class Input extends Base
{

    /**
     * @var string
     */
    protected $type = 'text';

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/input.php';

    /**
     * @return $this
     */
    public function init()
    {
        $this->addFilter('trim')->addFilter('singleline');
        return $this;
    }
}
