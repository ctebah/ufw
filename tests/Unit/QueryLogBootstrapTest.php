<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Ufw\Db\Db;

class QueryLogBootstrapTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDevConstantRegistersTheShutdownQueryLog()
    {
        define('DBLOG_ENV', true);
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $ref = new \ReflectionClass(Db::class);
        $ctor = $ref->getConstructor();
        $ctor->setAccessible(true);
        $db = $ref->newInstanceWithoutConstructor();
        $ctor->invoke($db);

        $this->assertTrue($db->query_log);
        Db::query_log_table();
    }

    public function testQueryLogTablePrintsTheDumpAndReturns()
    {
        $db = Db::instance();
        $previous = $db->query_log_data;
        $header = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : null;
        $db->query_log_data = array(
            array('query' => 'SELECT 1', 'rows' => 1),
            array('query' => 'SELECT 2', 'rows' => 2),
        );
        $_SERVER['HTTP_X_REQUESTED_WITH'] = '';
        $this->expectOutputRegex('/query-log-table.*SELECT 1.*SELECT 2/s');
        try {
            Db::query_log_table();
        } finally {
            $db->query_log_data = $previous;
            if ($header === null) {
                unset($_SERVER['HTTP_X_REQUESTED_WITH']);
            } else {
                $_SERVER['HTTP_X_REQUESTED_WITH'] = $header;
            }
        }
    }
}
