<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Controller\Base as ControllerBase;
use Ufw\Helper\Menu;
use Ufw\Registry;
use Ufw\Request;
use Ufw\Response;

/**
 *  */
class MenuHelperRegressionTest extends TestCase
{
    protected function tearDown(): void
    {
        Registry::getInstance()->remove('application');
        parent::tearDown();
    }

    public function testBuildTreeResolvesCallableCaptionBeforeRender()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        Registry::getInstance()->set('application', $app);
        $controller = new MenuRegressionController($app);
        $menu = new Menu();
        $menu->setController($controller);
        $buildTree = new \ReflectionMethod(Menu::class, 'buildTree');
        $buildTree->setAccessible(true);
        $config = array(
            array(
                'caption' => function () {
                    return new MenuCaptionStub('SignIn');
                },
                'content' => array('href' => '/login'),
            ),
        );
        $tree = $buildTree->invoke($menu, $config, '/current');

        $this->assertArrayHasKey(0, $tree);
        $this->assertInstanceOf(MenuCaptionStub::class, $tree[0]['caption']);
        $this->assertSame('SignIn', (string) $tree[0]['caption']);
    }
}

class MenuCaptionStub
{
    /** @var string */
    private $label;

    public function __construct($label)
    {
        $this->label = (string) $label;
    }

    public function __toString()
    {
        return $this->label;
    }
}

class MenuRegressionController extends ControllerBase
{
    public function __construct(Application $application)
    {
        parent::__construct($application);
    }

    public function url($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
    {
        return '/current';
    }
}
