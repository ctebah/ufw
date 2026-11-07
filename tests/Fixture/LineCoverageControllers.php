<?php

namespace Controller;

class ThrowingPing extends Ping
{
    public function indexAction()
    {
        ob_start();
        echo 'ob-coverage';
        throw new \RuntimeException('index failed');
    }
}

class OutputPing extends Ping
{
    public function indexAction()
    {
        echo 'plain-output';
    }
}
