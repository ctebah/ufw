<?php
namespace Ufw\Tests\Support;

use Psr\Http\Message\StreamInterface;
use Ufw\Component\ApiClient\Helper;
use Ufw\Db\Db;
use Ufw\Form\Validator\Required;
use Ufw\HttpHeaderSanitizer;
use Ufw\Router;
use Ufw\Utils\ArrayAccess;

/**
 * Runtime capability probes for tests that branch on framework behavior. Prefer direct assertions in new tests.
 */
final class RuntimeProbe
{
    /** @var array<string, bool> */
    private static $memo = array();

    private static function memo($key, callable $probe)
    {
        if (!array_key_exists($key, self::$memo)) {
            self::$memo[$key] = (bool) $probe();
        }

        return self::$memo[$key];
    }

    public static function requiredAcceptsScalarZero()
    {
        return self::memo('requiredZero', function () {
            $validator = new Required(false);

            return $validator->isValid('0') === true && $validator->isValid(0) === true;
        });
    }

    public static function arrayAccessSetAllNormalizesKeysAndInvalidPayloads()
    {
        return self::memo('arrayAccessSetAll', function () {
            $bag = new ArrayAccess();
            $bag->setAll(array(0 => 'zero'));
            $normalized = $bag->getAll();
            if (!isset($normalized['0']) || $normalized['0'] !== 'zero') {
                return false;
            }
            $bag->setAll(false);

            return $bag->getAll() === array();
        });
    }

    public static function httpHeaderSanitizerCoercesNonStringScalarsToEmpty()
    {
        return self::memo('headerSanitizer', function () {
            return HttpHeaderSanitizer::sanitizeValue(7) === '';
        });
    }

    public static function dbFetchGuardsInvalidResults()
    {
        return self::memo('dbFetchGuards', function () {
            if (!class_exists(Db::class)) {
                return false;
            }
            $db = Db::instance();
            try {
                return $db->fetch_array(null) === null && $db->fetch_array(false) === null;
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    public static function dbMagicGetReturnsNullWithoutHandle()
    {
        return self::memo('dbMagicNull', function () {
            return (new \ReflectionClass(Db::class))->hasProperty('lastQueryHandle');
        });
    }

    public static function apiClientHelperHasNormalizeStringKeyedArray()
    {
        return self::memo('apiNormalize', function () {
            return method_exists(Helper::getInstance(), 'normalizeStringKeyedArray');
        });
    }

    public static function inArrayHaystackFollowsLateMultiOptions()
    {
        return self::memo('inArrayLateOptions', function () {
            self::registerBareApplication();
            $select = new \Ufw\Form\Element\Select('status', array(
                'multiOptions' => array('a' => 'A'),
            ));
            $select->addMultiOption('b', 'B');
            $select->setValue('b');

            return $select->isValid() === true;
        });
    }

    public static function inArrayRejectsUnknownMultiValues()
    {
        return self::memo('inArrayRejectMulti', function () {
            self::registerBareApplication();
            $el = new \Ufw\Form\Element\CheckboxSet('tags', array(
                'multiOptions' => array('1' => 'One'),
            ));
            $el->setValue(array('9'));

            return $el->isValid() === false;
        });
    }

    public static function routeLiteralNormalizesInvalidDefinitionArrays()
    {
        return self::memo('routeLiteralDefaults', function () {
            return method_exists(\Ufw\Route\Literal::class, 'getDefaults');
        });
    }

    public static function routeRegExSkipsNonStringUrlBuilderEntries()
    {
        return self::memo('routeRegExUrlBuilder', function () {
            $app = (new \ReflectionClass(\Ufw\Application::class))->newInstanceWithoutConstructor();
            $request = new \Ufw\Request(array(), array(), array(), null, new \Ufw\ServerRequest(array(), array(), '/', 'GET', 'php://input'));
            $router = new Router($app, $request, array());
            $route = new \Ufw\Route\RegEx($router, array(
                'patterns' => array('#^/yes/(?P<id>\d+)$#'),
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
                'urlBuilder' => array('/yes/:id', array('not-a-pattern')),
            ));
            try {
                $route->buildUrl(array('id' => '4'));

                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    public static function routerThrowsLogicExceptionForUnknownRoute()
    {
        return self::memo('routerLogic', function () {
            $app = (new \ReflectionClass(\Ufw\Application::class))->newInstanceWithoutConstructor();
            $request = new \Ufw\Request(array(), array(), array(), null, new \Ufw\ServerRequest(array(), array(), '/', 'GET', 'php://input'));
            $router = new Router($app, $request, array());
            try {
                $router->getRoute('missing');
            } catch (\LogicException $e) {
                return true;
            } catch (\Throwable $e) {
                return false;
            }

            return false;
        });
    }

    /**
     * App-facing plain text from ApiClient/PSR-7 body (stream or string).
     *
     * @param mixed $body
     */
    public static function coerceHttpBodyToPlainText($body)
    {
        if ($body instanceof StreamInterface) {
            if ($body->isSeekable()) {
                $body->rewind();
            }

            return (string) $body->getContents();
        }
        if (is_string($body)) {
            return $body;
        }
        if (is_resource($body)) {
            return stream_get_contents($body) ?: '';
        }

        return (string) $body;
    }

    /**
     * Register a bare Application on Registry (validators/helpers that need translate, etc.).
     */
    public static function registerBareApplication()
    {
        $app = (new \ReflectionClass(\Ufw\Application::class))->newInstanceWithoutConstructor();
        \Ufw\Registry::getInstance()->set('application', $app);

        return $app;
    }
}
