<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * GET/load helper: {@see read()} caches in {@see $data}; {@see forget()} clears; implements {@see _doRead()}.
 */
trait TraitR
{
    abstract protected function _doRead();

    /**
     *
     * @var array
     */
    protected $data = array();

    public function forget()
    {
        return $this->setData(null);
    }

    /**
     *
     * @param boolean $reload
     * @return \Ufw\Component\ApiClient\Base\R
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
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    public function getFormData($data = null, $map = null)
    {
        $formData = array();
        if (is_null($map)) {
            $map = $this->getFieldsMap();
        }
        if (is_null($data)) {
            $data = $this->getData();
        }
        if ($data) {
            foreach ($data as $key => $value) {
                $mkey = isset($map[$key]) ? $map[$key] : $key;
                if (is_array($mkey)) {
                    $formData = array_merge_recursive($formData, $this->getFormData($value, $mkey));
                } else {
                    $formData = $this->helper->assignValueToKey($formData, $mkey, $value);
                }
            }
        }
        return $formData;
    }

    /**
     *
     * @param array $data
     */
    public function setData(array $data = null)
    {
        $this->data = $data;
        return $this;
    }
}
