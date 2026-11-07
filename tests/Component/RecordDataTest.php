<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Tests\Fixture\Record\WidgetInserted;

class RecordDataTest extends ComponentTestCase
{
    public function testSaveUpdateAndDeleteRoundTripUpdatesReadState()
    {
        $row = new WidgetInserted(array(
            'name' => 'save-gate',
            'qty' => 1,
            'data' => '{"k":1}',
            'version' => 1,
            'code' => 'sg-' . bin2hex(random_bytes(3)),
        ));
        $this->assertTrue($row->getIsNewRecord());
        $this->assertNotFalse($row->save());
        $this->assertFalse($row->getIsNewRecord());
        $this->assertNotNull($row->getReadState());

        $row->name = 'save-gate-updated';
        $this->assertNotFalse($row->save());
        $this->assertSame('save-gate-updated', $this->rawWidget((int) $row->id)['name']);

        $this->assertNotFalse($row->delete());
        $this->assertNull($this->rawWidget((int) $row->id));
    }
}
