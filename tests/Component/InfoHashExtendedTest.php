<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\InfoHash;

class InfoHashExtendedTest extends ComponentTestCase
{
    public function testSetDataCollectsDistinctPropertyValues()
    {
        $this->insertWidget(array('name' => 'col-a', 'code' => 'ca-' . bin2hex(random_bytes(2))));
        $this->insertWidget(array('name' => 'col-b', 'code' => 'cb-' . bin2hex(random_bytes(2))));
        $hash = new InfoHash('id', 'code');
        $collected = $hash->set_data('SELECT id, code, name FROM widget ORDER BY id', 'stdClass', false, 'name');
        $this->assertIsArray($collected);
        $this->assertArrayHasKey('name', $collected);
        $this->assertCount(2, $hash->info);
    }

    public function testSetPropertyValueIndexedAndAppendBranches()
    {
        $hash = new InfoHash('id', 'name');
        $hash->add_entry_array(array('id' => 10, 'name' => 'parent', 'code' => 'p'));
        $setter = new \ReflectionMethod(InfoHash::class, '__set_property_value');
        $setter->setAccessible(true);

        $rowAppend = (object) array('id' => 10, 'name' => 'parent', 'tag' => 'a');
        $setter->invoke($hash, $rowAppend, 'id', 'tags', 'tag', false);
        $this->assertSame('a', $hash->info[10]->tags[0]);

        $rowIndexed = (object) array('id' => 10, 'name' => 'parent', 'tag_id' => 5, 'tag' => 'five');
        $setter->invoke($hash, $rowIndexed, 'id', 'byId', 'tag', 'tag_id');
        $this->assertSame('five', $hash->info[10]->byId[5]);
    }

    public function testAsXmlAutoModeWithSingleEntryUsesObjectRoot()
    {
        $hash = new InfoHash('id', 'name');
        $hash->add_entry_array(array('id' => 1, 'name' => 'only', 'code' => 'o'));
        $xml = $hash->asXML('widget', 'auto');
        $this->assertStringContainsString('only', (string) $xml);
    }

    public function testXmlSerializeHandlesNumericKeysAndScalars()
    {
        $hash = new InfoHash('id', 'name');
        $method = new \ReflectionMethod(InfoHash::class, '__xml_serialize');
        $method->setAccessible(true);
        $xml = $method->invoke($hash, array(0 => 'val', 'label' => 'text'), 'root');
        $this->assertStringContainsString('val', (string) $xml);
        $this->assertStringContainsString('text', (string) $xml);
    }

    public function testGetTotalCountParsesStringCountFromFetchAll()
    {
        $this->insertWidget(array('name' => 'cnt', 'code' => 'cnt-' . bin2hex(random_bytes(2))));
        $hash = new InfoHash('id', 'code');
        $hash->set_data('SELECT id, code, name FROM widget ORDER BY id LIMIT 1');
        $this->assertSame(1, $hash->getTotalCount());
    }

    public function testScalarKeyFromObjectReturnsNullForNonScalar()
    {
        $hash = new InfoHash('id', 'code');
        $method = new \ReflectionMethod(InfoHash::class, 'scalarKeyFromObject');
        $method->setAccessible(true);
        $row = (object) array('id' => 1, 'code' => 'a', 'extra' => array());
        $this->assertNull($method->invoke($hash, $row, 'extra'));
    }

    public function testAddEntryUsesCoercedArrayKeys()
    {
        $hash = new InfoHash('id', 'code');
        $hash->addEntry((object) array('id' => '7', 'code' => 'c', 'name' => 'Seven'));
        $this->assertSame('Seven', $hash->info[7]->name);
    }

    public function testXmlSerializeWithScalarPhpValueUsesEmptyVarList()
    {
        $hash = new InfoHash('id', 'name');
        $method = new \ReflectionMethod(InfoHash::class, '__xml_serialize');
        $method->setAccessible(true);
        $xml = $method->invoke($hash, 'only-scalar', 'root');
        $this->assertStringContainsString('<root', (string) $xml);
    }

    public function testGetTotalCountCoercesIntegerCountFromFetchRow()
    {
        $hash = new InfoHash('id', 'code');
        $hash->setDataQuery('SELECT id FROM widget WHERE 1=0 LIMIT 1');
        $dbProp = new \ReflectionProperty(InfoHash::class, 'total_count');
        $dbProp->setAccessible(true);
        $dbProp->setValue($hash, null);
        $fetch = new \ReflectionMethod(InfoHash::class, 'getDbClass');
        $fetch->setAccessible(true);
        $this->insertWidget(array('name' => 'int-cnt', 'code' => 'ic-' . bin2hex(random_bytes(2))));
        $hash->setDataQuery('SELECT id, code, name FROM widget ORDER BY id LIMIT 5');
        $this->assertGreaterThanOrEqual(1, $hash->getTotalCount());
    }
}
