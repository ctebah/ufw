<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\InfoHashArray;

class InfoHashArrayTest extends TestCase
{
    public function testAddEntryBuildsInfoAndHashLikeMaster()
    {
        $hash = new InfoHashArray('id', 'tip_suda_id');
        $row = (object) array('id' => 5, 'tip_suda_id' => 2, 'name' => 'Sud');
        $hash->addEntry($row);
        $this->assertSame($row, $hash->info[5]);
        $this->assertSame(array(5), $hash->hash[2]);
    }

    public function testAddEntryArrayWithMultipleHashValues()
    {
        $hash = new InfoHashArray('id', 'tag');
        $hash->add_entry_array(array('id' => 1, 'tag' => array('a', 'b'), 'name' => 'X'));
        $this->assertCount(1, $hash->info);
        $this->assertSame(array(1), $hash->hash['a']);
        $this->assertSame(array(1), $hash->hash['b']);
    }

    public function testDeprecatedAddEntryDelegatesToAddEntry()
    {
        $hash = new InfoHashArray('id', 'name');
        $row = (object) array('id' => 7, 'name' => 'seven');
        $hash->add_entry($row);
        $this->assertSame($row, $hash->info[7]);
        $this->assertSame(array(7), $hash->hash['seven']);
    }
}
