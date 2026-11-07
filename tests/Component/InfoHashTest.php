<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\InfoHash;

class InfoHashTest extends ComponentTestCase
{
    public function testAggregateDataAndAddArrayDataAgainstWidget()
    {
        $id = $this->insertWidget(array('name' => 'infohash-gate', 'code' => 'ih-gate'));
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => $id, 'code' => 'ih-gate', 'name' => 'stale'));

        $hash->aggregate_data(
            'SELECT id, name FROM widget WHERE id = ' . (int) $id,
            'id'
        );
        $this->assertSame('infohash-gate', $hash->info[$id]->name);

        $db = Db::instance();
        $db->query('CREATE TABLE IF NOT EXISTS widget_tag (widget_id INT NOT NULL, tag_id INT NOT NULL)');
        $db->query('DELETE FROM widget_tag WHERE widget_id = ' . (int) $id);
        $db->query('INSERT INTO widget_tag (widget_id, tag_id) VALUES (' . (int) $id . ', 9)');

        $hash->add_array_data(
            'SELECT widget_id, tag_id FROM widget_tag WHERE widget_id = ' . (int) $id,
            'widget_id',
            'tags'
        );
        $this->assertCount(1, $hash->info[$id]->tags);
        $this->assertSame(9, (int) $hash->info[$id]->tags[0]->tag_id);
    }

    public function testSetDataHydratesWidgetRowsFromQuery()
    {
        $id = $this->insertWidget(array('name' => 'set-data-gate', 'code' => 'sd-gate'));
        $hash = new InfoHash('id', 'code');
        $hash->set_data(
            'SELECT id, code, name FROM widget WHERE id = ' . (int) $id,
            'stdClass'
        );
        $this->assertArrayHasKey($id, $hash->info);
        $this->assertSame('set-data-gate', $hash->info[$id]->name);
        $this->assertSame('SELECT id, code, name FROM widget WHERE id = ' . (int) $id, $hash->getDataQuery());
    }
}
