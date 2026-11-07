<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\DateRange;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;

class FormRenderTest extends TestCase
{
    use FormFixtureConfig;

    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testFormResolvesRenderHelperAndViewScript()
    {
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
            }
        };
        $this->assertNotEmpty($form->getRenderHelperName());
        $resolve = new \ReflectionMethod(\Ufw\Form\Base::class, 'resolveFormRenderHelper');
        $resolve->setAccessible(true);
        $this->assertNotNull($resolve->invoke($form));
        $script = $form->getViewScript();
        $this->assertTrue($script === null || is_string($script));
    }

    public function testElementRenderHelperRemoveClassAndMagicAccess()
    {
        $el = new Text('t', array('class' => 'a b'));
        $el->removeClass('b');
        $this->assertStringNotContainsString('b', $el->getAttrib('class'));
        $el->setAttrib('placeholder', 'hint');
        $this->assertSame('hint', $el->getAttrib('placeholder'));
        $this->assertNotNull($el->getRenderHelperName());
        $resolve = new \ReflectionMethod(\Ufw\Form\Element\Base::class, 'resolveFormRenderHelper');
        $resolve->setAccessible(true);
        $this->assertNotNull($resolve->invoke($el));
    }

    public function testDateRangeSelectAndMultiOptionSurfaces()
    {
        $range = new DateRange('period');
        $range->setValue(array('from' => '2020-01-01', 'to' => '2020-01-31'));
        $raw = $range->getValueRaw();
        $this->assertIsArray($raw);
        $this->assertArrayHasKey('from', $raw);
        $this->assertArrayHasKey('to', $raw);
        $this->assertNotNull($range->getValueFrom());
        $this->assertNotNull($range->getValueTo());

        $select = new Select('s', array('multiOptions' => array('a' => 'A')));
        $this->assertSame(array('a' => 'A'), $select->getMultiOptions());
        $this->assertIsString((string) $select);
    }
}
