<?php
namespace Ufw\Form\Element;

/** Numeric input (`type=number`). */
class Number extends Input
{

    protected $type = 'number';

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
