<?php
namespace Ufw\Form\Element;

/**
 * Control with a fixed option list (select, radio groups, checkbox sets): validates values against {@see getMultiOptionValues()}.
 */
abstract class Multi extends Base
{

    /**
     * @var array<int|string, mixed>
     */
    protected $multiOptions = array();

    /**
     * @return $this
     */
    public function init()
    {
        $this->addValidator('inArray', [
            'options' => [
                'haystack' => $this->getMultiOptionValues()
            ]
        ]);
        return parent::init();
    }

    /**
     * @return list<int|string>
     */
    public function getMultiOptionValues()
    {
        return array_keys($this->multiOptions);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getMultiOptions()
    {
        $options = $this->translate($this->multiOptions);
        return is_array($options) ? $options : $this->multiOptions;
    }

    /**
     * @param array<int|string, mixed> $options
     * @return $this
     */
    public function setMultiOptions(array $options)
    {
        $this->clearMultiOptions();
        return $this->addMultiOptions($options);
    }

    /**
     * @param array<int|string, mixed> $options
     * @return $this
     */
    public function addMultiOptions(array $options)
    {
        foreach ($options as $option => $value) {
            if (is_array($value) && array_key_exists('key', $value) && array_key_exists('value', $value)) {
                $optionKey = $value['key'];
                if (is_int($optionKey) || is_string($optionKey)) {
                    $this->addMultiOption($optionKey, $value['value']);
                }
            } else {
                $this->addMultiOption($option, $value);
            }
        }
        return $this;
    }

    /**
     * @param string|int $option
     * @param mixed $value
     * @return $this
     */
    public function addMultiOption($option, $value = '')
    {
        $option = (string) $option;
        $this->multiOptions[$option] = $value;
        return $this;
    }

    /**
     * @return $this
     */
    public function clearMultiOptions()
    {
        $this->multiOptions = array();
        return $this;
    }
}
