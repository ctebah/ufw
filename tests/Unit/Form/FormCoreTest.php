<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Collection;
use Ufw\Form\Element\Csrf as CsrfElement;
use Ufw\Form\Element\Date;
use Ufw\Form\Element\Hidden;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;

class FormCoreTest extends TestCase
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

    public function testFormConcreteSubclassPreInitAndPopulation()
    {
        $form = new class(array('name' => 'demo')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->setAttrib('action', '/save');
                $this->addElement(new Text('title'));
            }

            public function exposePreInit()
            {
                return $this->preInit();
            }

            public function exposePostInit()
            {
                return $this->postInit();
            }
        };
        $form->exposePreInit();
        $form->exposePostInit();
        $form->populate(array('title' => 'Hello'));
        $this->assertSame('Hello', $form->getElement('title')->getValue());
        $this->assertSame('post', $form->getMethod());
        $this->assertStringContainsString('-form', $form->getAttrib('id'));
        $this->assertSame('/save', $form->getAction());
        $this->assertSame(\Ufw\Registry::getInstance()->get('application'), $form->getApplication());
        $form->setAttrib('class', 'extra');
        $this->assertSame('extra', $form->getAttrib('class'));
    }

    public function testElementMetadataAndStringCast()
    {
        $el = new Text('code', array('metadata' => array('role' => 'main')));
        $el->setValue('x');
        $this->assertSame('code', $el->getName());
        $this->assertNotNull($el->getId());
        $this->assertSame('x', $el->getValue());
        $this->assertSame(array('role' => 'main'), $el->getMetadata());
        $el->addError('bad');
        $this->assertSame(array('bad'), $el->getErrors());
    }

    public function testFormRemoveElementAndCollectionClear()
    {
        $form = new class(array()) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->setAttrib('action', '/x');
                $this->addElement(new Text('a'));
            }
        };
        $form->removeElement('a');
        $this->assertFalse($form->hasElement('a'));

        $collection = new Collection();
        $collection->set('a', new Text('a'));
        $collection->clear();
        $this->assertFalse($collection->has('a'));
    }

    public function testSelectHiddenDateAndCsrfElementSurface()
    {
        $select = new Select('role', array(
            'multiOptions' => array('1' => 'One', '2' => 'Two'),
        ));
        $select->setValue('2');
        $this->assertSame(array(1, 2), $select->getMultiOptionValues());

        $hidden = new Hidden('id');
        $hidden->setValue('9');
        $this->assertSame('9', $hidden->getValue());

        $date = new Date('when');
        $date->setValue('2020-01-02');
        $this->assertSame('2020-01-02', $date->getValueRaw());
        $this->assertNotEmpty($date->getValue());

        $csrf = new class('csrf_token') extends CsrfElement {
            public function exposeCsrfClass()
            {
                return $this->getCsrfComponentClass();
            }
        };
        $this->assertSame(\Ufw\Component\Csrf::class, $csrf->exposeCsrfClass());
    }
}
