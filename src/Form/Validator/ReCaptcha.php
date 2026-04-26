<?php
namespace Ufw\Form\Validator;

/** Server-side verification of a Google reCAPTCHA response against the site `secret`. */
class ReCaptcha extends Base
{

    // The secret parameter is missing.
    const MISSING_INPUT_SECRET = 'missing-input-secret';

    // The secret parameter is invalid or malformed.
    const INVALID_INPUT_SECRET = 'invalid-input-secret';

    // The response parameter is missing.
    const MISSING_INPUT_RESPONSE = 'missing-input-response';

    // The response parameter is invalid or malformed.
    const INVALID_INPUT_RESPONSE = 'invalid-input-response';

    // The request is invalid or malformed.
    const BAD_REQUEST = 'bad-request';

    const TIMEOUT_OR_DUPLICATE = 'timeout-or-duplicate';

    /**
     *
     * @var array
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
     *
     * @var string
     */
    protected $secret;

    /**
     *
     * @var string
     */
    protected $siteKey;

    /**
     * Sets validator options
     *
     * @param array $options
     *            Validator options
     */
    public function __construct($secret, $siteKey)
    {
        $this->setSecret($secret)->setSiteKey($siteKey);
    }

    /**
     *
     * Returns true if and only if $value is contained in the haystack option.
     *
     * @param mixed $value
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        if (is_null($value)) {
            return false;
        }
        
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://www.google.com/recaptcha/api/siteverify'
        ]);
        $response = $client->post('', [
            'form_params' => [
                'secret' => $this->getSecret(),
                'response' => $value,
                'remoteip' => @$this->getApplication()
                    ->getRequest()
                    ->getServerParams()['REMOTE_ADDR']
            ]
        ]);
        
        $json = json_decode($response->getBody() . '', true);
        if ($json['success']) {
            return true;
        }
        
        foreach ($json['error-codes'] as $errCode) {
            $this->error($errCode);
        }
        
        return false;
    }

    /**
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     *
     * @return string
     */
    public function getSiteKey()
    {
        return $this->siteKey;
    }

    /**
     *
     * @param string $secret
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     *
     * @param string $siteKey
     */
    public function setSiteKey($siteKey)
    {
        $this->siteKey = $siteKey;
        return $this;
    }
}
