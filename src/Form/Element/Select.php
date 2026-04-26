<?php
namespace Ufw\Form\Element;

/** `<select>` backed by {@see Multi} options. */
class Select extends Multi
{

    protected $type = 'select';

    protected $renderScriptPath = 'form/multi-select.php';
    
    protected $defaultOptionsToAttribs = [
        'name',
        'id',
        'placeholder',
        'class',
        'type',
        'required',
        'maxlength',
        'title',
        'data-group',
        'data-group-errmsg',
        'style',
        'readonly',
        'disabled',
        'pattern',
        'size',
        'multiple'
    ];
    
    
    public function __toString()
    {
        if ((count($this->multiOptions) == 1) && trim(reset($this->multiOptions))) {
            $this->addClass('has-content');
        }
        return parent::__toString();
    }
    
}
