<?php
namespace Ufw\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Envs;
use Ufw\Helper\Menu;
use Ufw\Registry;

class MenuFileDefinitionTest extends TestCase
{
    protected function tearDown(): void
    {
        Envs::setRuntimeEnvLabel(null);
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testMenuLoadsDefinitionFromIncludePath()
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ufw-menu-gate-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($file, '<?php return array(array("caption" => "From file", "content" => array("href" => "/file")));');

        try {
            $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
            Registry::getInstance()->set('application', $app);
            $controller = new class($app) extends ControllerBase {
                public function partial($script, $vars = array())
                {
                    return 'menu:' . (isset($vars['items'][0]['caption']) ? $vars['items'][0]['caption'] : '');
                }

                public function url($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
                {
                    return '/file';
                }
            };
            $menu = new Menu();
            $menu->setController($controller);
            $menu($file);
            $html = $menu->render();
            $this->assertStringContainsString('From file', $html);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
