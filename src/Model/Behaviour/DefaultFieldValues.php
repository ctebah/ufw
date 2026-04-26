<?php
namespace Ufw\Model\Behaviour;

/**
 * Supplies missing field values before save: {@see $insertValues} / {@see $updateValues} by new vs existing row,
 * {@see $values} for either, {@see $alwaysValues} unless `$opts['skipDefaultOverrides']` is set. Values may be callables `(owner)`.
 */
class DefaultFieldValues extends Base
{

    public $values = [];
    
    public $alwaysValues = [];
    
    public $insertValues = [];

    public $updateValues = [];

    public function beforeSave($opts = [])
    {
        if ($this->getOwner()->getIsNewRecord()) {
            foreach (array_diff_key($this->insertValues, $this->getOwner()->getAll()) as $key => $value) {
                $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
            }
        } else {
            foreach (array_diff_key($this->updateValues, $this->getOwner()->getAll()) as $key => $value) {
                $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
            }
        }

        foreach (array_diff_key($this->values, $this->getOwner()->getAll()) as $key => $value) {
            $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
        }
        
        if (!$opts || !$opts['skipDefaultOverrides']) {
            foreach ($this->alwaysValues as $key => $value) {
                $this->getOwner()->$key = is_callable($value) ? $value($this->getOwner()) : $value;
            }
        }
        
        return $this;
    }
}
