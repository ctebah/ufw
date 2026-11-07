<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Component\Csrf;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = array();
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
        parent::tearDown();
    }

    public function testSessionKeyPrefixUsedByTokenLifecycle()
    {
        $probe = new class extends Csrf {
            public static function exposeSessionKey($purpose)
            {
                return parent::sessionKey($purpose);
            }
        };
        $key = $probe::exposeSessionKey('login');
        $this->assertStringContainsString('login', $key);
        $t1 = Csrf::token('login');
        $this->assertTrue(Csrf::validate('login', $t1));
        Csrf::rotate('login');
        $this->assertFalse(Csrf::validate('login', $t1));
    }
}
