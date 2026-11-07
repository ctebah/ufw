<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Db\Db;

class DbRowFieldArrayKeyTest extends TestCase
{
    public function testRowFieldArrayKeyCoversLegacyOffsetBranches(): void
    {
        $method = new \ReflectionMethod(Db::class, 'rowFieldArrayKey');
        $method->setAccessible(true);
        $this->assertSame(7, $method->invoke(null, 7));
        $this->assertSame('', $method->invoke(null, ''));
        $this->assertSame(42, $method->invoke(null, '42'));
        $this->assertSame(-3, $method->invoke(null, '-3'));
        $this->assertSame('abc', $method->invoke(null, 'abc'));
        $this->assertSame(1, $method->invoke(null, 1.9));
        $this->assertSame(0, $method->invoke(null, false));
        $this->assertSame(1, $method->invoke(null, true));
        $this->assertSame('', $method->invoke(null, null));
        $this->assertSame('', $method->invoke(null, array()));
    }
}
