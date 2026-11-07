<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Helper;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;

class ApiClientHelperRemainingTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        parent::tearDown();
    }

    public function testCreatePayloadUsesApiFormWhenArgumentOmitted()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new class(array('action' => '/gate')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('title'));
            }
        };
        $form->populate(array('title' => 'FromApi'));
        $api->setForm($form);

        $payload = Helper::getInstance()->createPayload($api, array('title'));
        $this->assertSame('FromApi', $payload['title']);
    }

    public function testAssignValueToKeyMergesIntoExistingNestedArray()
    {
        $helper = Helper::getInstance();
        $payload = array('meta' => array('keep' => 1));
        $out = $helper->assignValueToKey($payload, 'meta[title]', 'New');
        $this->assertSame(1, $out['meta']['keep']);
        $this->assertSame('New', $out['meta']['title']);
    }

    public function testAssignValueToKeySkipsEmptyBracketSegments()
    {
        $helper = Helper::getInstance();
        $out = $helper->assignValueToKey(array(), 'meta[][]title', 'x');
        $this->assertSame('x', $out['meta']['title']);
    }

    public function testNormalizeErrorTreeSkipsNonStringKeys()
    {
        $helper = Helper::getInstance();
        $tree = $helper->normalizeErrorTree(array('ok' => 'msg', 99 => 'numeric-key'));
        $this->assertSame(array('ok' => 'msg', '99' => 'numeric-key'), $tree);
    }

    public function testGetMappedKeyReturnsListFromArrayMapLeaf()
    {
        $helper = Helper::getInstance();
        $map = array('path' => array('a', 'b'));
        $this->assertSame(array('a', 'b'), $helper->getMappedKey($map, 'path'));
    }

    public function testGetErrorPathsCollectsLeafMessagePaths()
    {
        $helper = Helper::getInstance();
        $paths = $helper->getErrorPaths(
            array('email' => 'email'),
            array('email' => 'Invalid')
        );
        $this->assertSame(array(array()), $paths);

        $nested = $helper->getErrorPaths(
            array('user' => array('email' => 'email')),
            array('user' => array('email' => 'Bad'))
        );
        $this->assertContains(array('user'), $nested);
    }

    public function testGetElementResolvesArrayFormGraph()
    {
        $helper = Helper::getInstance();
        $form = array('meta' => array('title' => 'plain-value'));
        $value = $helper->getElement(array('meta', 'title'), $form);
        $this->assertSame('plain-value', $value);
    }

    public function testGetElementReturnsNullWhenSubFormMissing()
    {
        $helper = Helper::getInstance();
        $parent = new class {
            public function getSubForm($name)
            {
                return null;
            }
        };
        $this->assertNull($helper->getElement(array('meta', 'title'), $parent));
    }

    public function testAssignValueToKeyUsesStringKeyPath()
    {
        $helper = Helper::getInstance();
        $out = $helper->assignValueToKey(array(), 'title', 'Plain');
        $this->assertSame('Plain', $out['title']);
    }

    public function testGetMappedKeyReturnsOriginalKeyWhenMapMisses()
    {
        $helper = Helper::getInstance();
        $this->assertSame('missing', $helper->getMappedKey(array('other' => 'x'), 'missing'));
    }

    public function testGetElementResolvesBracketNotationOnArrayForm()
    {
        $helper = Helper::getInstance();
        $form = array('meta' => array('title' => 'value'));
        $el = $helper->getElement('meta[title]', $form);
        $this->assertSame('value', $el);
    }

    public function testNormalizeErrorTreeFromArrayAssignsScalarLeaves()
    {
        $helper = Helper::getInstance();
        $method = new \ReflectionMethod(Helper::class, 'normalizeErrorTreeFromArray');
        $method->setAccessible(true);
        $tree = $method->invoke($helper, array('field' => 'message'));
        $this->assertSame(array('field' => 'message'), $tree);
    }
}
