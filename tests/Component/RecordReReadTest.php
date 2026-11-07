<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Fixture\Record\WidgetPlain;

class RecordReReadTest extends ComponentTestCase
{
    public function testReReadReloadsRowFromDatabaseAfterExternalUpdate()
    {
        $id = $this->insertWidget(array('name' => 'before-read', 'code' => 'rr-gate'));
        $record = $this->fetchWidget($id, WidgetPlain::class);
        $this->assertNotNull($record);
        $record->captureReadState();
        $this->assertSame('before-read', $record->name);

        $db = \Ufw\Db\Db::instance();
        $db->simple_update('widget', array(
            'id' => $id,
            'name' => 'after-read',
        ));

        $record->reRead();
        $this->assertSame('after-read', $record->name);
    }
}
