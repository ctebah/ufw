<?php
namespace Ufw\Tests\Unit\InfoHash;

use PHPUnit\Framework\TestCase;
use Ufw\InfoHash;

class InfoHashInternalsTest extends TestCase
{
    /**
     * @param string $method
     * @return \ReflectionMethod
     */
    private function method($method)
    {
        $ref = new \ReflectionMethod(InfoHash::class, $method);
        $ref->setAccessible(true);
        return $ref;
    }

    public function testProtectedKeyHelpersCoerceScalarTypes()
    {
        $hash = new InfoHash('id', 'code');
        $row = (object) array('id' => 7, 'code' => 404, 'label' => 3.5, 'flag' => true, 'bad' => array());

        $this->assertSame(7, $this->method('idFromObject')->invoke($hash, $row));
        $this->assertSame('404', $this->method('hashKeyFromObject')->invoke($hash, $row));
        $this->assertNull($this->method('hashKeyFromObject')->invoke($hash, (object) array('id' => 1, 'code' => array())));

        $this->assertSame('3.5', $this->method('scalarStringFromObject')->invoke($hash, $row, 'label'));
        $this->assertSame('1', $this->method('scalarStringFromObject')->invoke($hash, $row, 'flag'));
        $this->assertSame('', $this->method('scalarStringFromObject')->invoke($hash, $row, 'bad'));
    }

    public function testArrayPropertyRefCreatesMissingArrayProperty()
    {
        $hash = new InfoHash('id', 'name');
        $parent = (object) array('id' => 1, 'name' => 'p');
        $ref = $this->method('arrayPropertyRef')->invoke($hash, $parent, 'tags');
        $this->assertIsArray($ref);
        $ref[] = 'a';
        $this->assertSame(array('a'), $ref);
        $this->assertIsArray($parent->tags);
    }

    public function testMagicGetAliasesBuildIdListAndArray()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 3, 'code' => 'c', 'name' => 'C'));
        $this->assertSame(array(3), $hash->idArr);
        $this->assertStringContainsString('3', $hash->idList);
    }

    public function testIteratorWalksInfoEntries()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 1, 'code' => 'a', 'name' => 'A'));
        $hash->add_entry_array(array('id' => 2, 'code' => 'b', 'name' => 'B'));
        $ids = array();
        foreach ($hash as $id => $row) {
            $ids[$id] = $row->name;
        }
        $this->assertSame(array(1 => 'A', 2 => 'B'), $ids);
    }

    public function testNormalizeConnMapsFalseToNull()
    {
        $hash = new InfoHash();
        $this->assertNull($this->method('normalizeConn')->invoke($hash, false));
        $this->assertSame('default', $this->method('normalizeConn')->invoke($hash, 'default'));
    }

    public function testArrayPropertyRefReusesExistingArray()
    {
        $hash = new InfoHash('id', 'name');
        $parent = (object) array('id' => 1, 'name' => 'p', 'tags' => array('existing'));
        $ref = $this->method('arrayPropertyRef')->invoke($hash, $parent, 'tags');
        $this->assertSame(array('existing'), $ref);
    }

    public function testXmlElementNameReturnsNullForUnsupportedTypes()
    {
        $hash = new InfoHash();
        $method = new \ReflectionMethod(InfoHash::class, '__xmlElementName');
        $method->setAccessible(true);
        $this->assertNull($method->invoke(null, new \stdClass()));
    }

    public function testXmlSerializeRecursiveHandlesNumericNodeNames()
    {
        $hash = new InfoHash();
        $parent = new \SimpleXMLElement('<root></root>');
        $method = new \ReflectionMethod(InfoHash::class, '__xml_serialize_recursive');
        $method->setAccessible(true);
        $method->invoke(null, 0, 'leaf', $parent);
        $this->assertNotEmpty($parent->asXML());
    }

    public function testXmlSerializeUsesEmptyVarsForScalarRoot()
    {
        $hash = new InfoHash();
        $method = new \ReflectionMethod(InfoHash::class, '__xml_serialize');
        $method->setAccessible(true);
        $xml = $method->invoke($hash, 'plain', 'wrap');
        $this->assertStringContainsString('wrap', (string) $xml);
    }
}
