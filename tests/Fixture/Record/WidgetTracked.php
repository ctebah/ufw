<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Db\Expression;
use Ufw\Model\Behaviour\ChangedOnly;
use Ufw\Model\Behaviour\DefaultFieldValues;
use Ufw\Model\Behaviour\InsertId;
use Ufw\Model\Behaviour\Json;

class WidgetTracked extends WidgetPlain
{
    public function behaviours()
    {
        return array(
            'pk' => array(
                'class' => InsertId::class,
            ),
            'defaults' => array(
                'class' => DefaultFieldValues::class,
                'alwaysValues' => array(
                    'ts_updated' => function () {
                        return new Expression('NOW()');
                    },
                ),
            ),
            'json' => array(
                'class' => Json::class,
                'serialAttributes' => array('data'),
            ),
            'changed' => array(
                'class' => ChangedOnly::class,
            ),
        );
    }
}
