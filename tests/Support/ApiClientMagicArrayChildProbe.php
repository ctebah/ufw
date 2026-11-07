<?php
namespace Ufw\Tests\Support;

class ApiClientMagicArrayChildProbe extends ApiClientTestApi
{
    public function getItems()
    {
        return array(static::getInstance($this->getClient()));
    }
}
