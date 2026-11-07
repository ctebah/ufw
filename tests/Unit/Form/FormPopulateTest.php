<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Application;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Singleline;
use Ufw\Registry;
use Ufw\Router;
use Ufw\Route\Literal;
use Ufw\ServerRequest;
use Ufw\Request;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;

class FormPopulateTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        $_SERVER = array();
        parent::tearDown();
    }

    public function testPopulateBracketNamesGetValuesAndFilterArrayDefinition()
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('group[field]'));
                $this->addElement(new Text('plain'));
            }
        };
        $form->populate(array(
            'group' => array('field' => 'nested'),
            'plain' => 'flat',
        ));
        $values = $form->getValues();
        $this->assertSame('flat', $values['plain']);
        $this->assertSame('nested', $form->getElement('group[field]')->getValue());

        $el = new Text('t');
        $el->addFilter(array('name' => 'singleline', 'options' => array()));
        $this->assertInstanceOf(Singleline::class, $el->getFilter('singleline'));
    }

    public function testPostInitSetsActionFromRouterAndQueryString()
    {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array(
            'form' => array('helper' => 'bootstrap5'),
            'dispatch' => array('controllers' => array('ping')),
        ));
        Registry::getInstance()->set('application', $app);
        $controller = new class($app) extends \Ufw\Controller\Base {
            public function url($params = array(), $reset = true, $withQS = false, $routeName = 'default', $encode = true)
            {
                return '/staff/save';
            }
        };
        $app->setCurrentController($controller);
        $_SERVER['QUERY_STRING'] = 'tab=1';
        $request = new Request(array(), array(), array(), null, new ServerRequest(
            array('REQUEST_URI' => '/staff/save'),
            array(),
            '/staff/save',
            'POST',
            'php://input'
        ));
        $router = new Router($app, $request, array(
            'save' => array(
                'type' => Literal::class,
                'path' => '/staff/save',
                'defaults' => array('controller' => 'ping', 'action' => 'save'),
            ),
        ));
        $router->match();
        $app->setRouter($router);
        $app->setRequest($request);

        $form = new class(array()) extends \Ufw\Form\Base {
            protected $autoViewScript = true;

            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $this->assertStringContainsString('/staff/save', $form->getAction());
        $this->assertStringContainsString('tab=1', $form->getAction());
        $viewScript = $form->getViewScript();
        $this->assertIsString($viewScript);
        $this->assertStringContainsString('form/', $viewScript);
    }
}
