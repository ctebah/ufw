<?php
namespace Ufw\Tests\Component;

use Ufw\InfoHash;

class InfoHashIteratorComponentTest extends ComponentTestCase
{
    public function testSetDataWithCollectPropertiesBuildsSideIndex()
    {
        $id = $this->insertWidget(array('name' => 'ih-count', 'code' => 'ihc-' . bin2hex(random_bytes(2))));
        $hash = new InfoHash('id', 'code');
        $query = 'SELECT id, code, name FROM widget WHERE id = ' . (int) $id;
        $side = $hash->set_data($query, 'stdClass', false, 'code');
        $this->assertSame($query, $hash->getDataQuery());
        $this->assertIsArray($side);
        $this->assertArrayHasKey('code', $side);
        $code = $hash->info[$id]->code;
        $this->assertArrayHasKey($code, $side['code']);
    }
}
