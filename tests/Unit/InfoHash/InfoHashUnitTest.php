<?php
namespace Ufw\Tests\Unit\InfoHash;

use PHPUnit\Framework\TestCase;
use Ufw\InfoHash;

class InfoHashUnitTest extends TestCase
{
    public function testRehashMakeIdListAndOptionHelpers()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 2, 'code' => 'b', 'name' => 'Beta'));
        $hash->add_entry_array(array('id' => 1, 'code' => 'a', 'name' => 'Alpha'));
        $hash->rehash();
        $this->assertSame(2, $hash->hash['b']);

        $hash->make_id_list();
        $this->assertStringContainsString('1', $hash->id_list);

        $options = $hash->dump_opt_list('name');
        $this->assertStringContainsString('Alpha', $options);
        $selected = $hash->tweak_selected($options, 1);
        $this->assertStringContainsString('selected="selected"', $selected);

        $this->assertSame($options, $hash->tweak_selected($options, false));
        $hash->setDataQuery('SELECT id, code FROM memory WHERE 1=1');
        $this->assertSame(2, $hash->getTotalCount());
    }

    public function testJsonSerializeExportsInfoArray()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 5, 'code' => 'z', 'name' => 'Zed'));
        $encoded = $hash->jsonSerialize();
        $this->assertIsArray($encoded);
        $this->assertCount(1, $encoded);
        $this->assertSame('Zed', $encoded[0]->name);
    }
}
