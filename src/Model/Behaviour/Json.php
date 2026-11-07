<?php
namespace Ufw\Model\Behaviour;

/**
 * JSON columns: {@see $serialAttributes} are decoded on load ({@see beforeDataSet}), encoded before save ({@see beforeSave}),
 * and decoded again after save ({@see afterSave}) so in-memory values stay as arrays when possible.
 */
class Json extends Base
{
    public $order = 130;

    /**
     * Column names, or a non-array when a behaviour config entry is not a list.
     *
     * @var array<int, string>|bool|float|int|string|null
     */
    public $serialAttributes = array();

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    public function beforeDataSet($data, $opts = [])
    {
        if (is_array($this->serialAttributes) && count($this->serialAttributes)) {
            foreach ($this->serialAttributes as $attribute) {
                if (!empty($data[$attribute]) && is_scalar($data[$attribute])) {
                    $a = $this->decodeJsonScalar($data[$attribute]);
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

    /**
     * @param array<string, mixed> $opts
     * @return $this
     */
    public function beforeSave($opts = [])
    {
        if (is_array($this->serialAttributes) && count($this->serialAttributes)) {
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
                        $a = $this->decodeJsonScalar($data[$attribute]);
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
     * Decode the stored JSON string back into an array.
     *
     * @param mixed $success
     * @param array<string, mixed> $opts
     * @return void
     */
    public function afterSave($success, $opts = [])
    {
        if (is_array($this->serialAttributes) && count($this->serialAttributes)) {
            $data = $this->getOwner()->getAll();
            foreach ($this->serialAttributes as $attribute) {
                if (!array_key_exists($attribute, $data)) {
                    continue;
                }
                $_att = $data[$attribute];
                if (!empty($_att) && is_scalar($_att)) {
                    $a = $this->decodeJsonScalar($_att);
                    if ($a !== false) {
                        $this->getOwner()->$attribute = $a;
                    } else {
                        $this->getOwner()->$attribute = null;
                    }
                }
            }
        }
    }

    /**
     * json_decode() wants a string. PHP 7.4 coerced other scalars; PHP 8 throws.
     * Casting first keeps the old coercion and stays valid on both.
     *
     * @param bool|float|int|string $value
     * @return mixed
     */
    protected function decodeJsonScalar($value)
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        return @json_decode($value, true);
    }
}