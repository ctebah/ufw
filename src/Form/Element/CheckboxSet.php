<?php
namespace Ufw\Form\Element;

/** Multiple checkboxes sharing one name from {@see Multi} options. */
class CheckboxSet extends Multi
{
    /**
     * @var array<int|string, mixed>
     */
    protected $value = array();

    /**
     * @var string
     */
    protected $type = 'checkbox';

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/multi-checkbox.php';

    /**
     * @var bool
     */
    protected $allowMultiple = true;

    /**
     * @return array<string, mixed>
     */
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
