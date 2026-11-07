<?php
namespace Ufw\Tests\Support;

use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Registry;

final class ApplicationFixtures
{
    /**
     * @return array{0: Application, 1: ControllerBase}
     */
    public static function bareWithController(array $config = array())
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        if ($config !== array()) {
            $app->setConfig($config);
        }
        Registry::getInstance()->set('application', $app);
        $controller = new class($app) extends ControllerBase {
            /** @var object|null */
            public $user;
        };
        $app->setCurrentController($controller);

        return array($app, $controller);
    }

    public static function tearDownRegistry()
    {
        $registry = Registry::getInstance();
        if ($registry->has('application')) {
            $registry->remove('application');
        }
        if ($registry->has('translator')) {
            $registry->remove('translator');
        }
        if ($registry->has('env')) {
            $registry->remove('env');
        }
        self::resetApplicationStaticConfig();
    }

    public static function resetApplicationStaticConfig()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $configPath = TestAppPaths::app() . '/config/app.php';
        $cfg = is_file($configPath) ? include $configPath : array();
        $app->setConfig(is_array($cfg) ? $cfg : array());
    }
}
