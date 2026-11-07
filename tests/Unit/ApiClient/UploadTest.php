<?php
namespace Ufw\Tests\Unit\ApiClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Client;
use Ufw\Component\ApiClient\Helper;
use Ufw\Form\Element\File;
use Ufw\Form\Validator\FileUpload;
use Ufw\HttpHeaderSanitizer;
use Ufw\JsOut;
use Ufw\ServerRequestFactory;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\ApiClient\Client as SupportApiClient;
use Ufw\Tests\Support\FormFixtureConfig;
use Ufw\Tests\Support\ApiClientRequesterProbe;
use Ufw\Utils\Asset\Js;

class UploadTest extends TestCase
{
    use FormFixtureConfig;

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        ApiClientRequesterProbe::resetInstance();
        SupportApiClient::resetForTests();
        parent::tearDown();
    }

    public function testControllerMagicGetInvokesGetterOrFails(): void
    {
        list(, $controller) = ApplicationFixtures::bareWithController();
        $this->expectException(\Exception::class);
        $controller->notRegisteredLazyProperty;
    }

    public function testApiClientResolveRequesterInstantiatesSiblingRequesterClass(): void
    {
        ApplicationFixtures::bareWithController();
        SupportApiClient::resetForTests();
        \Ufw\Tests\Support\ApiClient\Requester::resetForTests();
        $client = SupportApiClient::getInstance();
        $resolve = new \ReflectionMethod(Client::class, 'resolveRequester');
        $resolve->setAccessible(true);
        $requester = $resolve->invoke($client);
        $this->assertInstanceOf(\Ufw\Tests\Support\ApiClient\Requester::class, $requester);
    }

    public function testApiClientHelperSkipsNonStringErrorTreeKeys(): void
    {
        $helper = Helper::getInstance();
        $method = new \ReflectionMethod(Helper::class, 'normalizeErrorTreeFromArray');
        $method->setAccessible(true);
        $tree = $method->invoke($helper, array(0 => 'numeric', 'title' => 'keep'));
        $this->assertSame(array('0' => 'numeric', 'title' => 'keep'), $tree);
        $keyMethod = new \ReflectionMethod(Helper::class, 'errorTreeKeyToString');
        $keyMethod->setAccessible(true);
        $this->assertNull($keyMethod->invoke($helper, new \stdClass()));
    }

    public function testRequesterMapsBareRequestExceptionThroughDoApiRequest(): void
    {
        ApplicationFixtures::bareWithController();
        $requester = ApiClientRequesterProbe::getInstance();
        $requester->disableDebugLog();
        $requester->injectHttpClient(new GuzzleClient(array(
            'handler' => HandlerStack::create(new MockHandler(array(
                new RequestException('offline', new PsrRequest('GET', 'http://api.test/offline')),
            ))),
            'base_uri' => 'http://api.test',
        )));
        $doApi = new \ReflectionMethod(\Ufw\Component\ApiClient\Requester::class, 'doApiRequest');
        $doApi->setAccessible(true);
        $response = $doApi->invoke($requester, 'get', '/offline', null, array());
        $this->assertTrue($response->hasException());
    }

    public function testJsGetCompiledReturnsEmptyWhenAssetDataUnset(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $js = new Js();
        $js->setApplication($app);
        $compiled = new \ReflectionMethod(Js::class, 'getCompiled');
        $compiled->setAccessible(true);
        $this->assertSame('', $compiled->invoke($js));
    }

    public function testJsSkipsNonStringNonArrayCdnEntries(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $js = new Js();
        $js->setApplication($app)->setPosition(Js::POS_HEAD)->init(array(
            'cdn' => array(99, array('src' => '/ok.js', 'position' => Js::POS_HEAD)),
        ));
        $html = (string) $js;
        $this->assertStringContainsString('/ok.js', $html);
        $this->assertStringNotContainsString('99', $html);
    }

    public function testJsResourceStructDefaultsNonScalarPosition(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $js = new Js();
        $js->setApplication($app)->setPosition(Js::POS_HEAD);
        $struct = new \ReflectionMethod(Js::class, 'getResourceStruct');
        $struct->setAccessible(true);
        $parsed = $struct->invoke($js, array(
            'src' => '/x.js',
            'position' => new \stdClass(),
        ));
        $this->assertSame(Js::POS_BODY_BOTTOM, $parsed['position']);
    }

    public function testFileUploadAcceptsMatchingExtension(): void
    {
        ApplicationFixtures::bareWithController(self::formFixtureAppConfig());
        $field = new File('doc');
        $field->setAttrib('accept', '.pdf, .png');
        $validator = new FileUpload($field);
        $upload = new UploadedFile('php://temp', 10, UPLOAD_ERR_OK, 'paper.PDF', 'application/pdf');
        $this->assertTrue($validator->isValid($upload));
    }

    public function testJsOutSanitizeLeavesReadyEmptyWhenConcatenating(): void
    {
        ApplicationFixtures::bareWithController();
        $jsOut = JsOut::getInstance();
        $ref = new \ReflectionClass(JsOut::class);
        $content = $ref->getProperty('jsContent');
        $content->setAccessible(true);
        $content->setValue($jsOut, array('ready' => '', 'ext' => 'ext();', 'inline' => ''));
        $doConcat = $ref->getProperty('doConcatenate');
        $doConcat->setAccessible(true);
        $doConcat->setValue($jsOut, true);
        $sanitize = $ref->getMethod('_sanitize');
        $sanitize->setAccessible(true);
        $sanitize->invoke($jsOut);
        $merged = $content->getValue($jsOut);
        $this->assertArrayNotHasKey('ready', $merged);
        $this->assertStringContainsString('ext();', $merged['ext']);
    }

    public function testServerRequestFactoryImplodesArrayCookieHeaderValues(): void
    {
        $method = new \ReflectionMethod(ServerRequestFactory::class, 'cookiesFromHeaders');
        $method->setAccessible(true);
        $cookies = $method->invoke(null, null, HttpHeaderSanitizer::sanitizeHeadersArray(array(
            'cookie' => array('a=1', 'b=2'),
        )));
        $this->assertSame('1', $cookies['a'] ?? null);
        $this->assertSame('2', $cookies['b'] ?? null);
    }
}
