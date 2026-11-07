<?php
namespace Ufw\Tests\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientStubRequester;

class ApiClientClientSetupTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientStubRequester::clearNextResponse();
        parent::tearDown();
    }

    public function testRunRejectsInvalidFormBeforeRequest()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new class(array('action' => '/x')) extends FormAlike {
            public function isValid()
            {
                return false;
            }
        };
        $text = new Text('title');
        $form->addElement($text);
        $api->setForm($form);

        $run = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, '_run');
        $run->setAccessible(true);
        $this->expectException(\Ufw\Component\ApiClient\Exception::class);
        $run->invoke($api, function () {
            return new ApiResponse();
        });
    }

    public function testIsLoggedOnUsesUserObjectWhenPresent()
    {
        ApplicationFixtures::bareWithController(array());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $user = new class {
            public function isLoggedOn()
            {
                return true;
            }
        };
        $prop = new \ReflectionProperty(ApiClientTestApi::class, '_user');
        $prop->setAccessible(true);
        $prop->setValue($api, $user);
        $check = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, 'isLoggedOn');
        $check->setAccessible(true);
        $this->assertTrue($check->invoke($api));
    }
}
