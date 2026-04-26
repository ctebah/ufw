<?php
namespace Ufw\Form\Element;

/** Date input (`type=date` or text with date widget). */
class Date extends Input
{
    protected $type = 'text';

    protected $defaultOptionsToAttribs = [
        'name',
        'id',
        'placeholder',
        'class',
        'type',
        'required',
        'maxlength',
        'data-date-format',
        'data-date-end-date',
        'data-group',
        'data-group-errmsg',
        'style',
        'readonly',
        'disabled',
        'pattern',
    ];

    protected function getDefaultOptions()
    {
        return [
            'allowMultiple' => $this->getAllowMultiple(),
            'class' => 'form-control datepicker',
            'data-date-format' => 'dd. mm. yyyy.',
            'phpdateformat' => 'd. m. Y.',
            'type' => $this->getType(),
            'labelClass' => 'control-label'
        
        ];
    }

    public function setValue($value, $context = null)
    {
        if (preg_match('~\d{4}-\d{2}-\d{2}~', $value)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $value);
            if ($dt) {
                $value = $dt->format($this->phpdateformat);
            }
        }
        return parent::setValue($value, $context);
    }

    public function getValueRaw()
    {
        $date = \DateTime::createFromFormat($this->phpdateformat, $this->value);
        $out = $date instanceof \DateTime ? $date->format('Y-m-d') : '';
        return $out;
    }
}
