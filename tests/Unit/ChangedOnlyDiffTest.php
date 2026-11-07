<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Expression;
use Ufw\Model\Behaviour\ChangedOnly;

class ChangedOnlyDiffTest extends TestCase
{
    public function testJsonObjectKeyOrderIsNotAChange()
    {
        $this->assertTrue(ChangedOnly::jsonEqual(
            array('b' => 1, 'a' => 2),
            '{"a":2,"b":1}'
        ));
    }

    public function testJsonListOrderIsAChange()
    {
        $this->assertFalse(ChangedOnly::jsonEqual(array(1, 2), '[2,1]'));
    }

    public function testJsonNumberOneAndOnePointZeroMatch()
    {
        $this->assertTrue(ChangedOnly::jsonEqual(1, 1.0));
    }

    public function testJsonIntegersPastTheFloatMantissaStayDistinct()
    {
        $this->assertFalse(ChangedOnly::jsonEqual(
            '{"a":9007199254740993}',
            '{"a":9007199254740992}'
        ));
        $this->assertTrue(ChangedOnly::jsonEqual(1, 1));
        $this->assertFalse(ChangedOnly::jsonEqual(1, 2));
    }

    public function testJsonNullEmptyArrayAndEmptyStringStayDistinct()
    {
        $this->assertFalse(ChangedOnly::jsonEqual(null, array()));
        $this->assertFalse(ChangedOnly::jsonEqual(null, ''));
        $this->assertFalse(ChangedOnly::jsonEqual(array(), ''));
    }

    public function testDiffSkipsUnchangedColumnsAndPrimaryKey()
    {
        $diff = ChangedOnly::diffForUpdate(
            array('id' => '1', 'name' => 'a', 'qty' => '2'),
            array('id' => '1', 'name' => 'b', 'qty' => 2),
            array(),
            array('id'),
            array(),
            false
        );
        $this->assertSame(array('name' => 'b'), $diff);
    }

    public function testDiffWritesAPrimaryKeyThatDiffersFromTheSnapshot()
    {
        $diff = ChangedOnly::diffForUpdate(
            array('id' => '5', 'name' => 'a'),
            array('id' => 9, 'name' => 'b'),
            array(),
            array('id'),
            array(),
            false
        );
        $this->assertSame(array('id' => 9, 'name' => 'b'), $diff);
    }

    public function testExpressionIsAlwaysWritten()
    {
        $now = new Expression('NOW()');
        $diff = ChangedOnly::diffForUpdate(
            array('name' => 'a', 'ts_updated' => '2020-01-01 00:00:00'),
            array('name' => 'a', 'ts_updated' => $now),
            array(),
            array(),
            array('ts_updated'),
            false
        );
        $this->assertSame(array('ts_updated' => $now), $diff);
    }

    public function testSkipDefaultOverridesOmitsTimestampWhenNothingElseChanged()
    {
        $diff = ChangedOnly::diffForUpdate(
            array('name' => 'a', 'ts_updated' => '2020-01-01 00:00:00'),
            array('name' => 'a', 'ts_updated' => '2020-01-01 00:00:00'),
            array(),
            array(),
            array('ts_updated'),
            true
        );
        $this->assertSame(array(), $diff);
    }

    public function testSkipDefaultOverridesKeepsOldTimestampWhenAnotherFieldChanges()
    {
        $diff = ChangedOnly::diffForUpdate(
            array('name' => 'a', 'ts_updated' => '2020-01-01 00:00:00'),
            array('name' => 'b', 'ts_updated' => '2020-01-01 00:00:00'),
            array(),
            array(),
            array('ts_updated'),
            true
        );
        $this->assertSame(
            array('name' => 'b', 'ts_updated' => '2020-01-01 00:00:00'),
            $diff
        );
    }

    public function testStringZeroIsNotEmptyString()
    {
        $diff = ChangedOnly::diffForUpdate(
            array('name' => ''),
            array('name' => '0'),
            array(),
            array(),
            array(),
            false
        );
        $this->assertSame(array('name' => '0'), $diff);
    }

    public function testUntouchedJsonStaysOutOfTheUpdate()
    {
        $diff = ChangedOnly::diffForUpdate(
            array('data' => array('b' => 1, 'a' => 2), 'name' => 'a'),
            array('data' => '{"a": 2, "b": 1}', 'name' => 'a'),
            array('data'),
            array(),
            array(),
            false
        );
        $this->assertSame(array(), $diff);
    }

    public function testBeforeUpdateLeavesPresetUpdateValuesUntouched()
    {
        $record = new class(array('id' => 1, 'name' => 'a')) extends \Ufw\Model\Record\Base {
            protected static $tableName = 'widget';
            protected static $primaryKeyField = 'id';
            public function __construct(array $data)
            {
                parent::__construct($data);
            }
        };
        $record->captureReadState();
        $behaviour = new ChangedOnly($record);
        $opts = array('_updateValues' => array('name' => 'preset'));
        $behaviour->beforeUpdate($opts);
        $this->assertSame(array('name' => 'preset'), $opts['_updateValues']);
    }
}
