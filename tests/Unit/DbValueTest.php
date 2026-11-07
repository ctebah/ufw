<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Condition;
use Ufw\Db\Db;
use Ufw\Db\Expression;

class DbValueTest extends TestCase
{
    public function testExpressionSerializesToItsSql()
    {
        $expression = new Expression('NOW()');
        $this->assertSame('NOW()', (string) $expression);
        $this->assertSame('NOW()', $expression->jsonSerialize());
    }

    public function testConditionBuildsComparisonSql()
    {
        $equals = new Condition(array(
            'field' => 'name',
            'value' => 'a',
        ));
        $this->assertSame('name', $equals->getField());
        $this->assertSame('a', $equals->getValue());
        $this->assertSame('=', $equals->getOp());
        $this->assertSame("name = ('a')", (string) $equals);

        $missing = new Condition();
        $this->assertSame('IS', $missing->getOp());

        $in = new Condition(array(
            'field' => 'id',
            'value' => array(1, 2),
        ));
        $this->assertSame('IN', $in->getOp());
        $this->assertSame('id IN (1, 2)', (string) $in);

        $between = new Condition(array(
            'field' => 'qty',
            'op' => 'between',
            'value' => '1 AND 3',
        ));
        $between->setField('qty');
        $between->setOp('BETWEEN');
        $between->setValue('1 AND 3');
        $this->assertSame('BETWEEN', $between->getOp());
        $this->assertSame("qty BETWEEN '1 AND 3'", (string) $between);

        $raw = new Condition(new Expression('1 = 1'));
        $this->assertSame('1 = 1', (string) $raw);
    }

    public function testQuoteCoversScalarShapesWithoutALiveConnection()
    {
        $this->assertSame(3, Db::quote(3));
        $this->assertMatchesRegularExpression('/^1[.,]500000$/', (string) Db::quote(1.5));
        $this->assertSame(array(1, 'NULL'), Db::quote(array(1, null)));
        $this->assertSame(0, Db::quote(false));
        $this->assertSame(1, Db::quote(true));
        $this->assertSame('NULL', Db::quote(null));
        $this->assertSame('NULL', Db::quote('nil'));
        $this->assertSame('NOW()', Db::quote(new Expression('NOW()')));
        $this->assertSame("'a'", Db::quote('a'));
    }

    public function testConditionIsNullUsesIsOperatorWithoutExtraBrackets()
    {
        $isNull = new Condition(array(
            'field' => 'deleted_at',
            'value' => null,
        ));
        $this->assertSame('IS', $isNull->getOp());
        $this->assertSame('deleted_at IS NULL', (string) $isNull);
    }

    public function testConditionRegexpOmitsListBrackets()
    {
        $regexp = new Condition(array(
            'field' => 'name',
            'op' => 'REGEXP',
            'value' => '^a',
        ));
        $this->assertSame("name REGEXP '^a'", (string) $regexp);
    }

    public function testConditionGetValueQuotedJoinsArrayParts()
    {
        $in = new Condition(array(
            'field' => 'id',
            'value' => array(1, 2),
        ));
        $this->assertSame('1, 2', $in->getValueQuoted(Db::class));
    }

    public function testConditionInPreservesExpressionSubquery()
    {
        $subquery = new Expression('SELECT id FROM items WHERE active = 1');
        $condition = new Condition(array(
            'field' => 'id',
            'op' => 'IN',
            'value' => $subquery,
        ));
        $this->assertSame($subquery, $condition->getValue());
        $this->assertSame('IN', $condition->getOp());
        $this->assertSame('id IN (SELECT id FROM items WHERE active = 1)', (string) $condition);

        $viaSetter = new Condition(array('field' => 'parent_id'));
        $viaSetter->setOp('IN');
        $viaSetter->setValue(new Expression('SELECT parent_id FROM tree'));
        $this->assertSame('parent_id IN (SELECT parent_id FROM tree)', (string) $viaSetter);
    }
}
