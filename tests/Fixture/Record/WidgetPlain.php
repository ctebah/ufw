<?php
namespace Ufw\Tests\Fixture\Record;

use Ufw\Model\Record\Simple;

class WidgetPlain extends Simple
{
    protected static $tableName = 'widget';

    protected static $primaryKeyField = 'id';
}
