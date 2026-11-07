<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Model\Behaviour\OptimisticLock;

class WidgetLocked extends WidgetTracked
{
    public function behaviours()
    {
        $behaviours = parent::behaviours();
        $behaviours['lock'] = array(
            'class' => OptimisticLock::class,
        );
        return $behaviours;
    }
}
