<?php
namespace Ufw\Form\Validator;

/** Server-side verification of a Google reCAPTCHA response against the site `secret`. */
class ReCaptcha extends Base
{

    const MISSING_INPUT_SECRET = 'missing-input-secret';

    const INVALID_INPUT_SECRET = 'invalid-input-secret';

    const MISSING_INPUT_RESPONSE = 'missing-input-response';

    const INVALID_INPUT_RESPONSE = 'invalid-input-response';

    const BAD_REQUEST = 'bad-request';

    const TIMEOUT_OR_DUPLICATE = 'timeout-or-duplicate';

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::MISSING_INPUT_SECRET => "The secret parameter is missing.",
        self::INVALID_INPUT_SECRET => "The secret parameter is invalid or malformed.",
        self::MISSING_INPUT_RESPONSE => "The response parameter is missing.",
        self::INVALID_INPUT_RESPONSE => "The response parameter is invalid or malformed.",
        self::BAD_REQUEST => "The request is invalid or malformed.",
        self::TIMEOUT_OR_DUPLICATE => "Duplicate ReCaptcha response."
    );

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var string
     */
    protected $siteKey;

    /**
     * @param string $secret
     * @param string $siteKey
     */
    public function __construct($secret, $siteKey)
    {
        $this->setSecret($secret)->setSiteKey($siteKey);
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        if ($value === null) {
            return false;
        }

        $remoteip = null;
        $request = $this->getApplication()->getRequest();
        if ($request !== null) {
            $params = $request->getServerParams();
            $remoteip = $params['REMOTE_ADDR'] ?? null;
        }

        $client = $this->createSiteVerifyClient();
        $response = $client->post('', [
            'form_params' => [
                'secret' => $this->getSecret(),
                'response' => $value,
                'remoteip' => $remoteip
            ]
        ]);

        $json = json_decode($response->getBody() . '', true);
        if (!is_array($json)) {
            return false;
        }
        if (!empty($json['success'])) {
            return true;
        }

        if (!empty($json['error-codes']) && is_array($json['error-codes'])) {
            foreach ($json['error-codes'] as $errCode) {
                if (is_string($errCode)) {
                    $this->error($errCode);
                }
            }
        }

        return false;
    }

    /**
     * @return \GuzzleHttp\Client
     */
    protected function createSiteVerifyClient()
    {
        return new \GuzzleHttp\Client([
            'base_uri' => 'https://www.google.com/recaptcha/api/siteverify',
        ]);
    }

    /**
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @return string
     */
    public function getSiteKey()
    {
        return $this->siteKey;
    }

    /**
     * @param string $secret
     * @return $this
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @param string $siteKey
     * @return $this
     */
    public function setSiteKey($siteKey)
    {
        $this->siteKey = $siteKey;
        return $this;
    }
}
