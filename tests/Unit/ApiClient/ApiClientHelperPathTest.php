<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Helper;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Component\ApiClient\Client;

class ApiClientHelperPathTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        parent::tearDown();
    }

    public function testAssignValueToKeyParsesBracketNotationAndArrayKeys()
    {
        $helper = Helper::getInstance();
        $payload = $helper->assignValueToKey(array(), array('meta', 'title'), 'T');
        $this->assertSame('T', $payload['meta']['title']);

        $deep = $helper->assignValueToKey(array(), array('a', 'b', 'c'), 'leaf');
        $this->assertSame('leaf', $deep['a']['b']['c']);

        $nested = $helper->assignValueToKey(array(), array('', 'a', ''), 'v');
        $this->assertSame('v', $nested['a']);

        $emptyPath = $helper->assignValueToKey(array('keep' => 1), array(), 'ignored');
        $this->assertSame(1, $emptyPath['keep']);
    }

    public function testGetMappedKeyReturnsFallbackWhenMapBranchMissing()
    {
        $helper = Helper::getInstance();
        $map = array('user' => array('name' => 'profileName'));
        $this->assertSame(array('user', 'missing'), $helper->getMappedKey($map, array('user', 'missing')));

        $listMap = array('items' => array(array('a', 'b')));
        $this->assertSame('items', $helper->getMappedKey($listMap, 'items'));
    }

    public function testGetErrorPathsCollectsNestedFieldErrors()
    {
        $helper = Helper::getInstance();
        $paths = $helper->getErrorPaths(
            array('name' => 'name'),
            array('name' => 'Required')
        );
        $this->assertSame(array(array()), $paths);

        $nested = $helper->getErrorPaths(
            array('meta' => 'meta'),
            array('meta' => array('title' => 'Too short'))
        );
        $this->assertNotEmpty($nested);
    }

    public function testGetElementReadsNestedArrayFormAndMissingSubForm()
    {
        $helper = Helper::getInstance();
        $form = array(
            'meta' => array(
                'title' => 'from-array',
            ),
        );
        $this->assertSame('from-array', $helper->getElement(array('meta', 'title'), $form));
        $this->assertNull($helper->getElement(array('meta', 'missing'), $form));

        ApplicationFixtures::bareWithController(array());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $payload = $helper->createPayload($api, array('title'), array('title' => 'plain'));
        $this->assertSame('plain', $payload['title']);
    }

    public function testGetElementValueFromFormElement()
    {
        $helper = Helper::getInstance();
        $form = new class(array('action' => '/x')) extends \Ufw\Form\Base {
            protected function init()
            {
                $this->addElement(new Text('code'));
            }
        };
        $form->populate(array('code' => 'ABC'));
        $value = new \ReflectionMethod(Helper::class, '_getElementValue');
        $value->setAccessible(true);
        $this->assertSame('ABC', $value->invoke($helper, 'code', $form));
    }
}
