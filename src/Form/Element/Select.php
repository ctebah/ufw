<?php
namespace Ufw\Form\Element;

/** `<select>` backed by {@see Multi} options. */
class Select extends Multi
{

    /**
     * @var string
     */
    protected $type = 'select';

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/multi-select.php';

    /**
     * @var list<string>
     */
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
        if ((count($this->multiOptions) == 1)) {
            $first = reset($this->multiOptions);
            if (is_string($first) && trim($first) !== '') {
                $this->addClass('has-content');
            }
        }
        return parent::__toString();
    }

}
