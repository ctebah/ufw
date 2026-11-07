<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Text;
use Ufw\Form\Validator\Required;
use Ufw\Form\Validator\StringLength;
use Ufw\Tests\Support\RuntimeProbe;

class FormValidatorRegressionTest extends TestCase
{
    public function testRequiredTreatsZeroStringAsPresent()
    {
        $validator = new Required(false);
        if (RuntimeProbe::requiredAcceptsScalarZero()) {
            $this->assertSame(true, $validator->isValid('0'));
        } else {
            $this->assertNotSame(true, $validator->isValid('0'));
        }
    }

    public function testRequiredTreatsIntegerScalarZero()
    {
        $validator = new Required(false);
        if (RuntimeProbe::requiredAcceptsScalarZero()) {
            $this->assertSame(true, $validator->isValid(0));
        } else {
            $this->assertNotSame(true, $validator->isValid(0));
        }
    }

    public function testStringLengthAcceptsIntegerScalars()
    {
        $element = new Text('code');
        $element->addValidator('stringLength', [
            'options' => [10, 1],
        ]);
        $element->setValue(12345);
        $this->assertTrue($element->isValid());
    }
}
