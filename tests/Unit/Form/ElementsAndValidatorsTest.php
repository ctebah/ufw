<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Button;
use Ufw\Form\Element\Email;
use Ufw\Form\Element\Hidden;
use Ufw\Form\Element\Number;
use Ufw\Form\Element\Password;
use Ufw\Form\Element\Radio;
use Ufw\Form\Element\Select;
use Ufw\Form\Element\Text;
use Ufw\Form\Element\Textarea;
use Ufw\Form\Validator\Identical;
use Ufw\Tests\Support\ApplicationFixtures;

class ElementsAndValidatorsTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController();
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testCommonElementsExposeValueAndValidation()
    {
        $text = new Text('title');
        $text->setValue('A');
        $this->assertTrue($text->isValid());

        $hidden = new Hidden('id');
        $hidden->setValue('1');
        $this->assertSame('1', $hidden->getValue());

        $email = new Email('mail');
        $email->setValue('a@b.c');
        $this->assertTrue($email->isValid());

        $number = new Number('qty');
        $number->setValue(3);
        $this->assertTrue($number->isValid());

        $password = new Password('pass');
        $password->setValue('secret');
        $this->assertTrue($password->isValid());

        $textarea = new Textarea('body');
        $textarea->setValue("line\n");
        $this->assertTrue($textarea->isValid());

        $button = new Button('go');
        $this->assertSame('go', $button->getName());
    }

    public function testSelectAndRadioValidateAgainstMultiOptions()
    {
        $select = new Select('role', array(
            'multiOptions' => array('1' => 'One', '2' => 'Two'),
        ));
        $select->setValue('2');
        $this->assertTrue($select->isValid());

        $radio = new Radio('pick', array(
            'multiOptions' => array('a' => 'A'),
        ));
        $radio->setValue('a');
        $this->assertTrue($radio->isValid());
    }

    public function testIdenticalValidatorComparesStrictly()
    {
        $validator = new Identical('token');
        $this->assertTrue($validator->isValid('token', array('token' => 'token')));
        $this->assertFalse($validator->isValid('other', array('token' => 'token')));
    }

    public function testFormCollectionAggregatesElements()
    {
        list($app) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        ));
        $form = new class($app) extends \Ufw\Form\Base {
            /** @var \Ufw\Application */
            private $localApp;

            public function __construct(\Ufw\Application $application)
            {
                $this->localApp = $application;
                parent::__construct(array());
            }

            public function getApplication()
            {
                return $this->localApp;
            }

            protected function init()
            {
                $this->setAttrib('action', '/post');
            }
        };
        $form->addElement(new Text('name'));
        $elements = $form->getElements();
        $this->assertArrayHasKey('name', $elements);
        $this->assertSame('name', $elements['name']->getName());
    }
}
