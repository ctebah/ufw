<?php
namespace Ufw\Tests\Unit\ApiClient;

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Exception as ApiException;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Form\Element\Text;
use Ufw\Request;
use Ufw\ServerRequest;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;

require_once dirname(__DIR__, 2) . '/Fixture/route-controllers.php';

/**
 * Actionable framework gap clusters beyond the six priority files (Form, ApiClient, Request, Controller, …).
 */
class ApiClientActionableBulkTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        $_SESSION = array();
        parent::tearDown();
    }

    public function testApiRunMaps422ClientErrorsOntoFormWithoutFlash()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new FormAlike(array('action' => '/save'));
        $form->addElement(new Text('title'));
        $api->setForm($form)->setFieldsMap(array('title' => 'title'));

        $psr = new PsrRequest('POST', '/save');
        $http = new \GuzzleHttp\Psr7\Response(422, array('Content-Type' => 'application/json'), '{"errors":{"title":["Bad"]}}');
        $clientEx = new ClientException('Unprocessable', $psr, $http);
        $response = new ApiResponse();
        $response->setException($clientEx);

        $ex = new ApiException('Validation failed', 422);
        $ex->setResponse($response);

        $run = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, '_run');
        $run->setAccessible(true);
        try {
            $run->invoke($api, function () use ($ex) {
                throw $ex;
            });
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->getCode());
        }
        $this->assertNotEmpty($form->getElement('title')->getErrors());
    }

    public function testNormalizeFieldErrorMessagesFiltersNonStrings()
    {
        ApplicationFixtures::bareWithController(array());
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $method = new \ReflectionMethod(\Ufw\Component\ApiClient\Base::class, 'normalizeFieldErrorMessages');
        $method->setAccessible(true);
        $this->assertSame(array('a'), $method->invoke($api, array('a', '', 9, array('x'))));
        $this->assertSame(array(), $method->invoke($api, new \stdClass()));
    }

    public function testFormElementResolvesValidatorFromArrayDefinition()
    {
        ApplicationFixtures::bareWithController(array());
        $el = new Text('email');
        $el->addValidator(array('name' => 'email', 'options' => array()));
        $validator = $el->getValidator('email');
        $this->assertInstanceOf(\Ufw\Form\Validator\Email::class, $validator);
    }

    public function testRequestRequireServerRequestThrowsWhenMissing()
    {
        $req = new Request(array(), array(), array(), null, new ServerRequest(
            array(),
            array(),
            '/',
            'GET',
            'php://input'
        ));
        $prop = new \ReflectionProperty(Request::class, 'serverRequest');
        $prop->setAccessible(true);
        $prop->setValue($req, null);
        $this->expectException(\RuntimeException::class);
        $req->getQueryParams();
    }

    public function testControllerGetIdentSlugifiesClassName()
    {
        list($app,) = ApplicationFixtures::bareWithController(array(
            'dispatch' => array('controllers' => array('ping')),
        ));
        $controller = new \Controller\Ping($app);
        $this->assertSame('ping', $controller->getIdent());
    }
}
