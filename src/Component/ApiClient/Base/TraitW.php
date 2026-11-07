<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * POST/PUT-style helper: {@see write()} delegates to {@see _doWrite()}.
 */
trait TraitW {

    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    abstract protected function _doWrite();

    /**
     * @return $this
     */
    public function write()
    {
        return $this->_run(function () {
            return $this->_doWrite();
        });
    }
}
