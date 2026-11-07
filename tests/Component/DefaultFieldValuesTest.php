<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Tests\Fixture\Record\WidgetDefaults;

class DefaultFieldValuesTest extends ComponentTestCase
{
    public function testInsertFillsOnlyMissingKeysAndAlwaysOverwrites()
    {
        $row = new WidgetDefaults(array(
            'name' => 'alpha',
        ));
        $this->assertNotFalse($row->save());
        $stored = $this->rawWidget(Db::instance()->insert_id);
        $this->assertSame('from-insert', $stored['code']);
        $this->assertSame('from-values', $stored['data']);
        $this->assertSame('40', (string) $stored['qty']);
        $this->assertSame('alpha', $stored['name']);
    }

    public function testPresentKeysStayExceptAlwaysValues()
    {
        $row = new WidgetDefaults(array(
            'name' => 'alpha',
            'code' => 'keep-code',
            'data' => 'keep-data',
            'qty' => 3,
        ));
        $this->assertNotFalse($row->save());
        $stored = $this->rawWidget(Db::instance()->insert_id);
        $this->assertSame('keep-code', $stored['code']);
        $this->assertSame('keep-data', $stored['data']);
        $this->assertSame('alpha', $stored['name']);
        $this->assertSame('40', (string) $stored['qty']);
    }

    public function testAlwaysValueCallableReceivesTheRecord()
    {
        $row = new WidgetDefaults(array(
            'name' => 'scaled',
            'code' => 'scaled-code',
            'data' => 'kept',
        ));
        $this->assertNotFalse($row->save());
        $stored = $this->rawWidget(Db::instance()->insert_id);
        $this->assertSame('7', (string) $stored['qty']);
    }

    public function testSkipDefaultOverridesLeavesAlwaysValuesAlone()
    {
        $row = new WidgetDefaults(array(
            'name' => 'alpha',
            'code' => 'skip-code',
            'data' => 'skip-data',
            'qty' => 3,
        ));
        $this->assertNotFalse($row->save(false, array('skipDefaultOverrides' => true)));
        $stored = $this->rawWidget(Db::instance()->insert_id);
        $this->assertSame('3', (string) $stored['qty']);
        $this->assertSame('skip-code', $stored['code']);
        $this->assertSame('skip-data', $stored['data']);
    }

    public function testUpdateFillsUpdateValuesAndDoesNotApplyInsertValues()
    {
        $id = $this->insertWidget(array(
            'code' => 'stay-code',
            'name' => 'alpha',
            'data' => 'orig',
            'qty' => 2,
        ));
        $row = new WidgetDefaults(array(
            'id' => $id,
            'qty' => 1,
        ));
        $row->setIsNewRecord(false);
        $this->assertNotFalse($row->save());
        $stored = $this->rawWidget($id);
        $this->assertSame('from-update', $stored['name']);
        $this->assertSame('stay-code', $stored['code']);
        $this->assertSame('from-values', $stored['data']);
        $this->assertSame('40', (string) $stored['qty']);
    }

    public function testUpdateKeepsANameThatIsAlreadyOnTheRecord()
    {
        $id = $this->insertWidget(array(
            'code' => 'named',
            'name' => 'alpha',
        ));
        $row = new WidgetDefaults(array(
            'id' => $id,
            'name' => 'kept',
            'code' => 'named',
            'data' => 'kept-data',
            'qty' => 1,
        ));
        $row->setIsNewRecord(false);
        $this->assertNotFalse($row->save());
        $stored = $this->rawWidget($id);
        $this->assertSame('kept', $stored['name']);
        $this->assertSame('40', (string) $stored['qty']);
    }
}
