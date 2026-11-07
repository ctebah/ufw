<?php
namespace Ufw\Tests\Unit\Functions;

use PHPUnit\Framework\TestCase;

class MydieTest extends TestCase
{
    private function invokeMydie($msg)
    {
        if (!defined('UFW_MYDIE_SUPPRESS_EXIT')) {
            define('UFW_MYDIE_SUPPRESS_EXIT', true);
        }
        \Ufw\mydie($msg);
    }

    public function testMydiePrintsStringMessage()
    {
        $this->expectOutputRegex('/halt-msg/');
        $this->invokeMydie('halt-msg');
    }

    public function testMydiePrintsIntegerMessage()
    {
        $this->expectOutputRegex('/42/');
        $this->invokeMydie(42);
    }

    public function testMydiePrintsFloatMessage()
    {
        $this->expectOutputRegex('/2\\.5/');
        $this->invokeMydie(2.5);
    }

    public function testMydiePrintsObjectMessage()
    {
        $this->expectOutputRegex('/obj-msg/');
        $this->invokeMydie(new class {
            public function __toString()
            {
                return 'obj-msg';
            }
        });
    }
}
