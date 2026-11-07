<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Model\Behaviour\DefaultFieldValues;

class WidgetDefaults extends WidgetPlain
{
    public function behaviours()
    {
        return array(
            'defaults' => array(
                'class' => DefaultFieldValues::class,
                'values' => array(
                    'data' => 'from-values',
                ),
                'insertValues' => array(
                    'code' => 'from-insert',
                ),
                'updateValues' => array(
                    'name' => 'from-update',
                ),
                'alwaysValues' => array(
                    'qty' => function ($owner) {
                        return ($owner->name === 'scaled') ? 7 : 40;
                    },
                ),
            ),
        );
    }
}
