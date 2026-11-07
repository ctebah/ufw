<?php
namespace Ufw\Tests\Support;

class ApiClientStubRequester extends ApiClientRequesterProbe
{
    /**
     * @var \Ufw\Component\ApiClient\Response|null
     */
    private static $nextResponse = null;

    public static function setNextResponse(\Ufw\Component\ApiClient\Response $response)
    {
        self::$nextResponse = $response;
    }

    public static function clearNextResponse()
    {
        self::$nextResponse = null;
    }

    protected function doApiRequest($method, $url, $body, $headers)
    {
        if (self::$nextResponse !== null) {
            $response = self::$nextResponse;
            self::$nextResponse = null;
            $this->setLastResponse($response);

            return $response;
        }

        $this->createResponse(new \GuzzleHttp\Psr7\Response(200, array('Content-Type' => 'application/json'), '{}'));

        return $this->getLastResponse();
    }
}
