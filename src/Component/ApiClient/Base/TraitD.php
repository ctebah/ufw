<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * DELETE helper: {@see remove()} delegates to {@see _doRemove()} inside {@see \Ufw\Component\ApiClient\Base::_run()}.
 */
trait TraitD {

    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    abstract protected function _doRemove();

    /**
     * @return $this
     */
    public function remove()
    {
        return $this->_run(function () {
            return $this->_doRemove();
        });
    }
}
