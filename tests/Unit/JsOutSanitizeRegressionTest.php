<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\JsOut;

/**
 * Regression for 1.0.1: do not collapse {@code ;;} inside JS string literals during sanitize/minify prep.
 *
 *  */
class JsOutSanitizeRegressionTest extends TestCase
{
    public function testSanitizeDoesNotCollapseDoubleSemicolonInsideStringLiteral()
    {
        $jsOut = JsOut::getInstance();
        $ref = new \ReflectionClass(JsOut::class);
        $prop = $ref->getProperty('jsContent');
        $prop->setAccessible(true);
        $prop->setValue($jsOut, array(
            'ready' => '',
            'ext' => '',
            'inline' => 'var s = "a;;b";',
        ));
        $doConcat = $ref->getProperty('doConcatenate');
        $doConcat->setAccessible(true);
        $doConcat->setValue($jsOut, false);

        $sanitize = $ref->getMethod('_sanitize');
        $sanitize->setAccessible(true);
        $sanitize->invoke($jsOut);

        $content = $prop->getValue($jsOut);
        $this->assertStringContainsString(';;', $content['inline']);
    }
}
