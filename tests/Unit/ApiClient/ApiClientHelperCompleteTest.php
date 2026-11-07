<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Helper;

class ApiClientHelperCompleteTest extends TestCase
{
    public function testAssignValueToKeyWithArrayKeyPath()
    {
        $helper = Helper::getInstance();
        $out = $helper->assignValueToKey(array(), array('user', 'profile', 'name'), 'Ann');
        $this->assertSame('Ann', $out['user']['profile']['name']);
    }

    public function testAssignValueToKeyStringPathSplitsBracketNotation()
    {
        $helper = Helper::getInstance();
        $out = $helper->assignValueToKey(array(), 'items[0][label]', 'First');
        $this->assertSame('First', $out['items'][0]['label']);
    }

    public function testAssignValueAtPathPreservesExistingNestedArrays()
    {
        $helper = Helper::getInstance();
        $inner = new \ReflectionMethod(Helper::class, 'assignValueAtPath');
        $inner->setAccessible(true);
        $payload = array('meta' => array('keep' => true, 0 => 'drop'));
        $out = $inner->invoke($helper, $payload, array('meta', 'new'), 'v');
        $this->assertTrue($out['meta']['keep']);
        $this->assertSame('v', $out['meta']['new']);
        $this->assertArrayNotHasKey(0, $out['meta']);
    }

    public function testNormalizeStringKeyedArrayAndErrorTreeBranches()
    {
        $helper = Helper::getInstance();
        $this->assertSame(array('a' => 1), $helper->normalizeStringKeyedArray(array('a' => 1, 0 => 2)));
        $tree = $helper->normalizeErrorTree(array('x' => array('y' => 'z')));
        $this->assertSame('z', $tree['x']['y']);
    }

    public function testErrorTreeKeyToStringRejectsNonScalarKeys()
    {
        $helper = Helper::getInstance();
        $method = new \ReflectionMethod(Helper::class, 'errorTreeKeyToString');
        $method->setAccessible(true);
        $this->assertNull($method->invoke($helper, new \stdClass()));
    }

    public function testGetMappedKeyWalksNestedMapAndFallsBack()
    {
        $helper = Helper::getInstance();
        $map = array('a' => array('b' => 'leaf'));
        $this->assertSame('leaf', $helper->getMappedKey($map, array('a', 'b')));
        $this->assertSame(array('a', 'missing'), $helper->getMappedKey($map, array('a', 'missing')));
    }

    public function testEnsureMappedKeyResultReturnsListOrFallback()
    {
        $helper = Helper::getInstance();
        $method = new \ReflectionMethod(Helper::class, 'ensureMappedKeyResult');
        $method->setAccessible(true);
        $this->assertSame(array('x', 'y'), $method->invoke($helper, array('x', 'y'), 'fb'));
        $this->assertSame('fb', $method->invoke($helper, array(), 'fb'));
        $this->assertSame('text', $method->invoke($helper, 'text', 'fb'));
    }

    public function testGetElementArrayPathMissingKeyReturnsNull()
    {
        $helper = Helper::getInstance();
        $this->assertNull($helper->getElement(array('meta', 'title'), array('other' => array())));
    }

    public function testGetElementRejectsNonStringLeafKey()
    {
        $helper = Helper::getInstance();
        $this->assertNull($helper->getElement(array('meta', 99), array('meta' => array(99 => 'x'))));
    }

    public function testGetElementUsesFormBaseGetElement()
    {
        $helper = Helper::getInstance();
        $form = new class(array('action' => '/f')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new \Ufw\Form\Element\Text('title'));
            }
        };
        $el = $helper->getElement('title', $form);
        $this->assertInstanceOf(\Ufw\Form\Element\Text::class, $el);
    }

    public function testGetMappedKeyReturnsScalarMapEntry()
    {
        $helper = Helper::getInstance();
        $this->assertSame('remote', $helper->getMappedKey(array('local' => 'remote'), 'local'));
    }

    public function testGetMappedKeyNestedMapEndsWithArrayLeaf()
    {
        $helper = Helper::getInstance();
        $map = array('a' => array('b' => array('c', 'd')));
        $this->assertSame(array('c', 'd'), $helper->getMappedKey($map, array('a', 'b')));
    }

    public function testNormalizeErrorTreeSkipsInvalidKeysViaNullString()
    {
        $helper = Helper::getInstance();
        $method = new \ReflectionMethod(Helper::class, 'normalizeErrorTreeFromArray');
        $method->setAccessible(true);
        $out = $method->invoke($helper, array('ok' => 'x', '' => 'empty-key'));
        $this->assertArrayHasKey('ok', $out);
    }

    public function testGetElementObjectWithoutGetSubFormReturnsNull()
    {
        $helper = Helper::getInstance();
        $this->assertNull($helper->getElement(array('a', 'b'), new \stdClass()));
    }
}
