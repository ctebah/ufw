<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Form\Element\Select;
use Ufw\Registry;

use Ufw\Tests\Support\RuntimeProbe;

class InArrayValidatorTest extends TestCase
{
    protected function tearDown(): void
    {
        $registry = Registry::getInstance();
        if ($registry->has('application')) {
            $registry->remove('application');
        }
        parent::tearDown();
    }

    public function testSelectAcceptsStringValueForNumericStringOptionKey()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $select = new Select('sudska_praksa', [
            'multiOptions' => [
                '-1' => 'Sve',
                '1' => 'Da',
                '0' => 'Ne',
            ],
        ]);
        $select->setValue('-1');
        $this->assertTrue($select->isValid());
    }

    public function testSelectAcceptsIntValueForNumericStringOptionKey()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $select = new Select('sudska_praksa', [
            'multiOptions' => [
                '-1' => 'Sve',
                '1' => 'Da',
                '0' => 'Ne',
            ],
        ]);
        $select->setValue(-1);
        $this->assertTrue($select->isValid(), 'GET/casts may supply int -1 while option keys are strings');
    }

    public function testInArrayHaystackFollowsMultiOptionsAddedAfterInit()
    {
        RuntimeProbe::registerBareApplication();
        $select = new Select('status', [
            'multiOptions' => [
                'a' => 'A',
            ],
        ]);
        $select->addMultiOption('b', 'B');
        $select->setValue('b');
        if (RuntimeProbe::inArrayHaystackFollowsLateMultiOptions()) {
            $this->assertTrue($select->isValid());
        } else {
            $this->assertFalse($select->isValid(), 'v1.0.2 keeps init-time haystack; 1.1 must accept late multiOptions');
        }
    }
}
