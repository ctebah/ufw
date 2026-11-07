<?php
namespace Ufw\Tests\Support;

use Ufw\Component\ApiClient\Base\TraitR;

/**
 * Counts {@see TraitR::_doRead()} calls for cache-hit assertions on {@see TraitR::read()}.
 */
class TraitRReadCacheProbe extends ApiClientTestApi
{
    use TraitR;

    /** @var int */
    public $readCalls = 0;

    protected function _doRead()
    {
        $this->readCalls++;

        return null;
    }

    /**
     * @param mixed $data
     * @return array<string, mixed>
     */
    public function exposeGetFormData($data = null)
    {
        return $this->getFormData($data, array('a' => 'a'));
    }
}
