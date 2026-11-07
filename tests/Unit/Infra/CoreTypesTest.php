<?php
namespace Ufw\Tests\Unit\Infra;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Component\Csrf;
use Ufw\Envs;
use Ufw\HttpHeaderSanitizer;
use Ufw\Paths;
use Ufw\Registry;
use Ufw\Tests\Support\RuntimeProbe;

class CoreTypesTest extends TestCase
{
    protected function tearDown(): void
    {
        Envs::setRuntimeEnvLabel(null);
        Paths::setApplicationPath(TestAppPaths::app());
        $registry = Registry::getInstance();
        if ($registry->has('env')) {
            $registry->remove('env');
        }
        parent::tearDown();
    }

    public function testFilterStringKeyedArrayKeepsStringKeysOnly()
    {
        if (!function_exists('Ufw\\filter_string_keyed_array')) {
            $this->markTestSkipped('filter_string_keyed_array is not loaded on this baseline.');
        }
        $this->assertSame(array('a' => 1), \Ufw\filter_string_keyed_array(array('a' => 1, 0 => 'skip')));
        $this->assertSame(array(), \Ufw\filter_string_keyed_array(null));
    }

    public function testPregGrepKeysFiltersByPattern()
    {
        if (!function_exists('Ufw\\preg_grep_keys')) {
            $this->markTestSkipped('preg_grep_keys is not loaded on this baseline.');
        }
        $input = array('foo' => 1, 'bar' => 2, 'food' => 3);
        $this->assertSame(array('foo' => 1, 'food' => 3), \Ufw\preg_grep_keys('/^foo/', $input));
    }

    public function testArrayGetAndUnsetRemovesKey()
    {
        $bag = array('keep' => 1, 'take' => 2);
        $this->assertSame(2, \Ufw\array_get_and_unset($bag, 'take', null));
        $this->assertSame(array('keep' => 1), $bag);
        $this->assertSame('d', \Ufw\array_get_and_unset($bag, 'missing', 'd'));
    }

    public function testHttpHeaderSanitizerTrimsScalarsAndLists()
    {
        $this->assertSame('Name', HttpHeaderSanitizer::sanitizeName(' Name '));
        $this->assertSame('v', HttpHeaderSanitizer::sanitizeValue(' v '));
        $this->assertSame(array('a', 'b'), HttpHeaderSanitizer::sanitizeValue(array(' a ', ' b ')));
        if (RuntimeProbe::httpHeaderSanitizerCoercesNonStringScalarsToEmpty()) {
            $this->assertSame('', HttpHeaderSanitizer::sanitizeValue(7));
        } else {
            $this->assertSame(7, HttpHeaderSanitizer::sanitizeValue(7));
        }
        $headers = HttpHeaderSanitizer::sanitizeHeadersArray(array(
            ' X-Test ' => array(' one ', ' two '),
        ));
        $this->assertSame(array('one', 'two'), $headers['X-Test']);
    }

    public function testPathsAndEnvsResolveFromOverrides()
    {
        Paths::setApplicationPath('/tmp/app-root');
        $this->assertSame('/tmp/app-root', Paths::application());

        Envs::setRuntimeEnvLabel('staging');
        $this->assertSame('staging', Envs::runtimeLabel());
    }

    public function testEnvsReadsApplicationConfigBeforeRegistry()
    {
        $registry = Registry::getInstance();
        $hadApplication = $registry->has('application');
        $previousApp = $hadApplication ? $registry->get('application') : null;
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('env' => 'from-config'));
        $registry->set('application', $app);
        Envs::setRuntimeEnvLabel(null);
        try {
            $this->assertSame('from-config', Envs::runtimeLabel());
            $registry->set('env', 'registry-env');
            $this->assertSame('from-config', Envs::runtimeLabel());
        } finally {
            if ($hadApplication) {
                $registry->set('application', $previousApp);
            } else {
                $registry->remove('application');
            }
        }
    }

    public function testEnvsReadsRegistryEnvWhenApplicationConfigMissing()
    {
        $registry = Registry::getInstance();
        $hadApplication = $registry->has('application');
        $previousApp = $hadApplication ? $registry->get('application') : null;
        if ($hadApplication) {
            $registry->remove('application');
        }
        Envs::setRuntimeEnvLabel(null);
        $registry->set('env', 'registry-env');
        try {
            $this->assertSame('registry-env', Envs::runtimeLabel());
        } finally {
            $registry->remove('env');
            if ($hadApplication) {
                $registry->set('application', $previousApp);
            }
        }
    }

    public function testPathsExceptionLogFilePathVariants()
    {
        $default = Paths::exceptionLogFilePath(null);
        $this->assertStringContainsString('/log/exceptions.txt', $default);

        $abs = Paths::exceptionLogFilePath(array(
            'logging' => array('exception_log_path' => 'C:/logs/err.txt'),
        ));
        $this->assertSame('C:/logs/err.txt', $abs);

        $this->assertNull(Paths::exceptionLogFilePath(array(
            'logging' => array('exception_log_path' => false),
        )));

        $rel = Paths::exceptionLogFilePath(array(
            'logging' => array('exception_log_path' => 'log/custom.txt'),
        ));
        $this->assertStringEndsWith('/log/custom.txt', str_replace('\\', '/', $rel));
    }

    public function testCsrfTokenValidateAndRotate()
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = array();

        $token = Csrf::token('unit');
        $this->assertNotSame('', $token);
        $this->assertTrue(Csrf::validate('unit', $token));
        $this->assertFalse(Csrf::validate('unit', 'wrong'));
        $this->assertSame(Csrf::DEFAULT_FIELD_NAME, Csrf::getDefaultFieldName());

        Csrf::rotate('unit');
        $this->assertFalse(Csrf::validate('unit', $token));
        $this->assertTrue(Csrf::validate('unit', Csrf::token('unit')));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testEnvsFallsBackToApplicationEnvConstant()
    {
        if (!\defined('APPLICATION_ENV')) {
            define('APPLICATION_ENV', 'from-constant');
        }
        Envs::setRuntimeEnvLabel(null);
        $registry = Registry::getInstance();
        $hadApplication = $registry->has('application');
        if ($hadApplication) {
            $registry->remove('application');
        }
        try {
            $this->assertSame('from-constant', Envs::runtimeLabel());
        } finally {
            if ($hadApplication) {
                $registry->set('application', (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor());
            }
        }
    }
}
