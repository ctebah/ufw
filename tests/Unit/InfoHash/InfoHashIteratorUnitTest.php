<?php
namespace Ufw\Tests\Unit\InfoHash;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\InfoHash;
use Ufw\Paths;
use Ufw\Registry;

class InfoHashIteratorUnitTest extends TestCase
{
    public function testJsonSerializeCountIteratorAndMagicIdProperties()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 1, 'code' => 'a', 'name' => 'Alpha'));
        $hash->add_entry_array(array('id' => 2, 'code' => 'b', 'name' => 'Beta'));

        $this->assertSame(2, $hash->count());
        $serialized = $hash->jsonSerialize();
        $this->assertCount(2, $serialized);

        $keys = array();
        foreach ($hash as $key => $row) {
            $keys[] = $key;
            $this->assertIsObject($row);
        }
        $this->assertCount(2, $keys);

        $this->assertSame(array(1, 2), $hash->idArr);
        $this->assertNotEmpty($hash->idList);
    }

    public function testAddEntryAndRehashWithIntegerHashKeys()
    {
        $hash = new InfoHash('id', 'code');
        $obj = (object) array('id' => 5, 'code' => 99, 'label' => 'num');
        $hash->addEntry($obj);
        $this->assertSame(5, $hash->hash['99']);

        $hash->add_entry($obj);
        $hash->rehash();
        $this->assertSame(5, $hash->hash['99']);
    }

    public function testScalarHelpersViaReflection()
    {
        $hash = new InfoHash();
        $obj = (object) array('id' => 'x', 'name' => 42, 'note' => true);

        $hashKey = new \ReflectionMethod(InfoHash::class, 'hashKeyFromObject');
        $hashKey->setAccessible(true);
        $this->assertSame('42', $hashKey->invoke($hash, $obj));

        $scalarString = new \ReflectionMethod(InfoHash::class, 'scalarStringFromObject');
        $scalarString->setAccessible(true);
        $this->assertSame('42', $scalarString->invoke($hash, $obj, 'name'));

        $arrayRef = new \ReflectionMethod(InfoHash::class, 'arrayPropertyRef');
        $arrayRef->setAccessible(true);
        $parent = new \stdClass();
        $ref = $arrayRef->invoke($hash, $parent, 'tags');
        $this->assertIsArray($ref);
        $this->assertSame($ref, $parent->tags);
    }
}
