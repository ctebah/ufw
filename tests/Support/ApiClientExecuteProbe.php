<?php
namespace Ufw\Tests\Support;

use Ufw\Component\ApiClient\Response as ApiResponse;

class ApiClientExecuteProbe extends \Ufw\Component\ApiClient\Base\Execute
{
    /** @var ApiResponse|null */
    private $stubResponse;

    public static function resetInstances()
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Base::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
    }

    public function setStubResponse(ApiResponse $response)
    {
        $this->stubResponse = $response;

        return $this;
    }

    public function getStubResponse()
    {
        return $this->stubResponse;
    }

    protected function _do()
    {
        $this->setResponse($this->stubResponse);
    }
}
