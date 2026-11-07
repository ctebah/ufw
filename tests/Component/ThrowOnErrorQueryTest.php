<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use Ufw\Db\Db;
use Ufw\Db\QueryException;
use Ufw\Paths;

class ThrowOnErrorQueryTest extends ComponentTestCase
{
    public function testConfigTurnsDuplicateKeyIntoQueryException()
    {
        $this->insertWidget(array('code' => 'dup-config'));
        $restore = $this->swapApplication(TestAppPaths::throwApp());
        try {
            $db = Db::instance();
            $this->assertTrue($db->getThrowOnError());
            try {
                $db->simple_insert('widget', array(
                    'name' => 'y',
                    'code' => 'dup-config',
                ));
                $this->fail('Expected QueryException');
            } catch (QueryException $e) {
                $this->assertSame(1062, $e->getErrno());
                $this->assertStringContainsString('dup-config', $e->getQuery());
                $this->assertSame('local', $e->getConnIdent());
            }
        } finally {
            $restore();
        }
    }

    public function testConfigTurnsBadSqlIntoQueryException()
    {
        $restore = $this->swapApplication(TestAppPaths::throwApp());
        try {
            $db = Db::instance();
            try {
                $db->query('SELECT missing_column_zzz FROM widget');
                $this->fail('Expected QueryException');
            } catch (QueryException $e) {
                $this->assertSame(1054, $e->getErrno());
                $this->assertStringContainsString('missing_column_zzz', $e->getQuery());
            }
        } finally {
            $restore();
        }
    }

    public function testExplicitFalseInConfigKeepsBadSqlSilent()
    {
        $restore = $this->swapApplication(TestAppPaths::silentApp());
        try {
            $db = Db::instance();
            $this->assertFalse($db->getThrowOnError());
            $this->assertFalse($db->query('SELECT missing_column_zzz FROM widget'));
            $this->assertSame(1054, (int) $db->errno);
        } finally {
            $restore();
        }
    }

    /**
     * @param string $applicationPath
     * @return callable
     */
    private function swapApplication($applicationPath)
    {
        $prop = new \ReflectionProperty(Db::class, 'instance');
        $prop->setAccessible(true);
        $previousInstance = $prop->getValue();
        $previousPath = Paths::application();
        Paths::setApplicationPath($applicationPath);
        $prop->setValue(null, null);

        return function () use ($prop, $previousInstance, $previousPath) {
            $prop->setValue(null, $previousInstance);
            Paths::setApplicationPath($previousPath);
        };
    }
}
