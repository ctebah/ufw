<?php
namespace Ufw\Tests\Unit\JsOut;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Helper\Placeholder as HelperPlaceholder;
use Ufw\JsOut;
use Ufw\Placeholder;
use Ufw\Registry;
use Ufw\Tests\Support\ApplicationFixtures;

class JsOutTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testReadyPlaceholderScriptsOnlyAndWrapperMode()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array());
        Registry::getInstance()->set('application', $app);

        $jsOut = JsOut::getInstance();
        $placeholderOnly = new \ReflectionMethod(JsOut::class, 'placeholderScriptsOnlyOutput');
        $placeholderOnly->setAccessible(true);
        $this->assertIsString($placeholderOnly->invoke($jsOut));

        $mode = new \ReflectionMethod(JsOut::class, 'getReadyWrapperMode');
        $mode->setAccessible(true);
        $this->assertNotEmpty($mode->invoke($jsOut));

        $minify = new \ReflectionMethod(JsOut::class, '_minify');
        $minify->setAccessible(true);
        $jsOut->readyPlaceholder();
        $minify->invoke($jsOut);

        $gzip = new \ReflectionMethod(JsOut::class, '_gzip');
        $gzip->setAccessible(true);
        $gzip->invoke($jsOut);
    }

    public function testReadyPlaceholderWritesBundleWhenStorageConfigured()
    {
        $storage = sys_get_temp_dir() . '/ufw-jsout-gate-' . getmypid();
        if (!is_dir($storage)) {
            mkdir($storage, 0775, true);
        }
        try {
            list($app, $controller) = ApplicationFixtures::bareWithController(array(
                'jsOutStorage' => array(
                    'webpath' => '/static/js/gate',
                    'absrootpath' => $storage,
                    'serve-minified' => false,
                    'readyWrapper' => 'classic',
                ),
            ));
            $placeholder = $app->getHelper('placeholder', $controller);
            $this->assertInstanceOf(HelperPlaceholder::class, $placeholder);
            $placeholder('jsReady')->append('console.log(1);');

            $html = JsOut::newInstance()->readyPlaceholder();
            $this->assertStringContainsString('/static/js/gate/', $html);
            $this->assertStringContainsString('.js', $html);
        } finally {
            if (is_dir($storage)) {
                $this->removeDirectoryTree($storage);
            }
            ApplicationFixtures::tearDownRegistry();
        }
    }

    private function removeDirectoryTree($dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectoryTree($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testFetchPlaceholderStringifiesPlaceholderSlot()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $slot = $app->getHelper('placeholder')->placeholder('jsInline');
        $this->assertInstanceOf(Placeholder::class, $slot);
        $slot->append('grecaptcha.render();');

        $jsOut = JsOut::getInstance();
        if (!method_exists(JsOut::class, 'fetchPlaceholder')) {
            $this->markTestSkipped('fetchPlaceholder is internal to newer JsOut refactors.');
        }
        $method = new \ReflectionMethod(JsOut::class, 'fetchPlaceholder');
        $method->setAccessible(true);

        $this->assertSame('grecaptcha.render();', $method->invoke($jsOut, 'jsInline'));
    }
}
