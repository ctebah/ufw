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
     *
     * @param \Ufw\Component\ApiClient\Base $ref
     *            requestor
     * @param array $fields
     *            field names for api payload
     * @param \Ufw\Form\Base|array $form
     *            data form
     * @return NULL[]
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

    public function assignValueToKey($payload, $key, $value)
    {
        $partKeys = is_array($key) ? $key : preg_split('~[\]\[]~', $key, -1, PREG_SPLIT_NO_EMPTY);
        $sub = &$payload;
        foreach ($partKeys as $partKey) {
            if (!isset($sub[$partKey]))
                $sub[$partKey] = array();
            $sub = & $sub[$partKey];
        }
        $sub = $value;
        return $payload;
    }

    public function getMappedKey($map, $key)
    {
        if (is_array($key)) {
            $mkey = $map;
            foreach ($key as $kkey) {
                if (isset($mkey[$kkey])) {
                    $mkey = $mkey[$kkey];
                } else {
                    $mkey = $key;
                }
            }
        } else {
            $mkey = isset($map[$key]) ? $map[$key] : $key;
        }
        return $mkey;
    }

    public function getErrorPaths($map, $errors, $path = array(), $level = 0)
    {
        $collect = array();
        foreach ($errors as $key => $messages) {
            if (is_array($messages) && $messages) {
                $collect = array_merge($collect, $this->getErrorPaths($map, $messages, array_merge($path, array(
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

    protected function _getMappedKeyArray($map, $key)
    {
        $tmp = $key;
        if (is_array($key)) {
            $kkey = key($key);
            $tmp[] = $kkey;
            if (isset($map[$kkey])) {
                $tmp = $this->_getMappedKeyArray($map[$kkey], $key[$kkey]);
            } else {
                $tmp = $this->_getMappedKeyArray($kkey, $key[$kkey]);
            }
        } else {
            $tmp = isset($map[$key]) ? $map[$key] : $key;
        }
        return $tmp;
    }

    protected function _getElementValue($mkey, $form)
    {
        $el = $this->getElement($mkey, $form);
        $value = $el instanceof \Ufw\Form\Element\Base ? $el->getValue() : $el;
        return $value;
    }

    public function getElement($mkey, $form)
    {
        $partKeys = is_array($mkey) ? $mkey : preg_split('~[\]\[]~', $mkey, -1, PREG_SPLIT_NO_EMPTY);
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
                    $sub = $sub->getSubForm($partKey);
                    if (!$sub) {
                        throw new Exception('Not found');
                    }
                }
            }
            $partKey = end($partKeys);
            if (is_array($sub)) {
                $el = isset($sub[$partKey]) ? $sub[$partKey] : null;
            } else {
                $el = $sub->getElement($partKey);
            }
        } catch (Exception $e) {
            $el = null;
        }
        return $el;
    }
}