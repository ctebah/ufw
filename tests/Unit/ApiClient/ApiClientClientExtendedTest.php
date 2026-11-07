<?php
namespace Ufw\Tests\Unit\ApiClient;

use Ufw\Tests\Support\TestAppPaths;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Helper;
use Ufw\Component\ApiClient\Response as ApiResponse;
use Ufw\Form\Element\Text;
use Ufw\Component\ApiClient\FormAlike;
use Ufw\Paths;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClientTestApi;
use Ufw\Tests\Support\ApiClientStubRequester;
use Ufw\Tests\Support\ApiClientRequesterProbe;

class ApiClientClientExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array(
            'dispatch' => array(
                'routes' => array(),
                'controllers' => array(),
                'module' => array(),
                'controller_aliases' => array(),
            ),
        ));
        Paths::setApplicationPath(TestAppPaths::app());
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientTestApi::resetInstances();
        ApiClientRequesterProbe::resetInstance();
        ApiClientStubRequester::clearNextResponse();
        parent::tearDown();
    }

    public function testClientRequesterResolutionAndMagicGet()
    {
        $requester = ApiClientStubRequester::getInstance();
        $client = Client::getInstance();
        $client->setRequester($requester);
        $this->assertSame($requester, $client->getRequester());
        $this->assertTrue($client->getRaiseExceptionOnApiError());
        $response = $client->get('/probe');
        $this->assertInstanceOf(ApiResponse::class, $response);
    }

    public function testMagicTraitGetApplicationAndHelperProperty()
    {
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $this->assertSame(
            \Ufw\Registry::getInstance()->get('application'),
            $api->exposeGetApplication()
        );
        $this->assertInstanceOf(Helper::class, $api->helper);
    }

    public function testRunSuccessAndSetFormErrors()
    {
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new FormAlike(array('action' => '/noop'));
        $form->addElement(new Text('title'));
        $api->setForm($form)->setFieldsMap(array('title' => 'title'));

        $ok = new ApiResponse();
        $ok->setResponse(new \GuzzleHttp\Psr7\Response(200, array(), '{}'));
        $api->exposeRun(function () use ($ok) {
            return $ok;
        });
        $this->assertSame($ok, $api->getResponse());

        $api->exposeSetFormErrors(array('title' => array('Required')));
        $this->assertNotEmpty($form->getElement('title')->getErrors());
    }

    public function testHelperNormalizeErrorTreeAndStringKeyedArray()
    {
        $helper = Helper::getInstance();
        $this->assertSame(array('a' => 1), $helper->normalizeStringKeyedArray(array('a' => 1, 0 => 'x')));
        $tree = $helper->normalizeErrorTree(array('field' => array('msg')));
        $this->assertNotEmpty($helper->getErrorPaths(array('field' => 'field'), $tree));
        $this->assertSame('title', $helper->getMappedKey(array('field' => 'title'), 'field'));
    }

    public function testHelperPayloadBracketPathsNestedErrorsAndFormArray()
    {
        $helper = Helper::getInstance();
        $api = ApiClientTestApi::getInstance(Client::getInstance());
        $form = new FormAlike(array('action' => '/save'));
        $form->addElement(new Text('title'));
        $form->getElement('title')->setValue('Hello');

        $nested = $helper->assignValueToKey(array(), 'meta[title]', 'Hello');
        $this->assertSame(array('meta' => array('title' => 'Hello')), $nested);

        $deep = $helper->assignValueToKey(array('meta' => array('x' => 1)), array('meta', 'title'), 'Hi');
        $this->assertSame('Hi', $deep['meta']['title']);

        $payload = $helper->createPayload($api, array('meta[title]'), $form, array('meta[title]' => 'title'));
        $this->assertSame('Hello', $payload['meta']['title']);

        $paths = $helper->getErrorPaths(
            array('user' => 'user'),
            array('user' => array('name' => array('first' => 'Required')))
        );
        $this->assertNotEmpty($paths);

        $arrayForm = array('title' => 'from-array');
        $el = $helper->getElement('title', $arrayForm);
        $this->assertSame('from-array', $el);
    }

    public function testApiClientRaisesOnErrorResponseFromRequester()
    {
        ApplicationFixtures::bareWithController(array('dispatch' => array('routes' => array())));
        ApiClientStubRequester::clearNextResponse();
        $bad = new ApiResponse();
        $bad->setResponse(new \GuzzleHttp\Psr7\Response(400, array(), '{"error":"bad"}'));
        $bad->setException(new \RuntimeException('api fail'));
        ApiClientStubRequester::setNextResponse($bad);

        $client = Client::getInstance();
        $client->setRequester(ApiClientStubRequester::getInstance());
        $api = ApiClientTestApi::getInstance($client);

        $this->expectException(\Ufw\Component\ApiClient\Exception::class);
        $api->get('/fail');
    }

    public function testRequesterGetApplicationAndLogger()
    {
        $requester = ApiClientRequesterProbe::getInstance();
        $this->assertSame(
            \Ufw\Registry::getInstance()->get('application'),
            $requester->exposeGetApplication()
        );
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $requester->exposeGetLogger());
    }

    public function testRequesterLoggerUsesFallbackWhenClassPathNormalizationFails()
    {
        ApiClientRequesterProbe::resetInstance();
        $requester = RequesterPregReplaceNullProbe::getInstance();
        $requester->failNextClassPathReplace = true;
        $this->assertInstanceOf(\Monolog\Logger::class, $requester->exposeGetLogger());
    }
}

class RequesterPregReplaceNullProbe extends ApiClientRequesterProbe
{
    /** @var bool */
    public $failNextClassPathReplace = false;

    protected function pregReplaceClassPathSlashes($className)
    {
        if ($this->failNextClassPathReplace) {
            $this->failNextClassPathReplace = false;

            return null;
        }

        return parent::pregReplaceClassPathSlashes($className);
    }
}
