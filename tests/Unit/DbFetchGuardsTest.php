<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Db;
use Ufw\Tests\Support\RuntimeProbe;

class DbFetchGuardsTest extends TestCase
{
    public function testFetchArrayAndObjectReturnNullForInvalidResult()
    {
        if (!RuntimeProbe::dbFetchGuardsInvalidResults()) {
            $this->markTestSkipped('Db fetch guards are a 1.1 hardening; v1.0.2 passes invalid handles to mysqli.');
        }
        $db = Db::instance();
        $this->assertNull($db->fetch_array(null));
        $this->assertNull($db->fetch_array(false));
        $this->assertNull($db->fetch_object(null));
        $this->assertNull($db->fetch_object(false));
    }

    public function testFreeIgnoresNonResults()
    {
        $db = Db::instance();
        $db->free(null);
        $db->free(false);
        $this->assertTrue(true);
    }

    public function testMagicGetReturnsNullWithoutQueryHandle()
    {
        if (!RuntimeProbe::dbMagicGetReturnsNullWithoutHandle()) {
            $this->markTestSkipped('lastQueryHandle guard is 1.1 Db API.');
        }
        $db = Db::instance();
        $handle = new \ReflectionProperty(Db::class, 'lastQueryHandle');
        $handle->setAccessible(true);
        $saved = $handle->getValue($db);
        $handle->setValue($db, null);
        try {
            $this->assertNull($db->insert_id);
            $this->assertNull($db->rows_affected);
        } finally {
            $handle->setValue($db, $saved);
        }
    }
}
