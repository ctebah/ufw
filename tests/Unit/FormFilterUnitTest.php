<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Filter\Singleline;
use Ufw\Form\Filter\Trim;

class FormFilterUnitTest extends TestCase
{
    public function testTrimRecursesAndStripsWhitespace()
    {
        $filter = new Trim();
        $this->assertSame('ab', $filter->filter('  ab  '));
        $this->assertSame(array('x' => 'y'), $filter->filter(array('x' => " \ty\n ")));

        $custom = new Trim('.');
        $this->assertSame('x', $custom->filter('..x..'));
    }

    public function testSinglelineCollapsesLineBreaks()
    {
        $filter = new Singleline();
        $this->assertSame('a b c', $filter->filter("a\rb\nc"));
        $this->assertSame(array('t' => '1 2'), $filter->filter(array('t' => "1\n2")));
    }
}
