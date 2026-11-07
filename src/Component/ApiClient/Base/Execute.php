<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * One-shot runnable API action: {@see run()} / invokable wrapper around {@see _do()}.
 */
abstract class Execute extends \Ufw\Component\ApiClient\Base
{

    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    abstract protected function _do();

    /**
     * @return $this
     */
    public function run()
    {
        return $this->_run(function () {
            $this->_do();
            return $this->getResponse();
        });
    }

    /**
     * @return $this
     */
    public function __invoke()
    {
        return $this->run();
    }
}