<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Model\Behaviour\InsertId;
use Ufw\Model\Behaviour\ReadAfterSave;

class WidgetReread extends WidgetPlain
{
    public function behaviours()
    {
        return array(
            'pk' => array(
                'class' => InsertId::class,
            ),
            'reread' => array(
                'class' => ReadAfterSave::class,
            ),
        );
    }
}
