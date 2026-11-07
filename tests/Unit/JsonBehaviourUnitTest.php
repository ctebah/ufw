<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Model\Behaviour\Json;
use Ufw\Model\Record\Base as RecordBase;

class JsonBehaviourUnitTest extends TestCase
{
    public function testBeforeDataSetDecodesScalarJsonAndClearsInvalidPayload()
    {
        $owner = new class(array('meta' => '{"k":1}')) extends RecordBase {
            protected static $tableName = 'widget';
            protected static $primaryKeyField = 'id';
            public function __construct(array $data)
            {
                parent::__construct($data);
            }
        };
        $behaviour = new Json($owner);
        $behaviour->serialAttributes = array('meta');

        $out = $behaviour->beforeDataSet(array('meta' => '{"a":1}'), array());
        $this->assertSame(array('a' => 1), $out['meta']);

        $bad = $behaviour->beforeDataSet(array('meta' => 'not-json'), array());
        $this->assertNull($bad['meta']);
    }

    public function testBeforeSaveEncodesArraysAndNullsInvalidScalars()
    {
        $owner = new class(array('meta' => array('x' => 1))) extends RecordBase {
            protected static $tableName = 'widget';
            protected static $primaryKeyField = 'id';
            public function __construct(array $data)
            {
                parent::__construct($data);
            }
        };
        $behaviour = new Json($owner);
        $behaviour->serialAttributes = array('meta');
        $behaviour->beforeSave();
        $this->assertSame('{"x":1}', $owner->meta);

        $owner->meta = 'false';
        $behaviour->beforeSave();
        $this->assertNull($owner->meta);
    }

    public function testAfterSaveDecodesStoredJsonBackIntoArrays()
    {
        $owner = new class(array('meta' => '{"z":2}')) extends RecordBase {
            protected static $tableName = 'widget';
            protected static $primaryKeyField = 'id';
            public function __construct(array $data)
            {
                parent::__construct($data);
            }
        };
        $behaviour = new Json($owner);
        $behaviour->serialAttributes = array('meta');
        $behaviour->afterSave(true, array());
        $this->assertSame(array('z' => 2), $owner->meta);
    }
}
