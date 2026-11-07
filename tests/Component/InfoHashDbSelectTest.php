<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\InfoHash;

class InfoHashDbSelectTest extends ComponentTestCase
{
    public function testSimpleSelectPopulatesHashAndMakeIdListQuotesIds()
    {
        $id = $this->insertWidget(array('name' => 'hash-gate', 'code' => 'hg-' . bin2hex(random_bytes(2))));
        $hash = Db::instance()->simple_select('widget', array('id' => $id));
        $this->assertInstanceOf(InfoHash::class, $hash);
        $this->assertSame((string) $id, (string) $hash->hash['hash-gate']);

        $hash->make_id_list();
        $this->assertStringContainsString((string) $id, $hash->id_list);

        $options = $hash->dump_opt_list('name');
        $this->assertStringContainsString('hash-gate', $options);
        $this->assertStringContainsString('selected="selected"', $hash->tweak_selected($options, $id));
    }
}
