<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Condition;
use Ufw\Db\Db;
use Ufw\Db\Expression;

class DbSubqueryTest extends TestCase
{
    public function testSubqueryBuildsInExpressionAndConditionFragments()
    {
        $sql = Db::subquery(array(
            'status' => array('a', 'b'),
            'note' => new Expression('NOW()'),
            'name' => new Condition(array('field' => 'name', 'value' => 'x')),
        ), ' AND ');
        $this->assertStringContainsString('status IN', $sql);
        $this->assertStringContainsString('note = NOW()', $sql);
        $this->assertStringContainsString("name = ('x')", $sql);
    }

    public function testSubqueryCompareModeUsesIsForNull()
    {
        $sql = Db::subquery(array(
            'deleted_at' => null,
            'id' => 5,
        ), ' AND ', true);
        $this->assertStringContainsString('deleted_at IS NULL', $sql);
        $this->assertStringContainsString('id = 5', $sql);
    }
}
