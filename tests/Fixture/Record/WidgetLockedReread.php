<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Model\Behaviour\ReadAfterSave;

class WidgetLockedReread extends WidgetLocked
{
    public function behaviours()
    {
        $behaviours = parent::behaviours();
        $behaviours['reread'] = array(
            'class' => ReadAfterSave::class,
        );
        return $behaviours;
    }
}
