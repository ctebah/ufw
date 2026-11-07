<?php
namespace Ufw\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Db\Db;
use Ufw\Db\Expression;
use Ufw\Model\Base as ModelBase;
use Ufw\Model\Behaviour\Base as BehaviourBase;
use Ufw\Model\Behaviour\ChangedOnly;
use Ufw\Model\Behaviour\InsertId;
use Ufw\Model\Behaviour\Json;
use Ufw\Model\Behaviour\OptimisticLock;
use Ufw\Model\Behaviour\ReadAfterSave;
use Ufw\Model\Exception\StaleRecord;
use Ufw\Model\Record\Base as RecordBase;
use Ufw\Model\Record\ReadOnlyRecord;
use Ufw\Model\Record\Solr;
use Ufw\Registry;

class SurfaceModel extends ModelBase
{
    protected $recordClassName = '\\Fixture\\Record\\WidgetPlain';
}

class BareRecord extends RecordBase
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = 'id';

    public $rereads = 0;

    public function reRead()
    {
        $this->rereads++;
        return $this;
    }

    public function callInsert()
    {
        return $this->insert();
    }

    public function callUpdate()
    {
        return $this->update();
    }

    public function callDeleteHook()
    {
        return $this->_delete();
    }

    public function callHelper($name)
    {
        return $this->getHelper($name);
    }
}

class ReadOnlyProbe extends ReadOnlyRecord
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = 'id';

    public function callInsert()
    {
        return $this->insert();
    }

    public function callUpdate()
    {
        return $this->update();
    }

    public function callDeleteHook()
    {
        return $this->_delete();
    }
}

class ArrayPkRecord extends RecordBase
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = array('id', 'code');

    public function behaviours()
    {
        return array(
            'pk' => array('class' => InsertId::class),
            'reread' => array('class' => ReadAfterSave::class),
        );
    }
}

class FakeSolrUpdate
{
    public $deleted;

    public $committed = false;

    public $documents;

    public $overwrite;

    public $commitWithin;

    public function addDeleteById($id)
    {
        $this->deleted = $id;
    }

    public function addCommit()
    {
        $this->committed = true;
    }

    public function createDocument($json)
    {
        return $json;
    }

    public function addDocuments($documents, $overwrite, $commitWithin)
    {
        $this->documents = $documents;
        $this->overwrite = $overwrite;
        $this->commitWithin = $commitWithin;
    }
}

class FakeSolrClient
{
    public $update;

    public $connection;

    public function createUpdate()
    {
        return $this->update = new FakeSolrUpdate();
    }

    public function update($update, $connection)
    {
        $this->connection = $connection;
        return 'updated';
    }
}

class SolrProbe extends Solr
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = 'id';

    public $client;

    public function __construct($data = array())
    {
        $this->client = new FakeSolrClient();
        parent::__construct($data);
    }

    public function getSolrJson()
    {
        return array('id' => $this->id);
    }

    public function getSolrClient()
    {
        return $this->client;
    }

    public function runInsert()
    {
        return $this->insert();
    }

    public function runUpdate()
    {
        return $this->update();
    }

    public function runDelete()
    {
        return $this->_delete();
    }
}

class LifecycleProbe extends BehaviourBase
{
    public $seen = array();

    public function afterDataSet($opts = [])
    {
        $this->seen[] = 'afterDataSet';
    }

    public function beforeDelete($opts = [])
    {
        $this->seen[] = 'beforeDelete';
    }

    public function afterDelete($success, $opts = [])
    {
        $this->seen[] = 'afterDelete';
    }
}

class LifecycleRecord extends RecordBase
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = 'id';

    public function behaviours()
    {
        return array(
            'life' => array('class' => LifecycleProbe::class),
        );
    }
}

class ObjectTrackedRecord extends RecordBase
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = 'id';

    public function behaviours()
    {
        return array(
            'changed' => new ChangedOnly($this),
        );
    }
}

class RecordAdvancedBehavioursTest extends TestCase
{
    public function testModelMagicGetReachesDbRecordClassAndUser()
    {
        $registry = Registry::getInstance();
        $hadApplication = $registry->has('application');
        $previous = $hadApplication ? $registry->get('application') : null;
        $registry->set('application', new class extends Application {
            public function __construct()
            {
            }

            public function getHelper($name, $controller = null)
            {
                return function () use ($name) {
                    return 'helper-' . $name;
                };
            }
        });
        try {
            $model = new SurfaceModel();
            $this->assertInstanceOf(Db::class, $model->getDb());
            $this->assertSame('\\Fixture\\Record\\WidgetPlain', $model->getRecordClassName());
            $this->assertSame('helper-user', $model->user);
            $this->assertNull($model->missing);
            $this->assertSame('helper-user', $model->getUser());
        } finally {
            if ($hadApplication) {
                $registry->set('application', $previous);
            } else {
                $registry->remove('application');
            }
        }
    }

    public function testBehaviourHelperUsesTheApplication()
    {
        $registry = Registry::getInstance();
        $hadApplication = $registry->has('application');
        $previous = $hadApplication ? $registry->get('application') : null;
        $registry->set('application', new class extends Application {
            public function __construct()
            {
            }

            public function getHelper($name, $controller = null)
            {
                return 'helper-' . $name;
            }
        });
        try {
            $behaviour = new InsertId(new BareRecord());
            $this->assertSame('helper-user', $behaviour->getHelper('user'));
            $this->assertSame($behaviour->getApplication(), $registry->get('application'));
        } finally {
            if ($hadApplication) {
                $registry->set('application', $previous);
            } else {
                $registry->remove('application');
            }
        }
    }

    public function testBareRecordBasePersistenceHooksReturnFalse()
    {
        $registry = Registry::getInstance();
        $hadApplication = $registry->has('application');
        $previous = $hadApplication ? $registry->get('application') : null;
        $registry->set('application', new class extends Application {
            public function __construct()
            {
            }

            public function getHelper($name, $controller = null)
            {
                return 'ok';
            }
        });
        try {
            $record = new BareRecord(array('id' => 1, 'name' => 'a'));
            $this->assertFalse($record->callInsert());
            $this->assertFalse($record->callUpdate());
            $this->assertFalse($record->callDeleteHook());
            $this->assertSame(array(), $record->getRelationsData());
            $this->assertSame(array('id' => 1, 'name' => 'a'), $record->getData());
            $this->assertSame('ok', $record->callHelper('user'));
            $this->assertFalse($record->delete());
            $record->setIsNewRecord(false);
            $this->assertFalse($record->delete());
            $record->setPrimaryKeyField('id');
            $this->assertSame('widget', BareRecord::getTableName());
            $this->assertSame('master_server', BareRecord::getDbconnIdent());
        } finally {
            if ($hadApplication) {
                $registry->set('application', $previous);
            } else {
                $registry->remove('application');
            }
        }
    }

    public function testReadOnlyRecordRejectsWrites()
    {
        $record = new ReadOnlyProbe(array('id' => 1));
        try {
            $record->save();
            $this->fail('Expected read-only save');
        } catch (\Exception $e) {
            $this->assertSame('Read-only record', $e->getMessage());
        }
        try {
            $record->delete();
            $this->fail('Expected read-only delete');
        } catch (\Exception $e) {
            $this->assertSame('Read-only record', $e->getMessage());
        }
        foreach (array('callInsert', 'callUpdate', 'callDeleteHook') as $method) {
            try {
                $record->$method();
                $this->fail('Expected read-only ' . $method);
            } catch (\Exception $e) {
                $this->assertSame('Read-only record', $e->getMessage());
            }
        }
    }

    public function testSolrRecordSendsInsertUpdateAndDeleteThroughTheClient()
    {
        $record = new SolrProbe(array('id' => 9));
        $record->setOverwrite(true);
        $record->setAddCommit(false);
        $record->setCommitWithin(1000);
        $this->assertTrue($record->getOverwrite());
        $this->assertFalse($record->getAddCommit());
        $this->assertSame(1000, $record->getCommitWithin());
        $this->assertNull($record->getSolrConnection());

        $this->assertSame($record, $record->runInsert());
        $this->assertFalse($record->client->update->committed);
        $this->assertSame(array(array('id' => 9)), $record->client->update->documents);

        $record->setAddCommit(true);
        $this->assertSame($record, $record->runUpdate());
        $this->assertTrue($record->client->update->committed);

        $this->assertSame('updated', $record->runDelete());
        $this->assertSame(9, $record->client->update->deleted);
        $this->assertTrue($record->client->update->committed);
    }

    public function testInsertIdSkipsAFailedInsertAndACompositeKey()
    {
        $record = new ArrayPkRecord(array('id' => 1, 'code' => 'a'));
        $behaviour = new InsertId($record);
        $this->assertSame($behaviour, $behaviour->afterInsert(false));
        $this->assertSame(1, $record->id);
        $record->setIsNewRecord(false);
        $behaviour->afterInsert(true);
        $this->assertSame(1, $record->id);

        $fresh = new ArrayPkRecord(array('id' => 4, 'code' => 'b'));
        (new InsertId($fresh))->afterInsert(true);
        $this->assertSame(4, $fresh->id);
    }

    public function testReadAfterSaveReturnsImmediatelyWhenTheWriteFailed()
    {
        $record = new ArrayPkRecord(array('id' => 1));
        $behaviour = new ReadAfterSave($record);
        $this->assertSame($behaviour, $behaviour->afterSave(false));
    }

    public function testJsonHooksCoverEmptyConfigAndNonScalarValues()
    {
        $record = new BareRecord(array('data' => array('a' => 1), 'note' => null));
        $json = new Json($record);
        $this->assertSame(array('data' => array('a' => 1)), $json->beforeDataSet(array('data' => array('a' => 1))));

        $json->serialAttributes = array('data', 'missing', 'note');
        $decoded = $json->beforeDataSet(array(
            'data' => '{"a":1}',
            'note' => '',
            'missing' => null,
            'flag' => false,
        ));
        $this->assertSame(array('a' => 1), $decoded['data']);

        $bad = $json->beforeDataSet(array('data' => 'false'));
        $this->assertSame(array(), $bad['data']);

        $decode = new \ReflectionMethod(Json::class, 'decodeJsonScalar');
        $decode->setAccessible(true);
        $this->assertSame(7, $decode->invoke($json, 7));

        $json->beforeSave();
        $this->assertSame('{"a":1}', $record->data);
        $record->note = null;
        $json->beforeSave();
        $record->data = '{"a":1}';
        $json->beforeSave();
        $this->assertSame('{"a":1}', $record->data);
        $record->data = 'false';
        $json->beforeSave();
        $this->assertNull($record->data);

        $record->data = '{"a":1}';
        $json->afterSave(true);
        $this->assertSame(array('a' => 1), $record->data);
        $record->data = 'not-json';
        $json->afterSave(true);
        $this->assertNull($record->data);
        $record->data = 'false';
        $json->afterSave(true);
        $this->assertNull($record->data);
        $record->data = '';
        $json->afterSave(true);
        $this->assertSame('', $record->data);

        $json->serialAttributes = array();
        $this->assertSame($json, $json->beforeSave());
        $json->afterSave(true);
    }

    public function testOptimisticLockRejectsBadTokensAndSkipsAnEmptyUpdate()
    {
        $record = new BareRecord(array('id' => 1, 'name' => 'a', 'version' => ''));
        $record->setIsNewRecord(false);
        $lock = new OptimisticLock($record);
        $this->assertSame(1, $lock->beforeInsert() ? $record->version : null);

        $record->version = null;
        $lock->beforeInsert();
        $this->assertSame(1, $record->version);

        $record->version = 4;
        $lock->beforeInsert();
        $this->assertSame(4, $record->version);

        $record->captureReadState();
        $prop = new \ReflectionProperty(RecordBase::class, 'readState');
        $prop->setAccessible(true);
        $prop->setValue($record, array('version' => new Expression('NOW()')));
        try {
            $opts = array();
            $lock->beforeUpdate($opts);
            $this->fail('Expected expression rejection');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('expression', $e->getMessage());
        }

        $prop->setValue($record, array('version' => array(1)));
        try {
            $opts = array();
            $lock->beforeUpdate($opts);
            $this->fail('Expected array rejection');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('array', $e->getMessage());
        }

        $prop->setValue($record, null);
        $record->version = '';
        try {
            $opts = array();
            $lock->beforeUpdate($opts);
            $this->fail('Expected empty token rejection');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('no value', $e->getMessage());
        }

        $record->version = 2;
        $opts = array('_updateValues' => array());
        $lock->beforeUpdate($opts);
        $this->assertSame(array(), $opts['_updateValues']);
        $this->assertSame(2, $opts['_updateWhere']['version']);

        $opts = array('_updateValues' => array('name' => 'b'));
        $lock->beforeUpdate($opts);
        $this->assertInstanceOf(Expression::class, $opts['_updateValues']['version']);

        $opts = array();
        $lock->beforeUpdate($opts);
        $this->assertSame('a', $opts['_updateValues']['name']);
        $this->assertInstanceOf(Expression::class, $opts['_updateValues']['version']);

        $this->assertSame($lock, $lock->afterUpdate(false));
        $record->setLastWriteAffected(null);
        $this->assertSame($lock, $lock->afterUpdate(true));
        $this->assertSame(0, $record->rereads);
        $record->setLastWriteAffected(1);
        $this->assertSame($lock, $lock->afterUpdate(true));
        $this->assertSame(1, $record->rereads);

        $declared = new ArrayPkRecord(array('id' => 1, 'code' => 'a'));
        $declared->setLastWriteAffected(1);
        $declaredLock = new OptimisticLock($declared);
        $this->assertSame($declaredLock, $declaredLock->afterUpdate(true));

        $record->setLastWriteAffected(0);
        try {
            $lock->afterUpdate(true);
            $this->fail('Expected StaleRecord');
        } catch (StaleRecord $e) {
            $this->assertStringContainsString('changed', $e->getMessage());
        }

        $bare = new BareRecord(array('name' => 'a'));
        $missing = new \ReflectionMethod(OptimisticLock::class, 'expectedVersion');
        $missing->setAccessible(true);
        $this->assertNull($missing->invoke(new OptimisticLock($bare), 'version'));
    }

    public function testDataSetAndDeleteHooksRunDeclaredBehaviours()
    {
        $record = new LifecycleRecord(array('id' => 1, 'name' => 'a'));
        $probe = null;
        foreach ($record->getBehaviours('afterDataSet') as $behaviour) {
            $probe = $behaviour;
        }
        $this->assertInstanceOf(LifecycleProbe::class, $probe);
        $this->assertContains('afterDataSet', $probe->seen);
        $this->assertFalse($record->delete());
        $this->assertSame(array('afterDataSet', 'beforeDelete', 'afterDelete'), $probe->seen);

        $tracked = new ObjectTrackedRecord(array('name' => 'a'));
        $tracked->captureReadState();
        $this->assertSame(array('name' => 'a'), $tracked->getReadState());
    }

    public function testChangedOnlyJsonCompareFillsTheRemainingBranches()
    {
        $this->assertTrue(ChangedOnly::jsonEqual(array(1), array(1)));
        $this->assertTrue(ChangedOnly::jsonEqual(array(), array()));
        $this->assertFalse(ChangedOnly::jsonEqual(array(1), array(1, 2)));
        $this->assertFalse(ChangedOnly::jsonEqual(array(1), array('a' => 1)));
        $this->assertFalse(ChangedOnly::jsonEqual(array('a' => 1), array('a' => 1, 'b' => 2)));
        $this->assertFalse(ChangedOnly::jsonEqual(array('a' => 1), array('b' => 1)));
        $this->assertTrue(ChangedOnly::jsonEqual(true, true));
        $this->assertFalse(ChangedOnly::jsonEqual(true, false));
        $this->assertFalse(ChangedOnly::jsonEqual(1, 'nope'));
        $this->assertFalse(ChangedOnly::jsonEqual(1, 2));
        $this->assertTrue(ChangedOnly::jsonEqual('not-json', 'not-json'));
        $this->assertTrue(ChangedOnly::jsonEqual('', ''));

        $diff = ChangedOnly::diffForUpdate(
            array('meta' => array('a' => 1)),
            array('meta' => array('a' => 2)),
            array(),
            array(),
            array(),
            false
        );
        $this->assertSame(array('meta' => array('a' => 2)), $diff);

        $diff = ChangedOnly::diffForUpdate(
            array('name' => 'a'),
            array('name' => 'a', 'extra' => 'new'),
            array(),
            array(),
            array(),
            false
        );
        $this->assertSame(array('extra' => 'new'), $diff);

        $diff = ChangedOnly::diffForUpdate(
            array('id' => 1, 'name' => 'a', 'flag' => false, 'note' => null),
            array('id' => 1, 'name' => 'b', 'flag' => false, 'note' => null),
            array(),
            array('id'),
            array('id'),
            true
        );
        $this->assertSame(array('name' => 'b'), $diff);

        $diff = ChangedOnly::diffForUpdate(
            array('name' => null, 'flag' => true),
            array('name' => null, 'flag' => false),
            array(),
            array(),
            array('absent'),
            true
        );
        $this->assertSame(array('flag' => false), $diff);

        $record = new BareRecord(array('name' => 'a'));
        $record->setIsNewRecord(false);
        $changed = new ChangedOnly($record);
        $opts = array('_updateValues' => array('name' => 'a'));
        $changed->beforeUpdate($opts);
        $this->assertSame(array('name' => 'a'), $opts['_updateValues']);

        $names = new \ReflectionMethod(ChangedOnly::class, 'jsonAttributeNames');
        $names->setAccessible(true);
        $keys = new \ReflectionMethod(ChangedOnly::class, 'alwaysValueKeys');
        $keys->setAccessible(true);
        $this->assertSame(array(), $names->invoke($changed));
        $this->assertSame(array(), $keys->invoke($changed));

        $weird = new class(array('name' => 'a')) extends BareRecord {
            public function behaviours()
            {
                return array(
                    'json' => array(
                        'class' => Json::class,
                        'serialAttributes' => array('data'),
                    ),
                    'defaults' => array(
                        'class' => \Ufw\Model\Behaviour\DefaultFieldValues::class,
                        'alwaysValues' => 'ts',
                    ),
                );
            }
        };
        foreach ($weird->getBehaviours('beforeSave') as $behaviour) {
            if ($behaviour instanceof Json) {
                $behaviour->serialAttributes = 'data';
            }
        }
        $wiredWeird = new ChangedOnly($weird);
        $this->assertSame(array(), $names->invoke($wiredWeird));
        $this->assertSame(array(), $keys->invoke($wiredWeird));

        $recordWith = new class(array('name' => 'a')) extends BareRecord {
            public function behaviours()
            {
                return array(
                    'json' => array(
                        'class' => Json::class,
                        'serialAttributes' => array('data'),
                    ),
                    'defaults' => array(
                        'class' => \Ufw\Model\Behaviour\DefaultFieldValues::class,
                        'alwaysValues' => array('ts_updated' => 'x'),
                    ),
                );
            }
        };
        $wired = new ChangedOnly($recordWith);
        $this->assertSame(array('data'), $names->invoke($wired));
        $this->assertSame(array('ts_updated'), $keys->invoke($wired));
    }
}
