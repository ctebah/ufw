<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use Ufw\Db\Condition;
use Ufw\Db\Db;
use Ufw\Db\Expression;
use Ufw\Paths;
use Ufw\Tests\Fixture\Record\WidgetPlain;

class DbUpdateExpressionTest extends ComponentTestCase
{
    public function testSimpleUpdatePkResolvesRecordClassTableName()
    {
        $id = $this->insertWidget(array('name' => 'before-pk', 'code' => 'pk-' . bin2hex(random_bytes(2))));
        $db = Db::instance();
        $db->simple_update_pk(WidgetPlain::class, array('name' => 'after-pk'), array('id' => $id));
        $row = $this->rawWidget($id);
        $this->assertSame('after-pk', $row['name']);
    }

    public function testSubquerySupportsConditionAndExpression()
    {
        $fragment = Db::subquery(array(
            'version' => new Expression('version + 1'),
            'status' => new Condition(array('field' => 'qty', 'value' => 0, 'op' => '>')),
        ), ', ');
        $this->assertStringContainsString('version + 1', $fragment);
        $this->assertStringContainsString('qty', $fragment);
    }

    public function testQueryWithLastUsedConnectionIdentAndFileInvoker()
    {
        $db = Db::instance();
        $db->query('SELECT 1 AS one', 'default', __FILE__, __LINE__);
        $result = $db->query('SELECT 2 AS two', '__last_used', __FILE__, __LINE__);
        $row = $db->fetch_array($result);
        $this->assertSame(2, (int) $row['two']);
        $db->free($result);
    }

    public function testSetConnectionAutoconnectRegistersResource()
    {
        $db = Db::instance();
        $host = getenv('UFW_TEST_DB_HOST') ?: '127.0.0.1';
        $user = getenv('UFW_TEST_DB_USER') ?: 'root';
        $pass = getenv('UFW_TEST_DB_PASS') ?: 'ufw';
        $name = getenv('UFW_TEST_DB_NAME') ?: 'ufw_test';
        $db->set_connection('gate-auto', $host, $user, $pass, true, $name, false);
        $connections = (new \ReflectionProperty(Db::class, 'db_connections'));
        $connections->setAccessible(true);
        $all = $connections->getValue($db);
        $this->assertArrayHasKey('gate-auto', $all);
        $this->assertInstanceOf(\mysqli::class, $all['gate-auto']['resource']);
    }

    public function testDbConstructorAppliesSilentOptsFromFixtureApp()
    {
        $previous = Paths::application();
        Paths::setApplicationPath(TestAppPaths::silentApp());
        try {
            $ref = new \ReflectionClass(Db::class);
            $db = $ref->newInstanceWithoutConstructor();
            $ref->getConstructor()->invoke($db);
            $this->assertFalse($db->getThrowOnError());
        } finally {
            Paths::setApplicationPath($previous);
        }
    }
}
