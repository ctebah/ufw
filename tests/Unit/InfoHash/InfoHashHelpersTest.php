<?php
namespace Ufw\Tests\Unit\InfoHash;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Db;
use Ufw\InfoHash;
use Ufw\InfoHashArray;

class InfoHashHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        InfoHash::setDbClass(Db::class);
        parent::tearDown();
    }

    public function testProtectedKeyHelpersAndDbClassMutator()
    {
        $hash = new class('id', 'code') extends InfoHash {
            public function exposeNormalizeConn($conn)
            {
                return $this->normalizeConn($conn);
            }

            public function exposeHashKeyFromObject($object)
            {
                return $this->hashKeyFromObject($object);
            }

            public function exposeIdFromObject($object)
            {
                return $this->idFromObject($object);
            }

            public function exposeScalarKeyFromObject($object, $prop)
            {
                return $this->scalarKeyFromObject($object, $prop);
            }

            public function exposeArrayPropertyRef($parent, $prop)
            {
                return $this->arrayPropertyRef($parent, $prop);
            }
        };

        $row = (object) array('id' => 5, 'code' => 'c5', 'tags' => null);
        $this->assertNull($hash->exposeNormalizeConn(false));
        $this->assertSame('c5', $hash->exposeHashKeyFromObject($row));
        $this->assertSame(5, $hash->exposeIdFromObject($row));
        $this->assertSame('c5', $hash->exposeScalarKeyFromObject($row, 'code'));
        $this->assertIsArray($hash->exposeArrayPropertyRef($row, 'tags'));

        $hash->addEntry($row);
        $this->assertCount(1, $hash->info);

        $this->assertSame(Db::class, InfoHash::setDbClass(Db::class));
    }

    public function testInfoHashArrayRehash()
    {
        $arr = new InfoHashArray('id', 'code');
        $arr->add_entry_array(array('id' => 1, 'code' => 'a'));
        $arr->hash = array();
        $arr->rehash();
        $this->assertSame(array(1), $arr->hash['a']);
    }
}
