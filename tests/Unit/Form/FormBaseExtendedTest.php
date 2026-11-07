<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Text;
use Ufw\Form\Filter\Singleline;
use Ufw\Form\Validator\Required;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;

class FormBaseExtendedTest extends TestCase
{
    use FormFixtureConfig;

    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array_merge(self::formFixtureAppConfig(), array(
            'form' => array('helper' => 'bootstrap5'),
        )));
        \Ufw\Form\Base::setRenderHelperName('bootstrap5');
    }

    protected function tearDown(): void
    {
        \Ufw\Form\Base::setRenderHelperName(null);
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testMagicAccessOptionElementAndAttrib()
    {
        $form = new class(array('action' => '/gate', 'viewScript' => 'custom/form.html.php')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $this->assertSame('/gate', $form->action);
        $this->assertTrue(isset($form->title));
        $form->method = 'get';
        $this->assertSame('get', $form->getMethod());

        $replacement = new Text('other');
        $form->other = $replacement;
        $this->assertSame($replacement, $form->getElement('other'));
    }

    public function testFormToStringUsesRenderHelper()
    {
        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title', array('label' => 'Title')));
            }
        };
        $html = (string) $form;
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('title', $html);
    }

    public function testGetValuesSkipsIgnoredElementsAndIsValidAggregatesErrors()
    {
        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            protected function init()
            {
                $hidden = new Text('secret');
                $hidden->setIgnore(true);
                $hidden->setValue('skip-me');
                $this->addElement($hidden);
                $this->addElement(new Text('title', array('required' => true)));
            }
        };
        $values = $form->getValues();
        $this->assertArrayNotHasKey('secret', $values);
        $this->assertFalse($form->isValid());
        $this->assertTrue($form->getValidated());
        $this->assertNotEmpty($form->getErrors());
    }

    public function testExplicitViewScriptOverridesAutoPath()
    {
        $form = new class(array('action' => '/gate')) extends \Ufw\Form\Base {
            protected $autoViewScript = true;

            protected function init()
            {
            }
        };
        $form->setViewScript('forms/custom.html.php');
        $this->assertSame('forms/custom.html.php', $form->getViewScript());
    }

    public function testGetRenderHelperNameReadsApplicationFormConfig()
    {
        $form = new class(array('action' => '/gate')) extends \Ufw\Form\Base {
            protected function init()
            {
            }
        };
        $this->assertSame('bootstrap5', $form->getRenderHelperName());
    }

    public function testPopulateSkipsBracketElementWhenNestedValueMissing()
    {
        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('group[field]'));
                $this->addElement(new Text('plain'));
            }
        };
        $form->populate(array('plain' => 'ok'));
        $this->assertNull($form->getElement('group[field]')->getValue());
        $this->assertSame('ok', $form->getElement('plain')->getValue());
    }

    public function testPopulateTreatsFailedPregSplitAsEmptyParts()
    {
        $form = new class(array('action' => '/save')) extends \Ufw\Form\Base {
            /** @var bool */
            public $failNextSplit = false;

            protected function init()
            {
            }

            protected function pregSplitBracketElementName($elementName)
            {
                if ($this->failNextSplit) {
                    $this->failNextSplit = false;

                    return false;
                }

                return parent::pregSplitBracketElementName($elementName);
            }
        };
        $method = new \ReflectionMethod(\Ufw\Form\Base::class, 'splitBracketElementNameParts');
        $method->setAccessible(true);
        $form->failNextSplit = true;
        $this->assertSame(array(), $method->invoke($form, 'group[field]'));
    }

    public function testPostInitSkipsActionWhenUrlHelperUnavailable()
    {
        $app = (new \ReflectionClass(\Ufw\Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('routes' => array())));
        \Ufw\Registry::getInstance()->set('application', $app);
        $form = new class(array()) extends \Ufw\Form\Base {
            protected function init()
            {
            }

            public function getHelper($name)
            {
                return null;
            }
        };
        $postInit = new \ReflectionMethod(\Ufw\Form\Base::class, 'postInit');
        $postInit->setAccessible(true);
        $postInit->invoke($form);
        $this->assertNull($form->getAttrib('action'));
        \Ufw\Registry::getInstance()->remove('application');
    }

    public function testPostInitPostActionAppendsQueryString()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $_SERVER['QUERY_STRING'] = 'debug=1';
        $form = new class(array('method' => 'post')) extends \Ufw\Form\Base {
            protected function init()
            {
            }

            public function getHelper($name)
            {
                if ($name !== 'url') {
                    return null;
                }
                return new class extends \Ufw\Helper\Base {
                    public function url($params = array(), $reset = true, $withQS = false, $routeName = null, $encode = true)
                    {
                        return '/gate-form';
                    }
                };
            }
        };
        $postInit = new \ReflectionMethod(\Ufw\Form\Base::class, 'postInit');
        $postInit->setAccessible(true);
        $postInit->invoke($form);
        $this->assertSame('/gate-form?debug=1', $form->getAction());
        unset($_SERVER['QUERY_STRING']);
    }

    public function testGetApplicationThrowsWhenRegistryHasNoApplication()
    {
        \Ufw\Registry::getInstance()->remove('application');
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
            }
        };
        $this->expectException(\RuntimeException::class);
        $form->getApplication();
    }

    public function testGetRenderHelperNameFallsBackToBootstrap3WithoutConfig()
    {
        \Ufw\Form\Base::setRenderHelperName(null);
        $app = (new \ReflectionClass(\Ufw\Application::class))->newInstanceWithoutConstructor();
        $app->setConfig(array('dispatch' => array('routes' => array())));
        \Ufw\Registry::getInstance()->set('application', $app);
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
            }
        };
        $this->assertSame('bootstrap3', $form->getRenderHelperName());
        \Ufw\Form\Base::setRenderHelperName(null);
        \Ufw\Registry::getInstance()->remove('application');
    }
}
