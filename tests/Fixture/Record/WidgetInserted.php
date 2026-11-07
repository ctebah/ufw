<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Model\Behaviour\InsertId;

class WidgetInserted extends WidgetPlain
{
    public function behaviours()
    {
        return array(
            'pk' => array(
                'class' => InsertId::class,
            ),
        );
    }
}
