<?php
namespace Ufw\Form\Element;

/**
 * Start/end date pair on one field (daterange partial + `data-date-*` attributes).
 */
class DateRange extends Input
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
        'pattern'
    ];

    protected $renderScriptPath = 'form/daterange.php';

    public function init()
    {
        return $this;
    }

    protected function getDefaultOptions()
    {
        return [
            'allowMultiple' => $this->getAllowMultiple(),
            'class' => 'form-control',
            'data-date-format' => 'dd. mm. yyyy.',
            'phpdateformat' => 'd. m. Y.',
            'type' => $this->getType(),
            'labelClass' => 'control-label'
        ];
    }

    public function getValueFrom()
    {
        return @$this->value['from'];
    }

    public function getValueTo()
    {
        return @$this->value['to'];
    }

    public function setValue($value, $context = null)
    {
        foreach ([
            'from',
            'to'
        ] as $type) {
            if (isset($value[$type])) {
                if (preg_match('~\d{4}-\d{2}-\d{2}~', $value[$type])) {
                    $value[$type] = \DateTime::createFromFormat('Y-m-d', $value[$type])->format($this->phpdateformat);
                }
            }
        }
        return parent::setValue($value, $context);
    }

    public function getValueRaw()
    {
        $out = [];
        foreach ([
            'from',
            'to'
        ] as $type) {
            $date = \DateTime::createFromFormat($this->phpdateformat, $this->value[$type]);
            $out[$type] = $date instanceof \DateTime ? $date->format('Y-m-d') : '';
        }
        return $out;
    }
}
