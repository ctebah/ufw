<?php
namespace Ufw\Tests\Component;

use Ufw\Db\Db;
use Ufw\Tests\Fixture\Record\WidgetJson;

class JsonBehaviourTest extends ComponentTestCase
{
    public function testArrayRoundTripsAsUnicodeJsonAndStaysAnArrayInMemory()
    {
        $payload = array('ime' => 'Čačak', 'n' => 1);
        $row = new WidgetJson(array(
            'name' => 'alpha',
            'code' => 'json-round',
            'data' => $payload,
        ));
        $this->assertNotFalse($row->save());
        $this->assertSame($payload, $row->data);

        $stored = $this->rawWidget($row->id);
        $this->assertStringContainsString('Čačak', $stored['data']);
        $this->assertStringNotContainsString('\\u010c', $stored['data']);

        $fresh = $this->fetchWidget($row->id, WidgetJson::class);
        $this->assertSame($payload, $fresh->data);
    }

    public function testEmptyArrayIsStoredAsJsonArray()
    {
        $row = new WidgetJson(array(
            'name' => 'alpha',
            'code' => 'json-empty',
            'data' => array(),
        ));
        $this->assertNotFalse($row->save());
        $this->assertSame(array(), $row->data);
        $this->assertSame('[]', $this->rawWidget($row->id)['data']);
    }

    public function testAJsonStringIsDecodedAfterSave()
    {
        $row = new WidgetJson(array(
            'name' => 'alpha',
            'code' => 'json-string',
            'data' => '{"a":1}',
        ));
        $this->assertNotFalse($row->save());
        $this->assertSame(array('a' => 1), $row->data);
        $fresh = $this->fetchWidget($row->id, WidgetJson::class);
        $this->assertSame(array('a' => 1), $fresh->data);
    }

    public function testAScalarThatIsNotJsonBecomesNull()
    {
        $row = new WidgetJson(array(
            'name' => 'alpha',
            'code' => 'json-bad',
            'data' => 'not-json',
        ));
        $this->assertNotFalse($row->save());
        $this->assertNull($row->data);
        $this->assertNull($this->rawWidget($row->id)['data']);
    }

    public function testJsonLiteralFalseIsReadAsAnEmptyArray()
    {
        $db = Db::instance();
        $this->assertNotFalse($db->simple_insert('widget', array(
            'name' => 'alpha',
            'code' => 'json-false',
            'data' => 'false',
        )));
        $fresh = $this->fetchWidget($db->insert_id, WidgetJson::class);
        $this->assertSame(array(), $fresh->data);
    }
}
