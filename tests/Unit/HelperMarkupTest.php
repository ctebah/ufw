<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Helper\FlashMessenger;
use Ufw\Helper\Html5Attribs;
use Ufw\Helper\Html5Tag;
use Ufw\Helper\Placeholder;

class HelperMarkupTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        $_SESSION = array();
        parent::tearDown();
    }

    public function testHtml5TagBuildsOpenCloseAndFullElements()
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $tag = new Html5Tag();
        $tag->setController($controller);

        $this->assertSame('<br />', $tag->selfClosing('br', array()));
        $this->assertStringContainsString('<input type="hidden"', $tag->void('input', array('type' => 'hidden')));
        $this->assertSame('<p class="x">text</p>', $tag->full('p', array('class' => 'x'), 'text'));
    }

    public function testHtml5AttribsSerializesBooleansAndEscapesValues()
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $attribs = new Html5Attribs();
        $attribs->setController($controller);

        $out = $attribs->html5Attribs(array(
            'checked' => true,
            'hidden' => false,
            'title' => 'a "b"',
            'skip' => null,
        ));
        $this->assertStringContainsString('checked', $out);
        $this->assertStringNotContainsString('hidden', $out);
        $this->assertStringContainsString('a &quot;b&quot;', $out);
    }

    public function testPlaceholderAppendAndToString()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $slot = $app->getHelper('placeholder')->placeholder('testSlot');
        $this->assertInstanceOf(\Ufw\Placeholder::class, $slot);
        $slot->append('line1');
        $slot->append('line2');
        $this->assertStringContainsString('line1', (string) $slot);
        $this->assertStringContainsString('line2', (string) $slot);
    }

    public function testFlashMessengerStoresMessagesInSession()
    {
        ApplicationFixtures::bareWithController();
        $flash = new FlashMessenger();
        $flash->addMessage('saved', FlashMessenger::NAMESPACE_INFO);
        $this->assertSame(array('saved'), $flash->getCurrentMessages(FlashMessenger::NAMESPACE_INFO));
    }
}
