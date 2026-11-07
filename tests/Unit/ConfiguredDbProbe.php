<?php
namespace Ufw\Tests\Unit;

class ConfiguredDbProbe extends \Ufw\Db\Db
{
    public function marker()
    {
        return 'probe';
    }
}
