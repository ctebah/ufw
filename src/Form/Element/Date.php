<?php
namespace Ufw\Form\Element;

/** Date input (`type=date` or text with date widget). */
class Date extends Input
{
    /**
     * @var string
     */
    protected $type = 'text';

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
        'data-date-format',
        'data-date-end-date',
        'data-group',
        'data-group-errmsg',
        'style',
        'readonly',
        'disabled',
        'pattern',
    ];

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return $this
     */
    public function setValue($value, $context = null)
    {
        if (is_string($value) && preg_match('~\d{4}-\d{2}-\d{2}~', $value)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $value);
            $format = $this->getOption('phpdateformat');
            if ($dt && is_string($format)) {
                $value = $dt->format($format);
            }
        }
        return parent::setValue($value, $context);
    }

    /**
     * @return string
     */
    public function getValueRaw()
    {
        $format = $this->getOption('phpdateformat');
        $formatStr = is_string($format) ? $format : 'd. m. Y.';
        $value = $this->value;
        if (!is_string($value)) {
            return '';
        }
        $date = \DateTime::createFromFormat($formatStr, $value);
        return $date instanceof \DateTime ? $date->format('Y-m-d') : '';
    }
}
