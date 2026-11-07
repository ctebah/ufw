<?php
namespace Ufw\Tests\Unit\InfoHash;

use PHPUnit\Framework\TestCase;
use Ufw\InfoHash;

/**
 * In-memory {@see InfoHash} collection helpers (no DB).
 */
class InfoHashCollectionTest extends TestCase
{
    public function testAddEntryAndRehashBuildHashAndInfo()
    {
        $hash = new InfoHash('id', 'code');
        $row = (object) array('id' => 1, 'code' => 'a');
        $hash->addEntry($row);
        $this->assertSame($row, $hash->info[1]);
        $this->assertSame(1, $hash->hash['a']);

        $hash->hash = array();
        $hash->rehash();
        $this->assertSame(1, $hash->hash['a']);
    }

    public function testAddEntryWithIdAndHashBothIdField()
    {
        $hash = new InfoHash('id', 'id');
        $row = (object) array('id' => 689964, 'ptype' => 1);
        $hash->addEntry($row);
        $this->assertCount(1, $hash->info);
        $this->assertSame($row, reset($hash->info));
    }

    public function testAddEntryArrayAndOptionList()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 2, 'code' => 'b', 'name' => 'Beta'));
        $this->assertSame('Beta', $hash->info[2]->name);

        $html = $hash->dump_opt_list('name');
        $this->assertStringContainsString('<option value="2">Beta</option>', $html);
    }

    public function testMakeIdArrAndIdList()
    {
        $hash = new InfoHash();
        $hash->add_entry_array(array('id' => 3, 'code' => 'c', 'name' => 'G'));
        $hash->make_id_arr();
        $this->assertSame(array(3), $hash->id_arr);
        $hash->make_id_list();
        $this->assertSame('3', $hash->id_list);
    }

    public function testJsonSerializeCountAndTweakSelected()
    {
        $hash = new InfoHash();
        $hash->add_entry_array(array('id' => 1, 'code' => 'x', 'name' => 'A'));
        $this->assertCount(1, $hash);
        $this->assertSame(array($hash->info[1]), $hash->jsonSerialize());

        $options = '<option value="1">A</option><option value="2">B</option>';
        $this->assertStringContainsString('selected="selected"', $hash->tweak_selected($options, 1));
        $this->assertSame($options, $hash->tweak_selected($options, false));
    }
}
