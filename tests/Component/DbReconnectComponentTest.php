<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use Ufw\Db\Db;

/**
 * Exercises MySQL "gone away" retry paths in {@see Db::query()} against live MySQL (Docker).
 *
 * Retry-branch tests must run in-process so PCOV records {@see Db} lines 859-862.
 */
class DbReconnectComponentTest extends ComponentTestCase
{
    protected function tearDown(): void
    {
        self::resetDbSingleton();
        parent::tearDown();
    }

    private static function resetDbSingleton(): void
    {
        $ref = new \ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);
    }
    public function testQueryRetriesAfterConnectionDroppedOnNamedConnection(): void
    {
        $db = Db::instance();
        $config = include TestAppPaths::dbconnsConfigPath();
        $cn = $config['master_server'];
        $ident = 'coverage-retry-' . bin2hex(random_bytes(2));
        $db->set_connection($ident, $cn['host'], $cn['user'], $cn['pass'], true, $cn['dbname'], false);

        $this->assertNotFalse($db->query('SELECT 1 AS ok', $ident));
        $row = $db->fetch_array($db->result);
        $this->assertSame(1, (int) ($row['ok'] ?? 0));

        $connectionsProp = new \ReflectionProperty(Db::class, 'db_connections');
        $connectionsProp->setAccessible(true);
        /** @var array<string, array<string, mixed>> $connections */
        $connections = $connectionsProp->getValue($db);
        $this->assertArrayHasKey($ident, $connections);
        $handle = $connections[$ident]['resource'] ?? null;
        $this->assertInstanceOf(\mysqli::class, $handle);
        mysqli_close($handle);
        $connections[$ident]['resource'] = $handle;
        $connectionsProp->setValue($db, $connections);

        $lastUsedProp = new \ReflectionProperty(Db::class, 'last_used_conn');
        $lastUsedProp->setAccessible(true);
        $lastUsedProp->setValue($db, array('resource' => $handle));

        $this->assertNotFalse($db->query('SELECT 2 AS ok', $ident));
        $rowAfter = $db->fetch_array($db->result);
        $this->assertSame(2, (int) ($rowAfter['ok'] ?? 0));
    }

    /**
     * Covers retryConnectKey when conn_ident is {@see Db}'s __last_used alias.
     */
    public function testQueryRetriesWhenConnIdentIsLastUsedAlias(): void
    {
        $db = Db::instance();
        $config = include TestAppPaths::dbconnsConfigPath();
        $cn = $config['master_server'];
        $ident = 'coverage-retry-lu-' . bin2hex(random_bytes(2));
        $db->set_connection($ident, $cn['host'], $cn['user'], $cn['pass'], true, $cn['dbname'], false);
        $this->assertNotFalse($db->query('SELECT 1 AS ok', $ident));

        $connectionsProp = new \ReflectionProperty(Db::class, 'db_connections');
        $connectionsProp->setAccessible(true);
        $connections = $connectionsProp->getValue($db);
        $handle = $connections[$ident]['resource'];
        mysqli_close($handle);
        $connections[$ident]['resource'] = $handle;
        $connectionsProp->setValue($db, $connections);

        $lastUsedProp = new \ReflectionProperty(Db::class, 'last_used_conn');
        $lastUsedProp->setAccessible(true);
        $lastUsedProp->setValue($db, array('resource' => $handle));

        $this->assertNotFalse($db->query('SELECT 3 AS ok', '__last_used'));
        $row = $db->fetch_array($db->result);
        $this->assertSame(3, (int) ($row['ok'] ?? 0));
    }

    /**
     * Covers retryConnectKey from db_connections when connection meta has no ident.
     */
    public function testQueryRetriesUsingNamedConnIdentWhenConnectionMetaHasNoIdent(): void
    {
        $db = Db::instance();
        $config = include TestAppPaths::dbconnsConfigPath();
        $cn = $config['master_server'];
        $ident = 'coverage-retry-key-' . bin2hex(random_bytes(2));
        $db->set_connection($ident, $cn['host'], $cn['user'], $cn['pass'], true, $cn['dbname'], false);
        $this->assertNotFalse($db->query('SELECT 1 AS ok', $ident));

        $connectionsProp = new \ReflectionProperty(Db::class, 'db_connections');
        $connectionsProp->setAccessible(true);
        $connections = $connectionsProp->getValue($db);
        $handle = $connections[$ident]['resource'];
        mysqli_close($handle);
        $connections[$ident]['resource'] = $handle;
        unset($connections[$ident]['ident']);
        $connectionsProp->setValue($db, $connections);

        $lastUsedProp = new \ReflectionProperty(Db::class, 'last_used_conn');
        $lastUsedProp->setAccessible(true);
        $lastUsedProp->setValue($db, array('resource' => $handle));

        $this->assertNotFalse($db->query('SELECT 4 AS ok', $ident));
        $row = $db->fetch_array($db->result);
        $this->assertSame(4, (int) ($row['ok'] ?? 0));
    }
}
