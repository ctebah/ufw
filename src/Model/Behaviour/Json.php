<?php
namespace Ufw\Model\Behaviour;

/**
 * JSON columns: {@see $serialAttributes} are decoded on load ({@see beforeDataSet}), encoded before save ({@see beforeSave}),
 * and decoded again after save ({@see afterSave}) so in-memory values stay as arrays when possible.
 */
class Json extends Base
{

    public $serialAttributes = array();

    public function beforeDataSet($data, $opts = [])
    {
        if (count($this->serialAttributes)) {
            foreach ($this->serialAttributes as $attribute) {
                if (!empty($data[$attribute]) && is_scalar($data[$attribute])) {
                    $a = @json_decode($data[$attribute], true);
                    if ($a !== false) {
                        $data[$attribute] = $a;
                    } else {
                        $data[$attribute] = array();
                    }
                }
            }
        }
        return $data;
    }

    public function beforeSave($opts = [])
    {
        if (count($this->serialAttributes)) {
            $data = $this->getOwner()->getAll();
            foreach ($this->serialAttributes as $attribute) {
                if (!array_key_exists($attribute, $data)) {
                    continue;
                }
                
                // check if the attribute is an array, and serialize it
                if (is_array($data[$attribute])) {
                    $this->getOwner()->$attribute = json_encode($data[$attribute], JSON_UNESCAPED_UNICODE);
                } else {
                    // if its a string, lets see if its unserializable, if not
                    // set it to null
                    if (is_scalar($data[$attribute])) {
                        $a = @json_decode($data[$attribute], true);
                        if ($a === false) {
                            $this->getOwner()->$attribute = null;
                        }
                    }
                }
            }
        }
        return $this;
    }

    /**
     * convert the saved as a serialized string back into an array, cause
     * thats how we want to use it anyways ya know?
     */
    public function afterSave($success, $opts = [])
    {
        if (count($this->serialAttributes)) {
            $data = $this->getOwner()->getAll();
            foreach ($this->serialAttributes as $attribute) {
                if (!array_key_exists($attribute, $data)) {
                    continue;
                }
                $_att = $data[$attribute];
                if (!empty($_att) && is_scalar($_att)) {
                    $a = @json_decode($_att, true);
                    if ($a !== false) {
                        $this->getOwner()->$attribute = $a;
                    } else {
                        $this->getOwner()->$attribute = null;
                    }
                }
            }
        }
    }
}