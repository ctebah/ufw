<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Validator\RegEx;
use Ufw\Tests\Support\ApplicationFixtures;

class ValidatorMessagesTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testRegExValidatorErrorBranches(): void
    {
        ApplicationFixtures::bareWithController();
        $v = new RegEx('/^\d+$/');
        $this->assertFalse($v->isValid(array('x')));
        $this->assertTrue($v->isValid(''));

        $bad = new RegEx('/(/');
        $this->assertFalse($bad->isValid('1'));
        $this->assertFalse($v->isValid('abc'));
    }
}
