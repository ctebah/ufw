<?php
namespace Ufw\Tests\Component;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Db;
use Ufw\Paths;
use Ufw\Tests\Support\ApplicationFixtures;

abstract class ComponentTestCase extends TestCase
{
    /**
     * @var bool
     */
    private static $ready = false;

    public static function setUpBeforeClass(): void
    {
        self::$ready = self::installSchema();
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$ready) {
            $this->markTestSkipped('MySQL for component tests is not reachable');
        }
        Paths::setApplicationPath(TestAppPaths::app());
        self::resetDbSingleton();
        ApplicationFixtures::resetApplicationStaticConfig();
        $db = Db::instance();
        self::disableDbThrowOnError($db);
        $db->query('TRUNCATE TABLE widget');
    }

    protected function tearDown(): void
    {
        if (self::$ready) {
            self::disableDbThrowOnError(Db::instance());
        }
        ApplicationFixtures::resetApplicationStaticConfig();
        parent::tearDown();
    }

    /**
     * @param array $extra
     * @return int
     */
    protected function insertWidget(array $extra = array())
    {
        $row = array(
            'name' => 'alpha',
            'qty' => 2,
            'data' => '{"b":1,"a":2}',
            'version' => 1,
            'code' => 'code-' . bin2hex(random_bytes(4)),
        );
        foreach ($extra as $key => $value) {
            $row[$key] = $value;
        }
        $db = Db::instance();
        self::disableDbThrowOnError($db);
        $this->assertNotFalse($db->simple_insert('widget', $row));
        return (int) $db->insert_id;
    }

    /**
     * @param int $id
     * @param string $class
     * @return \Ufw\Model\Record\Base|null
     */
    protected function fetchWidget($id, $class)
    {
        $db = Db::instance();
        $result = $db->query('SELECT * FROM widget WHERE id = ' . (int) $id);
        $row = $db->fetch_object($result, $class);
        $db->free($result);
        return $row;
    }

    /**
     * SQL statements sent through Db::query() while $run executes, in order.
     *
     * @param callable $run
     * @return string[]
     */
    protected function capturedQueries(callable $run)
    {
        $db = Db::instance();
        $sw = new \ReflectionProperty(Db::class, 'sw');
        $sw->setAccessible(true);
        $swTotal = new \ReflectionProperty(Db::class, 'sw_total');
        $swTotal->setAccessible(true);
        $saved = array($db->query_log, $db->query_log_data, $sw->getValue($db), $swTotal->getValue($db));
        $sw->setValue($db, new \Ufw\Utils\Stopwatch());
        $swTotal->setValue($db, new \Ufw\Utils\Stopwatch());
        $db->query_log = true;
        $db->query_log_data = array();
        try {
            $run();
            return array_column($db->query_log_data, 'query');
        } finally {
            $db->query_log = $saved[0];
            $db->query_log_data = $saved[1];
            $sw->setValue($db, $saved[2]);
            $swTotal->setValue($db, $saved[3]);
        }
    }

    /**
     * @param int $id
     * @return array|null
     */
    protected function rawWidget($id)
    {
        $db = Db::instance();
        $result = $db->query('SELECT * FROM widget WHERE id = ' . (int) $id);
        $row = $db->fetch_array($result);
        $db->free($result);
        return $row;
    }

    /**
     * @param Db $db
     * @param bool $enabled
     */
    protected static function configureDbThrowOnError(Db $db, $enabled)
    {
        if (method_exists($db, 'setThrowOnError')) {
            $db->setThrowOnError($enabled);
        }
    }

    /**
     * @param Db $db
     */
    private static function disableDbThrowOnError(Db $db)
    {
        self::configureDbThrowOnError($db, false);
    }

    private static function resetDbSingleton()
    {
        $prop = new \ReflectionProperty(Db::class, 'instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * @return bool
     */
    private static function installSchema()
    {
        try {
            $config = include TestAppPaths::dbconnsConfigPath();
            $cn = $config['master_server'];
            $mysqli = @mysqli_connect(
                $cn['host'],
                $cn['user'],
                $cn['pass'],
                $cn['dbname'],
                (int) $cn['port']
            );
            if (!$mysqli) {
                return false;
            }
            $sql = file_get_contents(dirname(__DIR__) . '/schema.sql');
            if (!mysqli_multi_query($mysqli, $sql)) {
                mysqli_close($mysqli);
                return false;
            }
            do {
                if ($result = mysqli_store_result($mysqli)) {
                    mysqli_free_result($result);
                }
            } while (mysqli_more_results($mysqli) && mysqli_next_result($mysqli));
            $ok = mysqli_errno($mysqli) === 0;
            mysqli_close($mysqli);
            return $ok;
        } catch (\mysqli_sql_exception $e) {
            return false;
        }
    }
}
