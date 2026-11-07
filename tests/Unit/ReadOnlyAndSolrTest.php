<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\InfoHash;
use Ufw\Model\Base as ModelBase;
use Ufw\Model\Record\ReadOnlyRecord;

class ReadOnlyAndSolrTest extends TestCase
{
    public function testReadOnlyRecordBlocksPersistence()
    {
        $row = new class(array('id' => 1)) extends ReadOnlyRecord {
            protected static $tableName = 'widget';

            protected static $primaryKeyField = 'id';
        };

        $this->expectException(\Exception::class);
        $row->save();
    }

    public function testModelBaseExposesRecordClassName()
    {
        $model = new class extends ModelBase {
            protected $recordClassName = '\\Ufw\\Model\\Record\\Simple';
        };
        $this->assertSame('\\Ufw\\Model\\Record\\Simple', $model->getRecordClassName());
    }

    public function testInfoHashMagicGettersAndXmlExport()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 5, 'code' => 'c', 'name' => 'N'));
        $hash->make_id_arr();
        $this->assertSame(array(5), $hash->idArr);

        $hash->set_array_data(
            array((object) array('id' => 5, 'tag' => 'x')),
            'id',
            'tags'
        );
        $this->assertCount(1, $hash->info[5]->tags);

        $xml = $hash->asXML('items', 'info_only');
        $this->assertStringContainsString('<items>', $xml);
        $this->assertStringContainsString('N', $xml);
    }
}
