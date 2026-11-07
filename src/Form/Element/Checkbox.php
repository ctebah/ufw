<?php
namespace Ufw\Form\Element;

/**
 * Single checkbox with checked/unchecked value mapping (optional multi-checkbox mode via options).
 */
class Checkbox extends Base
{

    /**
     * @var string
     */
    protected $type = 'checkbox';

    /**
     * @var string
     */
    protected $checkedValue = '1';

    /**
     * @var string
     */
    protected $uncheckedValue = '0';

    /**
     * @var bool
     */
    protected $checked = false;

    /**
     * @var string
     */
    protected $renderScriptPath = 'form/checkbox.php';

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultOptions()
    {
        return [
            'allowMultiple' => $this->getAllowMultiple(),
            'wrapClass' => 'form-group form-group-checkbox checkboxselect',
            'type' => $this->getType(),
            'labelClass' => 'control-label'
        ];
    }

    /**
     * @return string
     */
    public function getCheckedValue()
    {
        return $this->checkedValue;
    }

    /**
     * @param string $checkedValue
     * @return $this
     */
    public function setCheckedValue($checkedValue)
    {
        $this->checkedValue = $checkedValue;
        return $this;
    }

    /**
     * @return string
     */
    public function getUncheckedValue()
    {
        return $this->uncheckedValue;
    }

    /**
     * @param string $uncheckedValue
     * @return $this
     */
    public function setUncheckedValue($uncheckedValue)
    {
        $this->uncheckedValue = $uncheckedValue;
        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return $this
     */
    public function setValue($value, $context = null)
    {
        if ($value == $this->getCheckedValue()) {
            parent::setValue($value, $context);
            $this->setChecked(true);
        } else {
            parent::setValue($this->getUncheckedValue(), $context);
            $this->setChecked(false);
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function getChecked()
    {
        return $this->checked;
    }

    /**
     * @param bool $checked
     * @return $this
     */
    public function setChecked($checked)
    {
        $this->checked = $checked;
        return $this;
    }
}
