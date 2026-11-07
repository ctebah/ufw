<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Placeholder;

class PlaceholderTest extends TestCase
{
    public function testAppendPrependSetAndGlue()
    {
        $slot = new Placeholder();
        $slot->append('b')->prepend('a')->append('c');
        $this->assertSame(array('a', 'b', 'c'), $slot->get());

        $slot->set('only');
        $this->assertSame('only', (string) $slot);

        $glued = new Placeholder();
        $glued->setGlue('|')->append('x')->append('y');
        $this->assertSame('x|y', (string) $glued);
        $this->assertSame('|', $glued->getGlue());
    }

    public function testCaptureStartFeedsAppendMode()
    {
        $slot = new Placeholder();
        $slot->captureStart('append');
        echo 'chunk';
        $slot->captureEnd();
        $this->assertSame('chunk', (string) $slot);
    }
}
