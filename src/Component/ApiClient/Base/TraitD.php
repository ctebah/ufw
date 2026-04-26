<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * DELETE helper: {@see remove()} delegates to {@see _doRemove()} inside {@see \Ufw\Component\ApiClient\Base::_run()}.
 */
trait Traitd {

    abstract protected function _doRemove();

    /**
     *
     * @return \Ufw\Component\ApiClient\Base
     */
    public function remove()
    {
        return $this->_run(function () {
            return $this->_doRemove();
        });
    }
}