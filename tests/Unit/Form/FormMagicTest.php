<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Trim;
use Ufw\Form\Validator\Required;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;

class FormMagicTest extends TestCase
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

    public function testFormMagicGetSetAndStringCast()
    {
        $form = new class(array('action' => '/post', 'method' => 'post')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $form->setAttrib('name', 'demo-form');
        $this->assertSame('demo-form', $form->name);
        $form->title = new Text('alias');
        $this->assertInstanceOf(Text::class, $form->getElement('title'));
    }

    public function testElementMagicSetAndMultiOptionValuesOnBase()
    {
        $el = new class('x') extends Text {
            public function exposeMultiValues()
            {
                return $this->getMultiOptionValues();
            }
        };
        $el->title = 'Label';
        $this->assertSame(array(), $el->exposeMultiValues());
    }

    public function testFiltersAndValidatorSurfaces()
    {
        $filter = new Trim();
        $this->assertStringContainsString('Filter_Trim', $filter->getName());

        $validator = new Required('req');
        $this->assertFalse($validator->isValid('', array()));

        $el = new Text('t');
        $el->setFilters(array($filter));
        $el->setValue('  hi  ');
        $this->assertSame('  hi  ', $el->getValueUnfiltered());
        $this->assertSame('hi', $filter->filter('  hi  '));
    }
}
