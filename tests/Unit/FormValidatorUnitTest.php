<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Form\Validator\Email;
use Ufw\Form\Validator\InArray;
use Ufw\Form\Validator\StringLength;
use Ufw\Registry;

class FormValidatorUnitTest extends TestCase
{
    protected function setUp(): void
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);
    }

    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testInArrayAcceptsScalarsArraysAndNull()
    {
        $validator = new InArray(array('a', 'b', 3));
        $this->assertTrue($validator->isValid(null));
        $this->assertTrue($validator->isValid('a'));
        $this->assertTrue($validator->isValid(array('a', 3)));
        $this->assertFalse($validator->isValid('z'));
        $this->assertSame(array('a', 'b', 3), $validator->getHaystack());
    }

    public function testStringLengthBounds()
    {
        $short = new StringLength(10, 2);
        $this->assertTrue($short->isValid('ok'));
        $this->assertFalse($short->isValid('x'));
        $this->assertFalse($short->isValid('this-is-too-long'));

        $numeric = new StringLength(5);
        $this->assertTrue($numeric->isValid(12345));
        $this->assertFalse($numeric->isValid(123456));
    }

    public function testEmailValidatorUsesBuiltInPattern()
    {
        $email = new Email();
        $this->assertTrue($email->isValid('user@example.com'));
        $this->assertFalse($email->isValid('not-an-email'));
    }
}
