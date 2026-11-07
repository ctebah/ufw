<?php
namespace Ufw\Form\Element;

/** Radio group from {@see Multi} options. */
class Radio extends Multi
{

    /**
     * @var array<int|string, mixed>
     */
    protected $value = array();

    /**
     * @var string
     */
    protected $type = 'radio';

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/multi-radio.php';

    /**
     * @return array<string, mixed>
     */
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
