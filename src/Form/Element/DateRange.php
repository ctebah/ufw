<?php
namespace Ufw\Form\Element;

/**
 * Start/end date pair on one field (daterange partial + `data-date-*` attributes).
 */
class DateRange extends Input
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
        'pattern'
    ];

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/daterange.php';

    /**
     * @return $this
     */
    public function init()
    {
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return mixed
     */
    public function getValueFrom()
    {
        $value = $this->value;
        if (!is_array($value)) {
            return null;
        }
        return $value['from'] ?? null;
    }

    /**
     * @return mixed
     */
    public function getValueTo()
    {
        $value = $this->value;
        if (!is_array($value)) {
            return null;
        }
        return $value['to'] ?? null;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return $this
     */
    public function setValue($value, $context = null)
    {
        if (is_array($value)) {
            $format = $this->getOption('phpdateformat');
            $formatStr = is_string($format) ? $format : 'd. m. Y.';
            foreach ([
                'from',
                'to'
            ] as $type) {
                if (isset($value[$type]) && is_string($value[$type]) && preg_match('~\d{4}-\d{2}-\d{2}~', $value[$type])) {
                    $dt = \DateTime::createFromFormat('Y-m-d', $value[$type]);
                    if ($dt instanceof \DateTime) {
                        $value[$type] = $dt->format($formatStr);
                    }
                }
            }
        }
        return parent::setValue($value, $context);
    }

    /**
     * @return array<string, string>
     */
    public function getValueRaw()
    {
        $out = [];
        $format = $this->getOption('phpdateformat');
        $formatStr = is_string($format) ? $format : 'd. m. Y.';
        $value = $this->value;
        if (!is_array($value)) {
            return [
                'from' => '',
                'to' => '',
            ];
        }
        foreach ([
            'from',
            'to'
        ] as $type) {
            $part = isset($value[$type]) && is_string($value[$type]) ? $value[$type] : '';
            $date = $part !== '' ? \DateTime::createFromFormat($formatStr, $part) : false;
            $out[$type] = $date instanceof \DateTime ? $date->format('Y-m-d') : '';
        }
        return $out;
    }
}
