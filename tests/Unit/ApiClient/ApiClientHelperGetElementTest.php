<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Helper;
use Ufw\Form\Element\Text;
use Ufw\Form\Base;

class ApiClientHelperGetElementTest extends TestCase
{
    protected function tearDown(): void
    {
        \Ufw\Tests\Support\ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testGetElementWalksBracketPathsOnArrayAndForm()
    {
        \Ufw\Tests\Support\ApplicationFixtures::bareWithController(array(
            'dispatch' => array('routes' => array(), 'controllers' => array('ping')),
        ));
        $app = \Ufw\Registry::getInstance()->get('application');
        $psr = new \Ufw\ServerRequest(
            array(),
            array(),
            '/',
            'GET',
            'php://input',
            array(),
            array(),
            array(),
            null,
            '1.1',
            array('controller' => 'ping', 'action' => 'index')
        );
        $router = new \Ufw\Router($app, new \Ufw\Request(array(), array(), array(), null, $psr), array(
            'default' => array(
                'type' => \Ufw\Route\Literal::class,
                'path' => '/',
                'defaults' => array('controller' => 'ping', 'action' => 'index'),
            ),
        ));
        $router->match();
        $app->setRouter($router);

        $helper = Helper::getInstance();
        $arrayForm = array('meta' => array('title' => 'From array'));
        $this->assertSame('From array', $helper->getElement('meta[title]', $arrayForm));

        $form = new class(array()) extends Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $form->populate(array('title' => 'From form'));
        $this->assertInstanceOf(Text::class, $helper->getElement('title', $form));

        $valueMethod = new \ReflectionMethod(Helper::class, '_getElementValue');
        $valueMethod->setAccessible(true);
        $this->assertSame('From form', $valueMethod->invoke($helper, 'title', $form));
    }

    public function testEnsureMappedKeyResultAndNestedMapBranches()
    {
        $helper = Helper::getInstance();
        $ensure = new \ReflectionMethod(Helper::class, 'ensureMappedKeyResult');
        $ensure->setAccessible(true);
        $this->assertSame('leaf', $ensure->invoke($helper, 'leaf', 'fallback'));
        $this->assertSame(array('a', 1), $ensure->invoke($helper, array('a', 1), 'fallback'));

        $map = array('x' => array('y' => 'mapped'));
        $this->assertSame('mapped', $helper->getMappedKey($map, array('x', 'y')));
        $this->assertSame(array('missing', 'key'), $helper->getMappedKey($map, array('missing', 'key')));
    }

    public function testGetErrorPathsCollectsLeafMessages()
    {
        $helper = Helper::getInstance();
        $paths = $helper->getErrorPaths(
            array('user' => 'user'),
            array('user' => array('email' => 'Invalid'))
        );
        $this->assertNotEmpty($paths);
    }
}
