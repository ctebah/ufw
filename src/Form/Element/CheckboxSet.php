<?php
namespace Ufw\Form\Element;

/** Multiple checkboxes sharing one name from {@see Multi} options. */
class CheckboxSet extends Multi
{
    protected $value = array();

    protected $type = 'checkbox';

    protected $renderScriptPath = 'form/multi-checkbox.php';

    protected $allowMultiple = true;

    protected function getDefaultOptions()
    {
        return [
            'allowMultiple' => $this->getAllowMultiple(),
            'wrapClass' => 'form-group form-group-checkbox checkboxselect',
            'type' => $this->getType(),
            'labelClass' => 'control-label'
        ];
    }
}
