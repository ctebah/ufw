<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * Variant of {@see TraitR}: skips re-read while {@see $hasErrors} is set after a failed response.
 */
trait TraitRE {
    
    use TraitR;

    protected $hasErrors = false;

    public function forget()
    {
        $this->hasErrors = false;
        return $this->setData(null);
    }

    /**
     *
     * @param boolean $reload
     * @return \Ufw\Component\ApiClient\Base\R
     */
    public function read($reload = false)
    {
        if ((!$reload && $this->getData()) || $this->hasErrors) {
            return $this;
        }
        
        return $this->_run(function () {
            try {
                return $this->_doRead();
            } catch (\Exception $e) {
                $this->hasErrors = true;
                throw $e;
            }
        });
    }
}