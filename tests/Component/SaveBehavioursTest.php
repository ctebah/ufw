<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Model\Exception\StaleRecord;
use Ufw\Tests\Fixture\Record\WidgetLocked;
use Ufw\Tests\Fixture\Record\WidgetLockedReread;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Fixture\Record\WidgetTracked;

class SaveBehavioursTest extends ComponentTestCase
{
    public function testChangingThePrimaryKeyUpdatesTheRowThatWasRead()
    {
        $id = $this->insertWidget(array('code' => 'pk-plain'));
        $row = $this->fetchWidget($id, WidgetPlain::class);
        $newId = 800000 + $id;
        $row->id = $newId;
        $row->name = 'moved';
        $this->assertNotFalse($row->save());
        $this->assertNull($this->rawWidget($id));
        $stored = $this->rawWidget($newId);
        $this->assertSame('moved', $stored['name']);
        $this->assertSame('pk-plain', $stored['code']);
    }

    public function testChangedOnlyWritesANewPrimaryKeyAndMatchesTheOldOne()
    {
        $id = $this->insertWidget(array('code' => 'pk-changed'));
        $row = $this->fetchWidget($id, WidgetTracked::class);
        $newId = 700000 + $id;
        $row->id = $newId;
        $row->name = 'moved';
        $queries = $this->capturedQueries(function () use ($row) {
            $this->assertNotFalse($row->save());
        });
        $updates = array_values(array_filter($queries, function ($sql) {
            return stripos(ltrim($sql), 'UPDATE') === 0;
        }));
        $this->assertCount(1, $updates);
        $this->assertStringContainsString('id = ' . $newId, $updates[0]);
        $this->assertStringContainsString('name =', $updates[0]);
        $this->assertStringContainsString("id = '" . $id . "'", $updates[0]);
        $this->assertStringNotContainsString('qty =', $updates[0]);
        $this->assertNull($this->rawWidget($id));
        $this->assertSame('moved', $this->rawWidget($newId)['name']);
    }

    public function testConstructedRecordWithoutASnapshotUsesTheKeyOnTheObject()
    {
        $id = $this->insertWidget(array('code' => 'pk-nosnap', 'name' => 'alpha'));
        $row = new WidgetPlain(array(
            'id' => $id,
            'name' => 'beta',
            'qty' => 2,
            'code' => 'pk-nosnap',
        ));
        $row->setIsNewRecord(false);
        $this->assertNotFalse($row->save());
        $this->assertSame('beta', $this->rawWidget($id)['name']);
    }

    public function testPlainUpdateStillWritesEveryColumn()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetPlain::class);
        $row->name = 'beta';
        $this->assertNotFalse($row->save());
        $sql = Db::instance()->last_query;
        $this->assertStringContainsString('qty =', $sql);
        $this->assertStringContainsString('name =', $sql);
    }

    public function testChangedOnlyAfterFetchWritesTheEditedFieldAndTimestamp()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetTracked::class);
        $row->name = 'beta';
        $this->assertNotFalse($row->save());
        $sql = Db::instance()->last_query;
        $this->assertStringContainsString('name =', $sql);
        $this->assertStringContainsString('ts_updated = NOW()', $sql);
        $this->assertStringNotContainsString('qty =', $sql);
        $this->assertStringNotContainsString('data =', $sql);
    }

    public function testConstructedRecordWithoutAFetchStillWritesTheFullRow()
    {
        $id = $this->insertWidget(array('code' => 'full-row'));
        $row = new WidgetTracked(array(
            'id' => $id,
            'name' => 'beta',
            'qty' => 2,
            'data' => array('b' => 1, 'a' => 2),
            'version' => 1,
            'code' => 'full-row',
        ));
        $row->setIsNewRecord(false);
        $this->assertNotFalse($row->save());
        $this->assertStringContainsString('qty =', Db::instance()->last_query);
    }

    public function testSkipDefaultOverridesKeepsTheLoadedTimestampInTheUpdate()
    {
        $id = $this->insertWidget(array('ts_updated' => '2020-01-01 00:00:00'));
        $row = $this->fetchWidget($id, WidgetTracked::class);
        $row->name = 'beta';
        $this->assertNotFalse($row->save(false, array('skipDefaultOverrides' => true)));
        $sql = Db::instance()->last_query;
        $this->assertStringContainsString('ts_updated =', $sql);
        $this->assertStringNotContainsString('NOW()', $sql);
        $this->assertStringNotContainsString('qty =', $sql);
    }

    public function testNoFieldChangeStillTouchesTheTimestampExpression()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetTracked::class);
        $this->assertNotFalse($row->save());
        $sql = Db::instance()->last_query;
        $this->assertStringContainsString('ts_updated = NOW()', $sql);
        $this->assertStringNotContainsString('qty =', $sql);
    }

    public function testOptimisticLockRejectsAStaleVersion()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetLocked::class);
        Db::instance()->query('UPDATE widget SET version = version + 1, name = \'other\' WHERE id = ' . (int) $id);
        $row->name = 'beta';
        try {
            $row->save();
            $this->fail('Expected StaleRecord');
        } catch (StaleRecord $e) {
            $fresh = $this->fetchWidget($id, WidgetPlain::class);
            $this->assertSame('other', $fresh->name);
        }
    }

    public function testOptimisticLockStartsAMissingVersionAtOne()
    {
        $row = new WidgetLocked(array(
            'name' => 'alpha',
            'code' => 'ver-omit',
        ));
        $this->assertNotFalse($row->save());
        $this->assertSame(1, (int) $row->version);
        $this->assertSame('1', (string) $this->rawWidget($row->id)['version']);
    }

    public function testOptimisticLockKeepsAnExplicitVersionOnInsert()
    {
        $row = new WidgetLocked(array(
            'name' => 'alpha',
            'code' => 'ver-set',
            'version' => 4,
        ));
        $this->assertNotFalse($row->save());
        $this->assertSame(4, (int) $row->version);
        $this->assertSame('4', (string) $this->rawWidget($row->id)['version']);
    }

    public function testOptimisticLockIncrementsVersionOnSuccess()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetLocked::class);
        $row->name = 'beta';
        $queries = $this->capturedQueries(function () use ($row) {
            $this->assertNotFalse($row->save());
        });
        $updates = array_values(array_filter($queries, function ($sql) {
            return stripos(ltrim($sql), 'UPDATE') === 0;
        }));
        $this->assertCount(1, $updates);
        $this->assertStringContainsString('version = version + 1', $updates[0]);
        $this->assertStringContainsString('ts_updated = NOW()', $updates[0]);
        $this->assertStringNotContainsString('qty =', $updates[0]);
        $fresh = $this->fetchWidget($id, WidgetPlain::class);
        $this->assertSame('2', (string) $fresh->version);
        $this->assertSame('beta', $fresh->name);
    }

    public function testOptimisticLockReadsTheRowBackSoASecondSaveIsNotStale()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetLocked::class);
        $row->name = 'one';
        $this->assertNotFalse($row->save());
        $this->assertSame('2', (string) $row->version);
        $this->assertIsString($row->ts_updated);
        $this->assertSame(array('b' => 1, 'a' => 2), $row->data);
        $this->assertSame('2', (string) $row->getReadState()['version']);

        $row->name = 'two';
        $this->assertNotFalse($row->save());
        $stored = $this->rawWidget($id);
        $this->assertSame('3', (string) $stored['version']);
        $this->assertSame('two', $stored['name']);
        $this->assertSame('3', (string) $row->version);
    }

    public function testOptimisticLockLeavesTheReadToADeclaredReadAfterSave()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetLockedReread::class);
        $row->name = 'one';
        $queries = $this->capturedQueries(function () use ($row) {
            $this->assertNotFalse($row->save());
        });
        $selects = array_filter($queries, function ($sql) {
            return stripos(ltrim($sql), 'SELECT') === 0;
        });
        $this->assertCount(1, $selects);
        $this->assertSame(array('b' => 1, 'a' => 2), $row->data);

        $row->name = 'two';
        $this->assertNotFalse($row->save());
        $this->assertSame('3', (string) $this->rawWidget($id)['version']);
    }

    public function testOptimisticLockDoesNotReadBackWhenTheUpdateWasSkipped()
    {
        $id = $this->insertWidget();
        $row = $this->fetchWidget($id, WidgetLocked::class);
        $queries = $this->capturedQueries(function () use ($row) {
            $this->assertNotFalse($row->save(false, array('skipDefaultOverrides' => true)));
        });
        $this->assertSame(array(), $queries);
        $this->assertSame('1', (string) $this->rawWidget($id)['version']);
    }
}
