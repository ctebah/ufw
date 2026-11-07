<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Model\Behaviour\Base as BehaviourBase;
use Ufw\Model\Behaviour\ChangedOnly;
use Ufw\Model\Record\Base as RecordBase;

class EarlyProbe extends BehaviourBase
{
    public function beforeUpdate(&$opts = [])
    {
    }
}

class MidProbe extends BehaviourBase
{
    public $order = 100;

    public function beforeUpdate(&$opts = [])
    {
    }
}

class LateProbe extends BehaviourBase
{
    public function beforeUpdate(&$opts = [])
    {
    }
}

class OrderProbeRecord extends RecordBase
{
    public function behaviours()
    {
        return array(
            'late' => array('class' => LateProbe::class),
            'early' => array('class' => EarlyProbe::class, 'order' => 10),
            'mid' => array('class' => MidProbe::class),
        );
    }
}

class SnapshotProbeRecord extends RecordBase
{
    public function behaviours()
    {
        return array(
            'changed' => array('class' => ChangedOnly::class),
        );
    }
}

class TieLeftProbe extends BehaviourBase
{
    public $order = 40;

    public function beforeUpdate(&$opts = [])
    {
    }
}

class TieRightProbe extends BehaviourBase
{
    public $order = 40;

    public function beforeUpdate(&$opts = [])
    {
    }
}

class TieOrderRecord extends RecordBase
{
    public function behaviours()
    {
        return array(
            'right' => array('class' => TieRightProbe::class),
            'left' => array('class' => TieLeftProbe::class),
        );
    }
}

class BehaviourOrderTest extends TestCase
{
    public function testConfiguredOrderOverridesDeclarationOrder()
    {
        $record = new OrderProbeRecord();
        $idents = array();
        foreach ($record->getBehaviours('beforeUpdate') as $ident => $behaviour) {
            $idents[] = $ident;
        }
        $this->assertSame(array('early', 'mid', 'late'), $idents);
    }

    public function testEqualOrderKeepsDeclarationOrder()
    {
        $record = new TieOrderRecord();
        $idents = array();
        foreach ($record->getBehaviours('beforeUpdate') as $ident => $behaviour) {
            $idents[] = $ident;
        }
        $this->assertSame(array('right', 'left'), $idents);
    }

    public function testWithoutAReadSnapshotChangedOnlyDoesNotNarrowTheUpdate()
    {
        $record = new SnapshotProbeRecord(array('id' => 1, 'name' => 'a'));
        $record->setIsNewRecord(false);
        $behaviour = new ChangedOnly($record);
        $opts = array();
        $behaviour->beforeUpdate($opts);
        $this->assertArrayNotHasKey('_updateValues', $opts);

        $record->captureReadState();
        $record->name = 'b';
        $behaviour->beforeUpdate($opts);
        $this->assertSame(array('name' => 'b'), $opts['_updateValues']);
    }
}
