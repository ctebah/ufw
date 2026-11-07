<?php
namespace Ufw\Tests\Support;

use Ufw\Component\ApiClient\Base\TraitRE;

class TraitReReadCacheProbe extends ApiClientTestApi
{
    use TraitRE;

    /** @var int */
    public $readCalls = 0;

    protected function _doRead()
    {
        $this->readCalls++;

        return null;
    }
}
