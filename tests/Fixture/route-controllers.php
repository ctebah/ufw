<?php

namespace Controller;

use Ufw\Controller\Base as ControllerBase;

class Ping extends ControllerBase
{
    public function indexAction()
    {
    }

    public function otherAction()
    {
    }

    protected function secretAction()
    {
    }
}

class ThrowingPing extends Ping
{
    public function indexAction()
    {
        ob_start();
        echo 'ob-coverage';
        throw new \RuntimeException('index failed');
    }
}

namespace Controller\Ping;

class Index
{
    public function get()
    {
    }
}

namespace Controller;

/**
 * Intentionally does not extend {@see ControllerBase} — gate tests for Application::dispatch().
 */
class Bare
{
    /**
     * @param \Ufw\Application $app
     */
    public function __construct($app)
    {
    }

    public function indexAction()
    {
    }
}
