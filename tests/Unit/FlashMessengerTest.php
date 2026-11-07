<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Helper\FlashMessenger;
use Ufw\Tests\Support\ApplicationFixtures;

class FlashMessengerTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        $this->resetFlashMessengerStatics();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            session_write_close();
        }
        parent::tearDown();
    }

    private function resetFlashMessengerStatics(): void
    {
        $ref = new \ReflectionClass(FlashMessenger::class);
        foreach (array('_session', '_messages') as $name) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    public function testLoadsPriorSessionWhenAlreadyActive(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array('ufw__fM' => array('info' => array('stored')));
        $this->resetFlashMessengerStatics();
        $fm = new FlashMessenger();
        $this->assertContains('stored', $fm->getMessages('info'));
        $this->assertFalse($fm->hasMessages('no-such-namespace'));
    }

    public function testLazySessionAndNamespaceCoercion(): void
    {
        ApplicationFixtures::bareWithController();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = array('ufw__fM' => array(5 => array('skip'), 'info' => array('kept')));
        $this->resetFlashMessengerStatics();
        new FlashMessenger();
        $fm = new FlashMessenger();
        $sessionProp = new \ReflectionProperty(FlashMessenger::class, '_session');
        $sessionProp->setAccessible(true);
        $sessionProp->setValue(null, null);
        $fm->addMessage('fresh');
        $this->assertContains('fresh', $fm->getCurrentMessages('info'));
        $this->assertSame(array(), $fm->getMessages('missing-ns'));
        $this->assertContains('kept', $fm->getMessages(''));
        $fm->clearCurrentMessages('');
        $this->assertFalse($fm->hasCurrentMessages(''));
    }
}
