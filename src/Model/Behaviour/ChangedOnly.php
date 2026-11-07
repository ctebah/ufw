<?php
namespace Ufw\Model\Behaviour;

/**
 * On update, write only values that differ from the snapshot taken when the row was read.
 * No snapshot (constructed record, not a fetch) leaves the SET list alone, so the full row is still written.
 *
 * JSON columns listed on a sibling {@see Json} behaviour are compared structurally, not as raw strings.
 */
class ChangedOnly extends Base
{
    public $order = 140;

    /**
     * @param array<string, mixed> $opts
     * @return $this
     */
    public function beforeUpdate(&$opts = [])
    {
        if (array_key_exists('_updateValues', $opts)) {
            return $this;
        }
        $previous = $this->getOwner()->getReadState();
        if (!is_array($previous)) {
            return $this;
        }
        $pk = $this->getOwner()->getPrimaryKeyField();
        if (!is_array($pk)) {
            $pk = $pk ? array($pk) : array();
        }
        $opts['_updateValues'] = self::diffForUpdate(
            $previous,
            $this->getOwner()->getAll(),
            $this->jsonAttributeNames(),
            $pk,
            $this->alwaysValueKeys(),
            !empty($opts['skipDefaultOverrides'])
        );
        return $this;
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $current
     * @param array<int, string> $jsonKeys
     * @param array<int, string> $pkFields
     * @param array<int, string> $alwaysKeys
     * @param bool $skipDefaultOverrides
     * @return array<string, mixed>
     */
    public static function diffForUpdate(array $previous, array $current, array $jsonKeys, array $pkFields, array $alwaysKeys, $skipDefaultOverrides)
    {
        $pkFlip = array_flip($pkFields);
        $jsonFlip = array_flip($jsonKeys);
        $dirty = array();
        foreach ($current as $key => $value) {
            if (isset($pkFlip[$key])) {
                if (!array_key_exists($key, $previous) || self::valuesEqual($previous[$key], $value)) {
                    continue;
                }
            }
            if ($value instanceof \Ufw\Db\Expression) {
                $dirty[$key] = $value;
                continue;
            }
            $existed = array_key_exists($key, $previous);
            $old = $existed ? $previous[$key] : null;
            if (!$existed) {
                $dirty[$key] = $value;
                continue;
            }
            $same = isset($jsonFlip[$key]) ? self::jsonEqual($old, $value) : self::valuesEqual($old, $value);
            if (!$same) {
                $dirty[$key] = $value;
            }
        }

        if ($skipDefaultOverrides && $alwaysKeys) {
            $real = $dirty;
            foreach ($alwaysKeys as $key) {
                unset($real[$key]);
            }
            if (!$real) {
                return array();
            }
            foreach ($alwaysKeys as $key) {
                if (!isset($pkFlip[$key]) && array_key_exists($key, $current)) {
                    $dirty[$key] = $current[$key];
                }
            }
        }

        return $dirty;
    }

    /**
     * Structural compare for a JSON column. Object key order, whitespace and unicode escaping are not changes.
     * List order is a change. 1 and 1.0 are not. null, [] and "" are not equal to each other.
     * PHP decodes both `{}` and `[]` to an empty array, so those two texts compare equal.
     *
     * @param mixed $previous
     * @param mixed $current
     * @return bool
     */
    public static function jsonEqual($previous, $current)
    {
        return self::jsonSame(self::jsonNormalize($previous), self::jsonNormalize($current));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    protected static function jsonNormalize($value)
    {
        if (is_array($value) || $value === null || $value === '' || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $value;
    }

    /**
     * @param mixed $left
     * @param mixed $right
     * @return bool
     */
    protected static function jsonSame($left, $right)
    {
        $leftNumber = is_int($left) || is_float($left);
        $rightNumber = is_int($right) || is_float($right);
        if ($leftNumber || $rightNumber) {
            if ($leftNumber && $rightNumber) {
                if (is_int($left) && is_int($right)) {
                    return $left === $right;
                }
                return (float) $left == (float) $right;
            }
            return false;
        }
        if (is_array($left) && is_array($right)) {
            $leftList = self::isListArray($left);
            $rightList = self::isListArray($right);
            if ($leftList && $rightList) {
                if (count($left) !== count($right)) {
                    return false;
                }
                foreach ($left as $index => $item) {
                    if (!self::jsonSame($item, $right[$index])) {
                        return false;
                    }
                }
                return true;
            }
            if ($leftList || $rightList) {
                return false;
            }
            if (count($left) !== count($right)) {
                return false;
            }
            foreach ($left as $key => $item) {
                if (!array_key_exists($key, $right) || !self::jsonSame($item, $right[$key])) {
                    return false;
                }
            }
            return true;
        }
        if (is_bool($left) || is_bool($right) || $left === null || $right === null || $left === '' || $right === '') {
            return $left === $right;
        }
        return $left === $right;
    }

    /**
     * @param array<mixed> $value
     * @return bool
     */
    protected static function isListArray(array $value)
    {
        if ($value === array()) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param mixed $previous
     * @param mixed $current
     * @return bool
     */
    protected static function valuesEqual($previous, $current)
    {
        if (is_array($previous) || is_array($current)) {
            return self::jsonEqual($previous, $current);
        }
        if ($previous === null || $current === null) {
            return $previous === $current;
        }
        if (is_bool($previous) || is_bool($current)) {
            return $previous === $current;
        }
        if (is_scalar($previous) && is_scalar($current)) {
            return (string) $previous === (string) $current;
        }
        return $previous === $current;
    }

    /**
     * @return array<int, string>
     */
    protected function jsonAttributeNames()
    {
        $names = array();
        foreach ($this->getOwner()->getBehaviours('beforeSave') as $behaviour) {
            if ($behaviour instanceof Json && is_array($behaviour->serialAttributes)) {
                foreach ($behaviour->serialAttributes as $name) {
                    $names[] = $name;
                }
            }
        }
        return $names;
    }

    /**
     * @return array<int, string>
     */
    protected function alwaysValueKeys()
    {
        $keys = array();
        foreach ($this->getOwner()->getBehaviours('beforeSave') as $behaviour) {
            if ($behaviour instanceof DefaultFieldValues && is_array($behaviour->alwaysValues)) {
                foreach (array_keys($behaviour->alwaysValues) as $key) {
                    $keys[] = $key;
                }
            }
        }
        return $keys;
    }
}
