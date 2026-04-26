<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * POST/PUT-style helper: {@see write()} delegates to {@see _doWrite()}.
 */
trait TraitW {

    abstract protected function _doWrite();

    /**
     *
     * @return \Ufw\Component\ApiClient\Base
     */
    public function write()
    {
        return $this->_run(function () {
            return $this->_doWrite();
        });
    }
}