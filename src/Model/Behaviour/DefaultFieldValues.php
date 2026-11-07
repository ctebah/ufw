<?php
namespace Ufw\Model\Behaviour;

/**
 * Supplies missing field values before save: {@see $insertValues} / {@see $updateValues} by new vs existing row,
 * {@see $values} for either, {@see $alwaysValues} unless `$opts['skipDefaultOverrides']` is set. Values may be callables `(owner)`.
 */
class DefaultFieldValues extends Base
{
    public $order = 110;

    /**
     * @var array<string, mixed>|bool|float|int|string|null
     */
    public $values = [];

    /**
     * @var array<string, mixed>|bool|float|int|string|null
     */
    public $alwaysValues = [];

    /**
     * @var array<string, mixed>|bool|float|int|string|null
     */
    public $insertValues = [];

    /**
     * @var array<string, mixed>|bool|float|int|string|null
     */
    public $updateValues = [];

    /**
     * @param array<string, mixed> $opts
     * @return $this
     */
    public function beforeSave($opts = [])
    {
        if ($this->getOwner()->getIsNewRecord()) {
            if (is_array($this->insertValues)) {
                foreach (array_diff_key($this->insertValues, $this->getOwner()->getAll()) as $key => $value) {
                    $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
                }
            }
        } elseif (is_array($this->updateValues)) {
            foreach (array_diff_key($this->updateValues, $this->getOwner()->getAll()) as $key => $value) {
                $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
            }
        }

        if (is_array($this->values)) {
            foreach (array_diff_key($this->values, $this->getOwner()->getAll()) as $key => $value) {
                $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
            }
        }

        if ((!$opts || !$opts['skipDefaultOverrides']) && is_array($this->alwaysValues)) {
            foreach ($this->alwaysValues as $key => $value) {
                $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
            }
        }
        
        return $this;
    }
}
