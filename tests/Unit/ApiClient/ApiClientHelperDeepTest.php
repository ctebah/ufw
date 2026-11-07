<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Component\ApiClient\Helper;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Component\ApiClient\Client;

class ApiClientHelperDeepTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        parent::tearDown();
    }

    public function testCreatePayloadMapsMultipleBracketFields()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new class(array('action' => '/gate')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $form->populate(array('title' => 'Hello'));
        $api->setForm($form);

        $helper = Helper::getInstance();
        $payload = $helper->createPayload($api, array('meta[title]'), $form, array('meta[title]' => 'title'));
        $this->assertSame('Hello', $payload['meta']['title']);
    }

    public function testAssignValueToKeyWithArrayPathAndNormalizeEdgeCases()
    {
        $helper = Helper::getInstance();
        $payload = $helper->assignValueToKey(array(), array('a', 'b'), 'deep');
        $this->assertSame('deep', $payload['a']['b']);

        $this->assertSame(array(), $helper->normalizeStringKeyedArray('nope'));
        $this->assertSame(array(), $helper->normalizeErrorTree('bad'));

        $tree = $helper->normalizeErrorTree(array('errors' => array(array('x' => 'y'))));
        $this->assertArrayHasKey('errors', $tree);
    }

    public function testGetElementUsesSubFormObjectGraph()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));

        $sub = new class(array('action' => '/sub')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $sub->populate(array('title' => 'Nested'));

        $parent = new class($sub) {
            /** @var \Ufw\Form\Base */
            private $sub;

            public function __construct($sub)
            {
                $this->sub = $sub;
            }

            public function getSubForm($name)
            {
                return $name === 'meta' ? $this->sub : null;
            }
        };

        $helper = Helper::getInstance();
        $el = $helper->getElement(array('meta', 'title'), $parent);
        $this->assertInstanceOf(Text::class, $el);
        $this->assertSame('Nested', $el->getValue());
    }

    public function testGetMappedKeyArrayFallbackAndErrorPathLeaves()
    {
        $helper = Helper::getInstance();
        $mapped = new \ReflectionMethod(Helper::class, '_getMappedKeyArray');
        $mapped->setAccessible(true);
        $this->assertSame('only', $mapped->invoke($helper, array('a' => 'only'), array('a', 'missing')));

        $paths = $helper->getErrorPaths(
            array('field' => 'field'),
            array('field' => 'Required message')
        );
        $this->assertSame(array(array()), $paths);
    }
}
