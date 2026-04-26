<?php
namespace Ufw\Form\Element;

/** Multi-line text (`<textarea>`). */
class Textarea extends Base
{

    protected $type = 'textarea';

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

    protected $renderScriptPath = 'form/textarea.php';
}
