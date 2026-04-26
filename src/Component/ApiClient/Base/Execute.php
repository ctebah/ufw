<?php
namespace Ufw\Component\ApiClient\Base;

/**
 * One-shot runnable API action: {@see run()} / invokable wrapper around {@see _do()}.
 */
abstract class Execute extends \Ufw\Component\ApiClient\Base
{

    abstract protected function _do();

    /**
     *
     * @return \Ufw\Component\ApiClient\Base\Execute
     */
    public function run()
    {
        return $this->_run(function () {
            $this->_do();
        });
    }

    public function __invoke()
    {
        return $this->run();
    }
}