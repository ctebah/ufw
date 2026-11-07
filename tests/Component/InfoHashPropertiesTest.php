<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\InfoHash;

class InfoHashPropertiesTest extends ComponentTestCase
{
    public function testAggregateDataNoOpWhenQueryFails()
    {
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => 1, 'code' => 'a', 'name' => 'A'));
        $hash->aggregate_data('SELECT no_such_column FROM widget', 'id');
        $this->assertSame('A', $hash->info[1]->name);
    }

    public function testAddArrayDataWithCollectPropertyAndIndexedProperty()
    {
        $id = $this->insertWidget(array('name' => 'arr-deep', 'code' => 'ad-' . bin2hex(random_bytes(2))));
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => $id, 'code' => 'ad-key', 'name' => 'arr-deep'));

        $db = Db::instance();
        $db->query('CREATE TABLE IF NOT EXISTS widget_tag_collect (widget_id INT NOT NULL, tag_id INT NOT NULL, tag_name VARCHAR(32))');
        $db->query('DELETE FROM widget_tag_collect WHERE widget_id = ' . (int) $id);
        $db->query("INSERT INTO widget_tag_collect (widget_id, tag_id, tag_name) VALUES (" . (int) $id . ", 1, 'alpha'), (" . (int) $id . ", 2, 'beta')");

        $collected = $hash->add_array_data(
            'SELECT widget_id, tag_id, tag_name FROM widget_tag_collect WHERE widget_id = ' . (int) $id,
            'widget_id',
            'tags',
            false,
            'stdClass',
            'tag_name',
            'tag_name',
            'tag_id'
        );
        $this->assertSame(array('alpha', 'beta'), $collected);
        $this->assertSame('alpha', $hash->info[$id]->tags[1]);
        $this->assertSame('beta', $hash->info[$id]->tags[2]);
    }

    public function testSetArrayDataAndGetTotalCountWithoutLimit()
    {
        $id = $this->insertWidget(array('name' => 'total-one', 'code' => 'tot-' . bin2hex(random_bytes(2))));
        $hash = new InfoHash('id', 'code');
        $hash->set_data('SELECT id, code, name FROM widget WHERE id = ' . (int) $id);
        $this->assertSame(1, $hash->getTotalCount());

        $extra = (object) array('id' => $id, 'code' => 'tag-x', 'label' => 'L');
        $hash->set_array_data(array($extra), 'id', 'extras');
        $this->assertCount(1, $hash->info[$id]->extras);
    }

    public function testGetTotalCountWithLimitQueriesFullTableSize()
    {
        $this->insertWidget(array('name' => 't1', 'code' => 't1-' . bin2hex(random_bytes(2))));
        $this->insertWidget(array('name' => 't2', 'code' => 't2-' . bin2hex(random_bytes(2))));
        $this->insertWidget(array('name' => 't3', 'code' => 't3-' . bin2hex(random_bytes(2))));
        $hash = new InfoHash('id', 'code');
        $hash->set_data('SELECT id, code, name FROM widget ORDER BY id LIMIT 2');
        $this->assertCount(2, $hash->info);
        $this->assertSame(3, $hash->getTotalCount());
    }

    public function testAddArrayDataCallableCollectAppendsComputedValues()
    {
        $id = $this->insertWidget(array('name' => 'callable', 'code' => 'cb-' . bin2hex(random_bytes(2))));
        $hash = new InfoHash('id', 'code');
        $hash->add_entry_array(array('id' => $id, 'code' => 'cb-key', 'name' => 'callable'));
        $db = Db::instance();
        $db->query('CREATE TABLE IF NOT EXISTS widget_tag_collect (widget_id INT NOT NULL, tag_id INT NOT NULL, tag_name VARCHAR(32))');
        $db->query('DELETE FROM widget_tag_collect WHERE widget_id = ' . (int) $id);
        $db->query("INSERT INTO widget_tag_collect (widget_id, tag_id, tag_name) VALUES (" . (int) $id . ", 1, 'one')");

        $collected = $hash->add_array_data(
            'SELECT widget_id, tag_id, tag_name FROM widget_tag_collect WHERE widget_id = ' . (int) $id,
            'widget_id',
            'labels',
            false,
            'stdClass',
            function ($parent, $row) {
                return $parent->name . ':' . $row->tag_name;
            }
        );
        $this->assertSame(array('callable:one'), $collected);
    }

    public function testAddEntryDeprecatedWrapperDelegatesToAddEntry()
    {
        $hash = new InfoHash('id', 'name');
        $row = (object) array('id' => 9, 'name' => 'framework-entry', 'code' => 'le');
        $hash->add_entry($row);
        $this->assertSame('framework-entry', $hash->info[9]->name);
    }

    public function testGetTotalCountWithGroupByAndLimitUsesSubqueryCount()
    {
        $this->insertWidget(array('name' => 'g1', 'code' => 'g1-' . bin2hex(random_bytes(2)), 'qty' => 1));
        $this->insertWidget(array('name' => 'g2', 'code' => 'g2-' . bin2hex(random_bytes(2)), 'qty' => 1));
        $hash = new InfoHash('id', 'code');
        $hash->set_data('SELECT id, code, name FROM widget GROUP BY id, code, name ORDER BY id LIMIT 1');
        $this->assertSame(2, $hash->getTotalCount());
    }

    public function testAsXmlInfoOnlyAndCustomObjectMode()
    {
        $hash = new InfoHash('id', 'name');
        $hash->add_entry_array(array('id' => 1, 'name' => 'solo', 'code' => 's'));
        $infoXml = $hash->asXML('items', 'info_only');
        $this->assertIsString($infoXml);
        $this->assertStringContainsString('solo', $infoXml);

        $custom = $hash->asXML('wrap', (object) array('k' => 'v'));
        $this->assertStringContainsString('v', (string) $custom);
    }

    public function testAsXmlAutoModeAndTweakSelectedSkipsNonScalar()
    {
        $hash = new InfoHash('id', 'name');
        $hash->add_entry_array(array('id' => 3, 'name' => 'gamma', 'code' => 'g'));
        $xml = $hash->asXML('widget');
        $this->assertIsString($xml);
        $this->assertStringContainsString('gamma', $xml);

        $options = $hash->dump_opt_list('name');
        $this->assertSame($options, $hash->tweak_selected($options, false));
        $this->assertSame($options, $hash->tweak_selected($options, array('x')));
    }
}
