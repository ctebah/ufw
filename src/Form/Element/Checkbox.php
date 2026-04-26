<?php
namespace Ufw\Form\Element;

/**
 * Single checkbox with checked/unchecked value mapping (optional multi-checkbox mode via options).
 */
class Checkbox extends Base
{

    protected $type = 'checkbox';

    protected $checkedValue = '1';

    protected $uncheckedValue = '0';

    protected $checked = false;

    protected $renderScriptPath = 'form/checkbox.php';

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
     *
     * @return string
     */
    public function getCheckedValue()
    {
        return $this->checkedValue;
    }

    /**
     *
     * @param string $checkedValue
     */
    public function setCheckedValue($checkedValue)
    {
        $this->checkedValue = $checkedValue;
        return $this;
    }

    /**
     *
     * @return string
     */
    public function getUncheckedValue()
    {
        return $this->uncheckedValue;
    }

    /**
     *
     * @param string $uncheckedValue
     */
    public function setUncheckedValue($uncheckedValue)
    {
        $this->uncheckedValue = $uncheckedValue;
        return $this;
    }

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
     *
     * @return boolean
     */
    public function getChecked()
    {
        return $this->checked;
    }

    /**
     *
     * @param boolean $checked
     */
    public function setChecked($checked)
    {
        $this->checked = $checked;
        return $this;
    }
}
