<?php
namespace Ufw\Form\Element;

/** Radio group from {@see Multi} options. */
class Radio extends Multi
{

    protected $value = array();

    protected $type = 'radio';

    protected $renderScriptPath = 'form/multi-radio.php';

    protected function getDefaultOptions()
    {
        return [
            'allowMultiple' => $this->getAllowMultiple(),
            'wrapClass' => 'form-group form-group-radio radioselect',
            'type' => $this->getType(),
            'labelClass' => 'control-label'
        ];
    }
}
