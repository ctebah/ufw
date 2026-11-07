<?php

namespace Ufw\Helper;

/**
 * Sends a `Location` response and aborts the pipeline via {@see \Ufw\Exception\BreakExecution} (302).
 * Invokable as `$this->redirect($url)` from controllers.
 */
class Redirect extends Base
{

    /**
     * @param string $url
     * @return void
     */
    public function __invoke($url)
    {
        $this->redirect($url);
    }

    /**
     * @param string $url
     * @return void
     */
    public function redirect($url)
    {
        $this->getResponse()->withHeader('Location', $url)->setFormat('raw');
        throw new \Ufw\Exception\BreakExecution('', 302);
    }
}
