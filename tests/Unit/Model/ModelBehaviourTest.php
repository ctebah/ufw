<?php
namespace Ufw\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Model\Behaviour\ChangedOnly;
use Ufw\Model\Behaviour\DefaultFieldValues;
use Ufw\Model\Behaviour\OptimisticLock;
use Ufw\Registry;
use Ufw\Tests\Fixture\Record\WidgetPlain;

class ModelBehaviourTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testChangedOnlyBeforeUpdateAndStaticJsonHelpers()
    {
        $record = new WidgetPlain(array('id' => 1, 'code' => 'a', 'data' => '{"x":1}'));
        $record->captureReadState();
        $record->data = '{"x":2}';

        $behaviour = new ChangedOnly($record);
        $opts = array();
        $behaviour->beforeUpdate($opts);
        $this->assertArrayHasKey('_updateValues', $opts);

        $this->assertTrue(ChangedOnly::jsonEqual('{"a":1}', '{"a":1}'));
        $this->assertTrue(ChangedOnly::diffForUpdate(
            array('id' => 1, 'name' => 'a'),
            array('id' => 1, 'name' => 'b'),
            array(),
            array('id'),
            array(),
            false
        ) === array('name' => 'b'));

        $jsonNorm = new \ReflectionMethod(ChangedOnly::class, 'jsonNormalize');
        $jsonNorm->setAccessible(true);
        $this->assertIsArray($jsonNorm->invoke(null, '{"a":1}'));

        $jsonSame = new \ReflectionMethod(ChangedOnly::class, 'jsonSame');
        $jsonSame->setAccessible(true);
        $this->assertTrue($jsonSame->invoke(null, array('a' => 1), array('a' => 1)));

        $isList = new \ReflectionMethod(ChangedOnly::class, 'isListArray');
        $isList->setAccessible(true);
        $this->assertTrue($isList->invoke(null, array(1, 2)));

        $valuesEq = new \ReflectionMethod(ChangedOnly::class, 'valuesEqual');
        $valuesEq->setAccessible(true);
        $this->assertTrue($valuesEq->invoke(null, 1, 1));

        $always = new \ReflectionMethod(ChangedOnly::class, 'alwaysValueKeys');
        $always->setAccessible(true);
        $this->assertIsArray($always->invoke($behaviour));

        $jsonNames = new \ReflectionMethod(ChangedOnly::class, 'jsonAttributeNames');
        $jsonNames->setAccessible(true);
        $this->assertIsArray($jsonNames->invoke($behaviour));
    }

    public function testDefaultFieldValuesAndOptimisticLockLifecycle()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $record = new WidgetPlain(array('data' => '{}'));
        $this->assertTrue($record->getIsNewRecord());
        $defaults = new DefaultFieldValues($record);
        $defaults->insertValues = array('code' => 'DEF');
        $defaults->beforeSave(array());
        $this->assertSame('DEF', $record->code);

        $existing = new class(array('id' => 3, 'code' => 'c', 'data' => '{}', 'version' => 2)) extends WidgetPlain {
            public function reRead()
            {
                return $this;
            }
        };
        $existing->captureReadState();
        $lock = new OptimisticLock($existing);
        $lock->beforeInsert(array());
        $opts = array();
        $lock->beforeUpdate($opts);
        $this->assertArrayHasKey('_updateWhere', $opts);
        $this->assertArrayHasKey('version', $opts['_updateWhere']);

        $existing->setLastWriteAffected(1);
        $lock->afterUpdate(true, array());

        $ownerReads = new \ReflectionMethod(OptimisticLock::class, 'ownerReadsAfterSave');
        $ownerReads->setAccessible(true);
        $this->assertIsBool($ownerReads->invoke($lock));
    }
}
