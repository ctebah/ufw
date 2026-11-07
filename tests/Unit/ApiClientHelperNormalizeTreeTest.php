<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Helper;

class ApiClientHelperNormalizeTreeTest extends TestCase
{
    public function testNormalizeErrorTreeFromArraySkipsKeysThatDoNotStringify(): void
    {
        $helper = new class() extends Helper {
            protected function errorTreeKeyToString($key)
            {
                if ($key === 'drop') {
                    return null;
                }

                return parent::errorTreeKeyToString($key);
            }
        };
        $method = new \ReflectionMethod(Helper::class, 'normalizeErrorTreeFromArray');
        $method->setAccessible(true);
        $out = $method->invoke($helper, array(
            'keep' => 'err',
            'drop' => 'ignored',
        ));
        $this->assertSame(array('keep' => 'err'), $out);
    }
}
