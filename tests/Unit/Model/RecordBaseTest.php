<?php
namespace Ufw\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Support\ApplicationFixtures;

class RecordBaseTest extends TestCase
{
    protected function tearDown(): void
    {
        if (\Ufw\Registry::getInstance()->has('___model_behaviours')) {
            \Ufw\Registry::getInstance()->remove('___model_behaviours');
        }
        parent::tearDown();
    }

    public function testSetDataGetDataAndReadStateLifecycle()
    {
        $record = new WidgetPlain(array('name' => 'alpha', 'code' => 'c1', 'data' => '{}'));
        $record->setIsNewRecord(true);
        $record->captureReadState();
        $this->assertSame('alpha', $record->getReadState()['name']);

        $record->setData(array('name' => 'beta'), array('source' => 'test'));
        $data = $record->getData();
        $this->assertSame('beta', $data['name']);

        $record->setLastWriteAffected(1);
        $this->assertSame(1, $record->getLastWriteAffected());
        $this->assertSame('id', $record->getPrimaryKeyField());
    }

    public function testGetBehavioursHashBuildsFromBehaviourDefinitions()
    {
        $record = new class(array('id' => 1, 'code' => 'x', 'data' => '{}')) extends WidgetPlain {
            public function behaviours()
            {
                return array(
                    'stamp' => array(
                        'class' => \Ufw\Model\Behaviour\DefaultFieldValues::class,
                        'insertValues' => array('code' => 'AUTO'),
                    ),
                );
            }
        };
        $hash = $record->getBehavioursHash();
        $this->assertNotEmpty($hash->hash);
        $found = iterator_to_array($record->getBehaviours('beforeSave'));
        $this->assertNotEmpty($found);
    }

    public function testGetBehavioursOrdersByOrderThenDefinitionPosition()
    {
        if (\Ufw\Registry::getInstance()->has('___model_behaviours')) {
            \Ufw\Registry::getInstance()->remove('___model_behaviours');
        }
        $record = new class(array('id' => 1, 'code' => 'x', 'data' => '{}')) extends WidgetPlain {
            public function behaviours()
            {
                return array(
                    'second' => array(
                        'class' => \Ufw\Model\Behaviour\DefaultFieldValues::class,
                        'order' => 100,
                    ),
                    'first' => array(
                        'class' => \Ufw\Model\Behaviour\DefaultFieldValues::class,
                        'order' => 100,
                    ),
                );
            }
        };
        $names = array_keys(iterator_to_array($record->getBehaviours('beforeSave')));
        $this->assertSame(array('second', 'first'), $names);
    }

    public function testSimpleDeleteNormalizesNonArrayPrimaryKeyField()
    {
        ApplicationFixtures::bareWithController(array());
        $record = new class(array('id' => 3, 'code' => 'u', 'data' => '{}')) extends WidgetPlain {
            /** @var object|null */
            private $mockDb;

            public function getPrimaryKeyField()
            {
                return false;
            }

            public function getDb()
            {
                if ($this->mockDb === null) {
                    $this->mockDb = new class {
                        public $lastCriteria;

                        public function simple_delete($table, $criteria, $ident, $file, $line)
                        {
                            $this->lastCriteria = $criteria;

                            return true;
                        }
                    };
                }

                return $this->mockDb;
            }
        };
        $record->setIsNewRecord(false);
        $db = $record->getDb();
        $delete = new \ReflectionMethod($record, '_delete');
        $delete->setAccessible(true);
        $delete->invoke($record);
        $this->assertSame(array(), $db->lastCriteria);
    }
}
