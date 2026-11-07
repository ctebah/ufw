<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Utils\Asset\Css;
use Ufw\Utils\Asset\Js;

class AssetUtilsTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testJsCompilesCdnScriptsForMatchingPosition()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $js = new Js();
        $js->setApplication($app)
            ->setPosition(Js::POS_HEAD)
            ->init(array(
                'cdn' => array(
                    array('src' => '/a.js', 'position' => Js::POS_HEAD),
                    array('src' => '/skip.js', 'position' => Js::POS_BODY_BOTTOM),
                ),
            ));

        $html = (string) $js;
        $this->assertStringContainsString('/a.js', $html);
        $this->assertStringNotContainsString('/skip.js', $html);
        $this->assertTrue($js->isInitialized());
    }

    public function testJsCompilesNestedLocalBundlesAtBodyBottom()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $js = new Js();
        $js->setApplication($app)->init(array(
            'local' => array(
                array('/one.js', '/two.js'),
                '/three.js',
            ),
        ));

        $html = (string) $js;
        $this->assertStringContainsString('/one.js', $html);
        $this->assertStringContainsString('/two.js', $html);
        $this->assertStringContainsString('/three.js', $html);
    }

    public function testCssCompilesCdnAndLocalLinks()
    {
        list($app) = ApplicationFixtures::bareWithController();
        $css = new Css();
        $css->setApplication($app)->init(array(
            'cdn' => array('/theme.css'),
            'local' => array('/local.css'),
        ));

        $html = (string) $css;
        $this->assertStringContainsString('rel="stylesheet"', $html);
        $this->assertStringContainsString('/theme.css', $html);
        $this->assertStringContainsString('/local.css', $html);
    }

    public function testUninitializedAssetRendersEmptyString()
    {
        $js = new Js();
        $this->assertSame('', (string) $js);
    }
}
