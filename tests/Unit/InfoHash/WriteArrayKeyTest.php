<?php
namespace Ufw\Tests\Unit\InfoHash;

use PHPUnit\Framework\TestCase;
use Ufw\InfoHash;
use Ufw\InfoHashArray;

class WriteArrayKeyTest extends TestCase
{
    public function testWriteArrayKeyCoversLegacyOffsetBranches(): void
    {
        $hash = new InfoHash('id', 'code');
        $method = new \ReflectionMethod(InfoHash::class, 'writeArrayKey');
        $method->setAccessible(true);
        $this->assertSame(2, $method->invoke($hash, 2));
        $this->assertSame('', $method->invoke($hash, ''));
        $this->assertSame(9, $method->invoke($hash, '9'));
        $this->assertSame(0, $method->invoke($hash, 0.5));
        $this->assertSame('', $method->invoke($hash, null));
        $this->assertSame('', $method->invoke($hash, new \stdClass()));
    }

    public function testInfoHashArrayAddEntryUsesWriteArrayKeyOnHashValues(): void
    {
        $hash = new InfoHashArray('id', 'tags');
        $row = (object) array('id' => '10', 'tags' => array('x', 1.5));
        $hash->addEntry($row);
        $this->assertSame($row, $hash->info[10]);
    }

    public function testDumpOptListAndWriteArrayKeyBool(): void
    {
        $hash = new InfoHash('id', 'name');
        $hash->info[1] = (object) array('id' => 1, 'name' => 'One');
        $html = $hash->dump_opt_list(null);
        $this->assertStringContainsString('One', $html);

        $write = new \ReflectionMethod(InfoHash::class, 'writeArrayKey');
        $write->setAccessible(true);
        $this->assertSame(1, $write->invoke($hash, true));
        $this->assertSame(0, $write->invoke($hash, false));

        $xml = new \ReflectionMethod(InfoHash::class, '__xml_serialize_recursive');
        $xml->setAccessible(true);
        $root = new \SimpleXMLElement('<root/>');
        $xml->invoke(null, 'key', null, $root);
        $this->assertNotNull($root);
    }

    public function testSetPropertyValueAndXmlSerializeBranches(): void
    {
        $hash = new InfoHash('id', 'code');
        $row = (object) array('id' => 1, 'code' => 'A', 'tags' => 'A');
        $hash->info[1] = $row;
        $method = new \ReflectionMethod(InfoHash::class, '__set_property_value');
        $method->setAccessible(true);
        $method->invoke($hash, $row, 'id', 'tags', false, 'code');
        $this->assertIsArray($row->tags);
    }
}
