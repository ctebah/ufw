<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Helper\Base as HelperBase;
use Ufw\Registry;

class HelperBaseTest extends TestCase
{
    protected function tearDown(): void
    {
        $registry = Registry::getInstance();
        if ($registry->has('application')) {
            $registry->remove('application');
        }
        if ($registry->has('translator')) {
            $registry->remove('translator');
        }
        parent::tearDown();
    }

    public function testMagicCallResolvesNamedHelperWithoutControllerMethod()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);

        $controller = new class($app) extends ControllerBase {
        };
        $app->setCurrentController($controller);

        Registry::getInstance()->set('translator', function ($str) {
            return 't:' . $str;
        });

        $helper = new class extends HelperBase {
            /**
             * @return string
             */
            public function proxyTranslate($str)
            {
                return $this->translate($str);
            }
        };
        $helper->setController($controller);

        $this->assertSame('t:hello', $helper->proxyTranslate('hello'));
    }
}
