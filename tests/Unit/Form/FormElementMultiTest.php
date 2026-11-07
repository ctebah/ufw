<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Select;
use Ufw\Tests\Support\ApplicationFixtures;

class FormElementMultiTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array(
            'form' => array('helper' => 'bootstrap5'),
            'dispatch' => array('routes' => array()),
        ));
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testAllowMultipleFiltersValuesAgainstMultiOptions()
    {
        $select = new Select('tags', array(
            'multiOptions' => array('a' => 'A', 'b' => 'B', 'c' => 'C'),
            'allowMultiple' => true,
        ));
        $select->setValue(array('a', 'z', 'b'));
        $this->assertSame(array('a', 'b'), $select->getValue());
        $class = (string) $select->getAttrib('class');
        $this->assertStringContainsString('has-content', $class);
    }

    public function testAllowMultipleAppendsBracketsToNameAttribute()
    {
        $select = new Select('tags', array(
            'multiOptions' => array('a' => 'A'),
            'allowMultiple' => true,
        ));
        $attribs = $select->getAttribs();
        $this->assertSame('tags[]', $attribs['name']);
    }

    public function testSingleSelectionInvalidValueFailsValidation()
    {
        $select = new Select('color', array(
            'multiOptions' => array('red' => 'Red'),
        ));
        $select->setValue('blue');
        $this->assertFalse($select->isValid());
    }

    public function testSelectToStringAddsHasContentForSingleOption()
    {
        $select = new Select('only', array(
            'multiOptions' => array('x' => 'Only'),
        ));
        $html = (string) $select;
        $this->assertNotEmpty($html);
    }
}
