<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\CheckboxSet;
use Ufw\Tests\Support\RuntimeProbe;

/**
 * Multi-select InArray haystack must accept array values (JEP forms).
 */
class InArrayMultiValueRegressionTest extends TestCase
{
    public function testCheckboxSetAcceptsArrayValueMatchingOptions()
    {
        RuntimeProbe::registerBareApplication();
        $el = new CheckboxSet('tags', array(
            'multiOptions' => array(
                '1' => 'One',
                '2' => 'Two',
            ),
        ));
        $el->setValue(array('1', '2'));
        $this->assertTrue($el->isValid());
    }

    public function testCheckboxSetRejectsValueOutsideHaystack()
    {
        RuntimeProbe::registerBareApplication();
        $el = new CheckboxSet('tags', array(
            'multiOptions' => array(
                '1' => 'One',
            ),
        ));
        $el->setValue(array('9'));
        $this->assertFalse($el->isValid());
    }
}
