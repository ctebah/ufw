<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Tests\Support\RuntimeProbe;
use Ufw\Utils\ArrayAccess;

class ArrayAccessTest extends TestCase
{
    public function testMagicAccessStoresRowFieldsInDataBag()
    {
        $bag = new ArrayAccess();
        $bag->name = 'alpha';
        $this->assertSame('alpha', $bag->name);
        $this->assertSame(array('name' => 'alpha'), $bag->getAll());
    }

    public function testRealObjectPropertyIsNotStoredInDataBag()
    {
        $bag = new ArrayAccessFixture();
        $bag->realProp = 'visible';
        $bag->rowField = 'stored';

        $this->assertSame('visible', $bag->realProp);
        $this->assertSame(array('rowField' => 'stored'), $bag->getAll());
    }

    public function testMagicUnsetClearsDataBagEntry()
    {
        $bag = new ArrayAccess();
        $bag->rowField = 'gone';
        unset($bag->rowField);
        $this->assertSame(array(), $bag->getAll());
    }

    public function testMagicIssetUsesDataBagSemantics()
    {
        $bag = new ArrayAccess();
        $bag->rowField = 'value';
        $this->assertTrue(isset($bag->rowField));

        $bag->rowField = null;
        $this->assertFalse(isset($bag->rowField));
    }

    public function testOverrideExistingOnEmptyBagReplacesData()
    {
        $bag = new ArrayAccess();
        $bag->overrideExisting(array('only' => 1));
        $this->assertSame(array('only' => 1), $bag->getAll());
    }

    public function testOverrideEmptyOnEmptyBagSetsData()
    {
        $bag = new ArrayAccess();
        $bag->overrideEmpty(array('fresh' => 1));
        $this->assertSame(array('fresh' => 1), $bag->getAll());
    }

    public function testIteratorOnEmptyBagIsNotValid()
    {
        $bag = new ArrayAccess();
        $bag->rewind();
        $this->assertFalse($bag->valid());
        $this->assertNull($bag->key());
    }

    public function testFluentSettersReturnSameInstance()
    {
        $bag = new ArrayAccess();
        $this->assertSame($bag, $bag->setAll(array('a' => 1)));
        $this->assertSame($bag, $bag->overrideExisting(array('b' => 2)));
        $this->assertSame($bag, $bag->overrideEmpty(array('c' => 3)));
    }

    public function testSetAllNormalizesNumericKeysAndClearsInvalidFetchResults()
    {
        $bag = new ArrayAccess();

        $bag->setAll(array(0 => 'zero', 'id' => 1));
        if (RuntimeProbe::arrayAccessSetAllNormalizesKeysAndInvalidPayloads()) {
            $this->assertSame(array('0' => 'zero', 'id' => 1), $bag->getAll());
            $bag->setAll(false);
            $this->assertSame(array(), $bag->getAll());
            $bag->setAll(null);
            $this->assertSame(array(), $bag->getAll());
        } else {
            $this->assertSame(array(0 => 'zero', 'id' => 1), $bag->getAll());
            $bag->setAll(false);
            $this->assertFalse($bag->getAll());
        }
    }

    public function testOverrideExistingMergesNonEmptyBag()
    {
        $bag = new ArrayAccess();
        $bag->setAll(array('a' => 1, 'b' => 2));
        $bag->overrideExisting(array('b' => 9, 'c' => 3));

        $this->assertSame(array('a' => 1, 'b' => 9, 'c' => 3), $bag->getAll());
    }

    public function testOverrideEmptyFillsOnlyPreviouslyEmptyValues()
    {
        $bag = new ArrayAccess();
        $bag->setAll(array('a' => '', 'b' => 1));
        $bag->overrideEmpty(array('a' => 'filled', 'c' => 3));

        $this->assertSame(array('a' => 'filled', 'b' => 1, 'c' => 3), $bag->getAll());
    }

    public function testArrayAccessOffsetAndIssetSemantics()
    {
        $bag = new ArrayAccess();
        $bag['key'] = 'value';
        $this->assertTrue(isset($bag['key']));
        $this->assertSame('value', $bag['key']);

        $bag['nullable'] = null;
        $this->assertFalse(isset($bag['nullable']));
        $this->assertFalse($bag->offsetExists('nullable'));
        $this->assertNull($bag['nullable']);
        $this->assertArrayHasKey('nullable', $bag->getAll());

        unset($bag['key']);
        $this->assertFalse($bag->offsetExists('key'));
    }

    public function testCountableAndIteratorExposeDataBag()
    {
        $bag = new ArrayAccess();
        $bag->setAll(array('x' => 1, 'y' => 2));

        $this->assertSame(2, count($bag));

        $seen = array();
        foreach ($bag as $key => $value) {
            $seen[$key] = $value;
        }
        $this->assertSame(array('x' => 1, 'y' => 2), $seen);
    }

    public function testUnsetOnRealPropertyDoesNotTouchDataBag()
    {
        $bag = new ArrayAccessFixture();
        $bag->realProp = 'x';
        unset($bag->realProp);
        $this->assertFalse(isset($bag->realProp));
        $this->assertSame(array(), $bag->getAll());
    }

    public function testIteratorWalksWithNextAndCurrent()
    {
        $bag = new ArrayAccess();
        $bag->setAll(array('a' => 1, 'b' => 2));
        $bag->rewind();
        $this->assertTrue($bag->valid());
        $this->assertSame('a', $bag->key());
        $this->assertSame(1, $bag->current());
        $bag->next();
        $this->assertSame('b', $bag->key());
        $bag->next();
        $this->assertFalse($bag->valid());
    }

    public function testOffsetGetUsesRealPropertyWhenPresent()
    {
        $bag = new ArrayAccessFixture();
        $bag->realProp = 'live';
        $this->assertSame('live', $bag['realProp']);
        $this->assertSame('live', $bag->offsetGet('realProp'));
    }

    public function testGetByReferenceMutatesDataBagInPlace()
    {
        $bag = new ArrayAccess();
        $ref = &$bag->mutable;
        $ref = 'changed';
        $this->assertSame('changed', $bag->mutable);
    }
}

class ArrayAccessFixture extends ArrayAccess
{
    /**
     * @var string
     */
    public $realProp = '';
}
