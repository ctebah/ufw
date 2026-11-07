<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Db\Expression;
use Ufw\Tests\Fixture\Record\WidgetPlain;
use Ufw\Tests\Fixture\Record\WidgetReread;

class ReadAfterSaveTest extends ComponentTestCase
{
    public function testSaveReloadsDatabaseDefaultsAndReplacesExpressions()
    {
        $row = new WidgetReread(array(
            'name' => 'alpha',
            'code' => 'reread',
        ));
        $row->ts_updated = new Expression('NOW()');
        $this->assertNotFalse($row->save());
        $this->assertSame('from-db', $row->label);
        $this->assertIsString($row->ts_updated);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} /', $row->ts_updated);
        $this->assertSame($row->ts_updated, $this->rawWidget($row->id)['ts_updated']);
    }

    public function testWithoutTheBehaviourTheRecordKeepsWhatItSent()
    {
        $row = new WidgetPlain(array(
            'name' => 'alpha',
            'code' => 'no-reread',
        ));
        $row->ts_updated = new Expression('NOW()');
        $this->assertNotFalse($row->save());
        $this->assertArrayNotHasKey('label', $row->getAll());
        $this->assertInstanceOf(Expression::class, $row->ts_updated);
        $stored = $this->rawWidget(Db::instance()->insert_id);
        $this->assertSame('from-db', $stored['label']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} /', $stored['ts_updated']);
    }
}
