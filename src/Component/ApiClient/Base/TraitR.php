<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * GET/load helper: {@see read()} caches in {@see $data}; {@see forget()} clears; implements {@see _doRead()}.
 */
trait TraitR
{
    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    abstract protected function _doRead();

    /**
     * @var array<string, mixed>|null
     */
    protected $data = array();

    /**
     * @return $this
     */
    public function forget()
    {
        return $this->setData(null);
    }

    /**
     * @param bool $reload
     * @return $this
     */
    public function read($reload = false)
    {
        if (!$reload && $this->getData()) {
            return $this;
        }
        return $this->_run(function () {
            return $this->_doRead();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, mixed>|null $map
     * @return array<string, mixed>
     */
    public function getFormData($data = null, $map = null)
    {
        $formData = array();
        if ($map === null) {
            $map = $this->getFieldsMap();
        }
        if ($data === null) {
            $data = $this->getData();
        }
        if (!is_array($data)) {
            return $formData;
        }
        $data = $this->getHelper()->normalizeStringKeyedArray($data);
        $map = $this->getHelper()->normalizeStringKeyedArray($map);
        foreach ($data as $dataKey => $value) {
            $mkey = isset($map[$dataKey]) ? $map[$dataKey] : $dataKey;
            if (is_array($mkey)) {
                $nestedMap = $this->getHelper()->normalizeStringKeyedArray($mkey);
                $nestedData = is_array($value) ? $this->getHelper()->normalizeStringKeyedArray($value) : array();
                $formData = $this->getHelper()->normalizeStringKeyedArray(array_merge_recursive(
                    $formData,
                    $this->getFormData($nestedData, $nestedMap)
                ));
            } elseif (is_string($mkey)) {
                $formData = $this->getHelper()->assignValueToKey($formData, $mkey, $value);
            }
        }
        return $this->getHelper()->normalizeStringKeyedArray($formData);
    }

    /**
     * @param array<string, mixed>|null $data
     * @return $this
     */
    public function setData($data = null)
    {
        $this->data = $data;
        return $this;
    }
}
