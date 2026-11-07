<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Db;

class DbQueryRetryConnectKeyTest extends TestCase
{
    public function testResolveRetryConnectKeyUsesLastUsedIdentWhenPresent(): void
    {
        $db = Db::instance();
        $method = new \ReflectionMethod(Db::class, 'resolveRetryConnectKey');
        $method->setAccessible(true);

        $lastUsed = new \ReflectionProperty(Db::class, 'last_used_conn');
        $lastUsed->setAccessible(true);
        $lastUsed->setValue($db, array('ident' => 'master', 'resource' => null));

        $connections = new \ReflectionProperty(Db::class, 'db_connections');
        $connections->setAccessible(true);
        $connections->setValue($db, array('master' => array('ident' => 'master')));

        $this->assertSame('master', $method->invoke($db, 'other'));
    }

    public function testResolveRetryConnectKeyUsesLastUsedAliasWhenMetaHasNoIdent(): void
    {
        $db = Db::instance();
        $method = new \ReflectionMethod(Db::class, 'resolveRetryConnectKey');
        $method->setAccessible(true);

        $lastUsed = new \ReflectionProperty(Db::class, 'last_used_conn');
        $lastUsed->setAccessible(true);
        $lastUsed->setValue($db, array('resource' => null));

        $this->assertSame('__last_used', $method->invoke($db, '__last_used'));
    }

    public function testResolveRetryConnectKeyUsesNamedConnectionWhenRegistered(): void
    {
        $db = Db::instance();
        $method = new \ReflectionMethod(Db::class, 'resolveRetryConnectKey');
        $method->setAccessible(true);

        $lastUsed = new \ReflectionProperty(Db::class, 'last_used_conn');
        $lastUsed->setAccessible(true);
        $lastUsed->setValue($db, array('resource' => null));

        $connections = new \ReflectionProperty(Db::class, 'db_connections');
        $connections->setAccessible(true);
        $connections->setValue($db, array('reporting' => array('host' => 'localhost')));

        $this->assertSame('reporting', $method->invoke($db, 'reporting'));
    }

    public function testResolveRetryConnectKeyFallsBackToDefault(): void
    {
        $db = Db::instance();
        $method = new \ReflectionMethod(Db::class, 'resolveRetryConnectKey');
        $method->setAccessible(true);

        $lastUsed = new \ReflectionProperty(Db::class, 'last_used_conn');
        $lastUsed->setAccessible(true);
        $lastUsed->setValue($db, null);

        $connections = new \ReflectionProperty(Db::class, 'db_connections');
        $connections->setAccessible(true);
        $connections->setValue($db, array());

        $this->assertSame('default', $method->invoke($db, null));
    }
}
