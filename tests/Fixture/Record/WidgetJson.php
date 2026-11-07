<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Model\Behaviour\InsertId;
use Ufw\Model\Behaviour\Json;

class WidgetJson extends WidgetPlain
{
    public function behaviours()
    {
        return array(
            'pk' => array(
                'class' => InsertId::class,
            ),
            'json' => array(
                'class' => Json::class,
                'serialAttributes' => array('data'),
            ),
        );
    }
}
