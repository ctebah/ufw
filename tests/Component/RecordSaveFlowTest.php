<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Fixture\Record\WidgetPlain;

class RecordSaveFlowTest extends ComponentTestCase
{
    public function testReReadReloadsRowAfterExternalUpdate()
    {
        $id = $this->insertWidget(array('name' => 'before-reread', 'code' => 'rr-' . bin2hex(random_bytes(2))));
        $record = new WidgetPlain(array('id' => $id, 'code' => 'rr-key', 'data' => '{}'));
        $record->setIsNewRecord(false);
        $record->captureReadState();
        $this->rawUpdateName($id, 'after-reread');
        $record->reRead();
        $this->assertSame('after-reread', $record->name);
    }

    public function testAfterSaveHookRunsOnInsert()
    {
        $record = new class(array('name' => 'hook', 'code' => 'hk-' . bin2hex(random_bytes(3)), 'data' => '{}')) extends WidgetPlain {
            public $hookRan = false;

            protected function afterSave($opts = array())
            {
                $this->hookRan = true;
                parent::afterSave($opts);
            }
        };
        $record->save();
        $this->assertTrue($record->hookRan);
    }

    /**
     * @param int $id
     * @param string $name
     */
    private function rawUpdateName($id, $name)
    {
        $db = \Ufw\Db\Db::instance();
        $db->simple_update('widget', array('id' => $id, 'name' => $name));
    }
}
