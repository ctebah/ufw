<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Db\QueryException;

class DbQueryExceptionTest extends ComponentTestCase
{
    public function testThrowingQueryExceptionCarriesQueryAndConnectionIdent()
    {
        $db = Db::instance();
        $db->setThrowOnError(true);
        try {
            $db->query('SELECT no_such_column FROM widget LIMIT 1');
            $this->fail('Expected QueryException');
        } catch (QueryException $e) {
            $this->assertSame(1054, $e->getErrno());
            $this->assertStringContainsString('no_such_column', $e->getQuery());
            $this->assertNotSame('', $e->getConnIdent());
        } finally {
            $db->setThrowOnError(false);
        }
    }

    public function testDuplicateInsertThrowsQueryExceptionWhenThrowOnErrorEnabled()
    {
        $code = 'dup-' . bin2hex(random_bytes(4));
        $this->insertWidget(array('code' => $code));
        $db = Db::instance();
        $db->setThrowOnError(true);
        try {
            $db->simple_insert('widget', array(
                'name' => 'dup-name',
                'code' => $code,
            ));
            $this->fail('Expected QueryException');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->getErrno());
            $this->assertStringContainsString('INSERT', $e->getQuery());
        } finally {
            $db->setThrowOnError(false);
        }
    }
}
