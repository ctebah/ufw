<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Validator\StringLength;
use Ufw\Tests\Support\ApplicationFixtures;

class FormValidatorMessageTest extends TestCase
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

    public function testCreateMessageObscuresValueAndFormatsObjects()
    {
        $validator = new StringLength(10, 2);
        $validator->setObscureValue(true);
        $this->assertFalse($validator->isValid('abcdefghijk'));

        $create = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'createMessage');
        $create->setAccessible(true);
        $msg = $create->invoke($validator, 'stringLengthTooShort', 'abcd');
        $this->assertIsString($msg);
        $this->assertStringNotContainsString('abcd', $msg);

        $validator->setObscureValue(false);
        $objectMsg = $create->invoke($validator, 'stringLengthTooShort', new class {
            public function __toString()
            {
                return 'obj-val';
            }
        });
        $this->assertStringContainsString('obj-val', $objectMsg);
    }

    public function testErrorUsesFirstTemplateWhenKeyNull()
    {
        $validator = new StringLength(5, 1);
        $error = new \ReflectionMethod(\Ufw\Form\Validator\Base::class, 'error');
        $error->setAccessible(true);
        $error->invoke($validator, null, 'x');
        $this->assertNotEmpty($validator->getErrors());
    }
}
