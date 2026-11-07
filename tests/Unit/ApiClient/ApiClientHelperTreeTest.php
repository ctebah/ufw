<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Helper;

class ApiClientHelperTreeTest extends TestCase
{
    public function testAssignValueAtPathMergesExistingNestedArrays()
    {
        $helper = Helper::getInstance();
        $merged = $helper->assignValueToKey(
            array('meta' => array('x' => 1, 0 => 'drop')),
            array('meta', 'title'),
            'Hello'
        );
        $this->assertSame(1, $merged['meta']['x']);
        $this->assertSame('Hello', $merged['meta']['title']);
    }

    public function testNormalizeErrorTreeKeepsNumericKeysAsStrings()
    {
        $helper = Helper::getInstance();
        $tree = $helper->normalizeErrorTree(array(0 => 'root', 'field' => array(1 => 'nested')));
        $this->assertSame('root', $tree['0']);
        $this->assertSame('nested', $tree['field']['1']);
    }

    public function testGetMappedKeyWalksNestedMap()
    {
        $helper = Helper::getInstance();
        $map = array('user' => array('name' => 'profileName'));
        $this->assertSame('profileName', $helper->getMappedKey($map, array('user', 'name')));
    }
}
