<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Singleline;
use Ufw\Form\Validator\Required;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;

class FormElementBaseTest extends TestCase
{
    use FormFixtureConfig;

    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array_merge(self::formFixtureAppConfig(), array(
            'form' => array('helper' => 'bootstrap5'),
        )));
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testMagicAccessOptionsAttribsAndCallGetters()
    {
        $el = new Text('title', array('label' => 'Title', 'class' => 'form-control'));
        $this->assertSame('Title', $el->label);
        $this->assertSame('title', $el->getName());
        $this->assertTrue($el->__isset('label'));
    }

    public function testFiltersValidatorsErrorsAndRequiredShortcut()
    {
        $el = new Text('code', array('required' => true));
        $el->addFilter(new Singleline());
        $el->setValue("  trimmed \n");
        $this->assertSame('trimmed', $el->getValue());

        $el->clearErrors()->addError('Custom');
        $this->assertSame(array('Custom'), $el->getErrors());

        $el->setValue('');
        $this->assertFalse($el->isValid(array()));
        $this->assertNotEmpty($el->getErrors());
    }

    public function testIgnoreFlagAndUnfilteredValue()
    {
        $el = new Text('hidden');
        $el->setIgnore(true);
        $el->setValue('raw', array('hidden' => 'raw'));
        $this->assertTrue($el->getIgnore());
        $unfiltered = new \ReflectionMethod(Text::class, 'getValueUnfiltered');
        if (method_exists($el, 'getValueUnfiltered')) {
            $this->assertNotNull($el->getValueUnfiltered());
        } else {
            $prop = new \ReflectionProperty(\Ufw\Form\Element\Base::class, 'valueUnfiltered');
            $prop->setAccessible(true);
            $this->assertSame('raw', $prop->getValue($el));
        }
    }
}
