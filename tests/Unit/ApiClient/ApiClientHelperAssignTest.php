<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Helper;

class ApiClientHelperAssignTest extends TestCase
{
    public function testAssignValueAtPathWithEmptyPathReturnsPayloadUntouched()
    {
        $helper = Helper::getInstance();
        $inner = new \ReflectionMethod(Helper::class, 'assignValueAtPath');
        $inner->setAccessible(true);
        $payload = array('keep' => 1);
        $this->assertSame($payload, $inner->invoke($helper, $payload, array(), 'ignored'));
    }

    public function testAssignValueToKeyWithEmptyBracketPartsSkipsEmptySegments()
    {
        $helper = Helper::getInstance();
        $out = $helper->assignValueToKey(array(), 'field[]', 'v');
        $this->assertSame('v', $out['field']);
    }

    public function testGetElementReturnsNullWhenPathMissing()
    {
        $helper = Helper::getInstance();
        $this->assertNull($helper->getElement('missing[path]', array('other' => 1)));
    }

    public function testAssignValueToKeyTreatsFailedPregSplitAsEmptyPath()
    {
        $helper = new ApiClientHelperPregSplitProbe();
        $helper->failNextBracketSplit = true;
        $payload = array('keep' => 1);
        $this->assertSame($payload, $helper->assignValueToKey($payload, 'meta[title]', 'ignored'));
    }

    public function testGetElementTreatsFailedPregSplitAsEmptyPath()
    {
        $helper = new ApiClientHelperPregSplitProbe();
        $helper->failNextBracketSplit = true;
        $this->assertNull($helper->getElement('meta[title]', array('meta' => array('title' => 'x'))));
    }
}

class ApiClientHelperPregSplitProbe extends Helper
{
    /** @var bool */
    public $failNextBracketSplit = false;

    protected function pregSplitBracketNotation($key)
    {
        if ($this->failNextBracketSplit) {
            $this->failNextBracketSplit = false;

            return false;
        }

        return parent::pregSplitBracketNotation($key);
    }
}
