<?php
namespace Ufw\Form\Element;

/** Multi-line text (`<textarea>`). */
class Textarea extends Base
{

    /**
     * @var string
     */
    protected $type = 'textarea';

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
        'rows',
        'cols',
        'data-group',
        'data-group-errmsg',
        'style',
        'readonly',
        'disabled'
    ];

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/textarea.php';
}
