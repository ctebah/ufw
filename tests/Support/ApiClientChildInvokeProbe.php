<?php
namespace Ufw\Tests\Support;

class ApiClientChildInvokeProbe extends ApiClientTestApi
{
    public function getChild()
    {
        return static::getInstance($this->getClient());
    }
}
