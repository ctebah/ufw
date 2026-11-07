<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Tests\Fixture\Record\WidgetPlain;

/**
 * Locks saveSimpleRelation pivot sync when DB holds ints and form sends string IDs (Woodward/JEP pattern).
 */
class SaveSimpleRelationIdMixTest extends ComponentTestCase
{
    public function testSaveSimpleRelationSyncsStringIdsAgainstIntPivotRows()
    {
        $db = Db::instance();
        $db->query('CREATE TABLE IF NOT EXISTS widget_tag (widget_id INT NOT NULL, tag_id INT NOT NULL)');

        $row = new class(array('name' => 'mix', 'code' => 'mix-rel')) extends WidgetPlain {
            public function syncTags($values)
            {
                return $this->saveSimpleRelation('widget_tag', 'widget_id', 'tag_id', $values);
            }
        };
        $this->assertNotFalse($row->save());
        $row->id = (int) $db->insert_id;

        $db->query('INSERT INTO widget_tag (widget_id, tag_id) VALUES (' . (int) $row->id . ', 1), (' . (int) $row->id . ', 2)');

        $this->assertNotFalse($row->syncTags(array('1', '3')));

        $left = $db->query(
            'SELECT tag_id FROM widget_tag WHERE widget_id = ' . (int) $row->id . ' ORDER BY tag_id'
        );
        $ids = array();
        while ($r = $db->fetch_array($left)) {
            $ids[] = (int) $r['tag_id'];
        }
        $db->free($left);

        $this->assertSame(array(1, 3), $ids);

        $db->query('DROP TABLE widget_tag');
    }
}
