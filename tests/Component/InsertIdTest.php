<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Fixture\Record\WidgetInserted;
use Ufw\Tests\Fixture\Record\WidgetPlain;

class InsertIdTest extends ComponentTestCase
{
    public function testSuccessfulInsertCopiesTheAutoincrementIdOntoTheRecord()
    {
        $row = new WidgetInserted(array(
            'name' => 'alpha',
            'code' => 'insert-id',
        ));
        $this->assertNotFalse($row->save());
        $stored = $this->rawWidget($row->id);
        $this->assertSame((int) $stored['id'], (int) $row->id);
        $this->assertSame('alpha', $stored['name']);
    }

    public function testPlainRecordDoesNotCopyTheIdBack()
    {
        $row = new WidgetPlain(array(
            'name' => 'alpha',
            'code' => 'no-insert-id',
        ));
        $this->assertNotFalse($row->save());
        $this->assertArrayNotHasKey('id', $row->getAll());
    }

    public function testFailedInsertLeavesTheIdUnset()
    {
        $first = new WidgetInserted(array(
            'name' => 'alpha',
            'code' => 'dup-id',
        ));
        $this->assertNotFalse($first->save());

        $second = new WidgetInserted(array(
            'name' => 'beta',
            'code' => 'dup-id',
        ));
        $this->assertFalse($second->save());
        $this->assertArrayNotHasKey('id', $second->getAll());
    }
}
