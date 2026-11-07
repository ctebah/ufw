<?php
namespace Ufw\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as PsrResponse;
use PHPUnit\Framework\TestCase;
use Ufw\Form\Validator\ReCaptcha;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\TestHttpRequest;

class FormValidatorReCaptchaTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testIsValidRejectsNullResponse(): void
    {
        ApplicationFixtures::bareWithController();
        $validator = new ReCaptchaStub(new MockHandler(array()), 'secret', 'site');
        $this->assertFalse($validator->isValid(null, array()));
    }

    public function testIsValidAcceptsSuccessfulVerifyPayload(): void
    {
        list($app) = ApplicationFixtures::bareWithController();
        $app->setRequest(TestHttpRequest::withAttributes(array(), 'POST'));
        $handler = new MockHandler(array(
            new PsrResponse(200, array(), '{"success":true}'),
        ));
        $validator = new ReCaptchaStub($handler, 'secret', 'site');
        $this->assertTrue($validator->isValid('token-value', array()));
    }

    public function testIsValidMapsErrorCodesAndRejectsInvalidJson(): void
    {
        ApplicationFixtures::bareWithController();
        $badJson = new ReCaptchaStub(new MockHandler(array(
            new PsrResponse(200, array(), 'not-json'),
        )), 'secret', 'site');
        $this->assertFalse($badJson->isValid('t', array()));

        $errors = new ReCaptchaStub(new MockHandler(array(
            new PsrResponse(200, array(), '{"success":false,"error-codes":["invalid-input-response","timeout-or-duplicate"]}'),
        )), 'secret', 'site');
        $this->assertFalse($errors->isValid('t', array()));
        $this->assertNotEmpty($errors->getMessages());
    }
}

class ReCaptchaStub extends ReCaptcha
{
    /**
     * @var MockHandler
     */
    private $handler;

    public function __construct(MockHandler $handler, $secret, $siteKey)
    {
        parent::__construct($secret, $siteKey);
        $this->handler = $handler;
    }

    protected function createSiteVerifyClient()
    {
        return new Client(array(
            'handler' => HandlerStack::create($this->handler),
            'base_uri' => 'https://www.google.com/recaptcha/api/siteverify',
        ));
    }
}
