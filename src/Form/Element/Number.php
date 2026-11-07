<?php
namespace Ufw\Form\Element;

/** Numeric input (`type=number`). */
class Number extends Input
{

    /**
     * @var string
     */
    protected $type = 'number';

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
        'min',
        'max',
        'step',
        'data-group',
        'data-group-errmsg',
        'style',
        'readonly',
        'disabled',
        'pattern'
    ];
}
