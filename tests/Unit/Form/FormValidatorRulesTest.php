<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Validator\Required;
use Ufw\Form\Validator\StringLength;
use Ufw\Tests\Support\ApplicationFixtures;

class FormValidatorRulesTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array());
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testStringLengthAndRequiredPopulateErrorMessages()
    {
        $length = new StringLength(6, 4);
        $this->assertFalse($length->isValid('ab'));
        $this->assertNotEmpty($length->getErrors());

        $required = new Required(true);
        $this->assertFalse($required->isValid('', array()));
        $this->assertNotEmpty($required->getErrors());

        $implode = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'implodeRecursive');
        $implode->setAccessible(true);
        $this->assertSame('a, b', $implode->invoke($required, array('a', 'b')));

        $create = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'createMessage');
        $create->setAccessible(true);
        $arrayMsg = $create->invoke($length, 'stringLengthTooShort', array('a', 'b'));
        $this->assertIsString($arrayMsg);
        $this->assertStringContainsString('a, b', $arrayMsg);

        $varString = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'messageVariableToString');
        $varString->setAccessible(true);
        $this->assertSame('1 2', $varString->invoke($length, array(1, 2)));
    }
}
