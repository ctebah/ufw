<?php
namespace Ufw\Component\ApiClient;

/**
 * Payload builder: maps form keys through {@see \Ufw\Component\ApiClient\Base::getFieldsMap()} and nested paths for JSON bodies.
 *
 * @method static Helper getInstance()
 */
class Helper
{
    use \Ufw\Singleton;

    /**
     * @param \Ufw\Component\ApiClient\Base $ref
     * @param list<string>|array<int, string> $fields
     * @param \Ufw\Form\Base|array<string, mixed>|null $form
     * @param array<string, mixed>|null $map
     * @return array<string, mixed>
     */
    public function createPayload($ref, $fields, $form = null, $map = null)
    {
        $payload = array();
        if (!$form) {
            $form = $ref->getForm();
        }
        if (!$map) {
            $map = $ref->getFieldsMap();
        }
        foreach ($fields as $key) {
            $mkey = $this->getMappedKey($map, $key);
            $value = $this->_getElementValue($mkey, $form);
            $payload = $this->assignValueToKey($payload, $key, $value);
        }
        
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string|array<int, string> $key
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function assignValueToKey($payload, $key, $value)
    {
        $path = array();
        if (is_array($key)) {
            foreach ($key as $partKey) {
                if ($partKey !== '') {
                    $path[] = $partKey;
                }
            }
        } else {
            $partKeys = $this->splitBracketNotationParts($key);
            foreach ($partKeys as $partKey) {
                $path[] = $partKey;
            }
        }

        return $this->assignValueAtPath($payload, $path, $value);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $partKeys
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function assignValueAtPath(array $payload, array $partKeys, $value)
    {
        if ($partKeys === array()) {
            return $payload;
        }
        $head = array_shift($partKeys);
        if ($partKeys === array()) {
            $payload[$head] = $value;

            return $payload;
        }
        $nested = array();
        if (isset($payload[$head]) && is_array($payload[$head])) {
            $nested = $this->normalizeStringKeyedArray($payload[$head]);
        }
        $payload[$head] = $this->assignValueAtPath($nested, $partKeys, $value);

        return $payload;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function normalizeStringKeyedArray($value)
    {
        if (!is_array($value)) {
            return array();
        }
        $out = array();
        foreach ($value as $key => $val) {
            if (is_string($key)) {
                $out[$key] = $val;
            }
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function normalizeErrorTree($value)
    {
        if (!is_array($value)) {
            return array();
        }

        return $this->normalizeErrorTreeFromArray($value);
    }

    /**
     * @param array<int|string, mixed> $value
     * @return array<string, mixed>
     */
    protected function normalizeErrorTreeFromArray(array $value)
    {
        $out = array();
        foreach ($value as $key => $val) {
            $k = $this->errorTreeKeyToString($key);
            if ($k === null) {
                continue;
            }
            if (is_array($val)) {
                $out[$k] = $this->normalizeErrorTreeFromArray($val);
            } else {
                $out[$k] = $val;
            }
        }

        return $out;
    }

    /**
     * @param mixed $key
     * @return string|null
     */
    protected function errorTreeKeyToString($key)
    {
        if (is_int($key)) {
            return (string) $key;
        }
        if (is_string($key)) {
            return $key;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $map
     * @param string|array<int, string>|list<int|string> $key
     * @return string|array<int, string|int>|list<int|string>
     */
    public function getMappedKey($map, $key)
    {
        if (is_array($key)) {
            $mkey = $map;
            foreach ($key as $kkey) {
                if (!array_key_exists($kkey, $mkey)) {
                    return $key;
                }
                $next = $mkey[$kkey];
                if (is_array($next)) {
                    $mkey = $next;
                } else {
                    return $this->ensureMappedKeyResult($next, $key);
                }
            }

            return $this->ensureMappedKeyResult($mkey, $key);
        }

        if (isset($map[$key])) {
            return $this->ensureMappedKeyResult($map[$key], $key);
        }

        return $key;
    }

    /**
     * @param mixed $mkey
     * @param string|array<int, string|int>|list<int|string> $fallback
     * @return string|array<int, string|int>|list<int|string>
     */
    protected function ensureMappedKeyResult($mkey, $fallback)
    {
        if (is_string($mkey)) {
            return $mkey;
        }
        if (is_array($mkey)) {
            $list = array();
            foreach ($mkey as $item) {
                if (is_string($item) || is_int($item)) {
                    $list[] = $item;
                }
            }
            if ($list !== array()) {
                return $list;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $map
     * @param array<string, mixed> $errors
     * @param list<string|int> $path
     * @param int $level
     * @return list<list<string|int>>
     */
    public function getErrorPaths($map, $errors, $path = array(), $level = 0)
    {
        $errors = $this->normalizeErrorTree($errors);
        $collect = array();
        foreach ($errors as $key => $messages) {
            if (is_array($messages) && $messages) {
                $collect = array_merge($collect, $this->getErrorPaths($map, $this->normalizeErrorTree($messages), array_merge($path, array(
                    $key
                )), $level + 1));
            } else {
                if ($messages) {
                    $collect[] = $path;
                }
            }
        }
        return $collect;
    }

    /**
     * @param array<string, mixed> $map
     * @param string|array<int, string> $key
     * @return string|array<int, string|int>|list<int|string>
     */
    protected function _getMappedKeyArray($map, $key)
    {
        return $this->getMappedKey($map, $key);
    }

    /**
     * @param string|array<int, string|int>|list<int|string> $mkey
     * @param \Ufw\Form\Base|array<string, mixed>|null $form
     * @return mixed
     */
    protected function _getElementValue($mkey, $form)
    {
        $el = $this->getElement($mkey, $form);
        $value = $el instanceof \Ufw\Form\Element\Base ? $el->getValue() : $el;
        return $value;
    }

    /**
     * @param string|array<int, string|int>|list<int|string> $mkey
     * @param \Ufw\Form\Base|array<string, mixed>|null $form
     * @return \Ufw\Form\Element\Base|mixed|null
     */
    public function getElement($mkey, $form)
    {
        if (is_array($mkey)) {
            $partKeys = $mkey;
        } else {
            $partKeys = $this->splitBracketNotationParts($mkey);
        }
        $sub = $form;
        try {
            for ($i = 0, $l = count($partKeys); $i < $l - 1; $i++) {
                $partKey = $partKeys[$i];
                if (is_array($sub)) {
                    if (isset($sub[$partKey])) {
                        $sub = $sub[$partKey];
                    } else {
                        throw new Exception('Not found');
                    }
                } else {
                    if (!is_object($sub) || !method_exists($sub, 'getSubForm')) {
                        throw new Exception('Not found');
                    }
                    $sub = call_user_func([$sub, 'getSubForm'], $partKey);
                    if (!$sub) {
                        throw new Exception('Not found');
                    }
                }
            }
            $partKey = end($partKeys);
            if (!is_string($partKey)) {
                throw new Exception('Not found');
            }
            if (is_array($sub)) {
                $el = isset($sub[$partKey]) ? $sub[$partKey] : null;
            } elseif ($sub instanceof \Ufw\Form\Base) {
                $el = $sub->getElement($partKey);
            } else {
                $el = null;
            }
        } catch (Exception $e) {
            $el = null;
        }
        return $el;
    }

    /**
     * @param string $key
     * @return list<string>
     */
    protected function splitBracketNotationParts($key)
    {
        $partKeys = $this->pregSplitBracketNotation($key);
        if ($partKeys === false) {
            return array();
        }

        return $partKeys;
    }

    /**
     * @param string $key
     * @return list<string>|false
     */
    protected function pregSplitBracketNotation($key)
    {
        return preg_split('~[\]\[]~', $key, -1, PREG_SPLIT_NO_EMPTY);
    }
}
