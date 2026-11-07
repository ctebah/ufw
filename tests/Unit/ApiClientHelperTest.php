<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Component\ApiClient\Helper;
use Ufw\Tests\Support\RuntimeProbe;

/**
 * Regression vs UFW 1.0 {@see Helper::assignValueToKey()} reference-by-path semantics (JEP/RJT Sync/Watson populate).
 */
class ApiClientHelperTest extends TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    protected function setUp(): void
    {
        $this->helper = Helper::getInstance();
    }

    protected function tearDown(): void
    {
        ApiClientPayloadProbe::resetInstances();
        ApiClientReadProbe::resetInstances();
        parent::tearDown();
    }

    public function testAssignValueToKeyBracketNotationBuildsNestedPayload()
    {
        $payload = array();
        $payload = $this->helper->assignValueToKey($payload, 'data[nested][field]', 'value');

        $this->assertSame(
            array(
                'data' => array(
                    'nested' => array(
                        'field' => 'value',
                    ),
                ),
            ),
            $payload
        );
    }

    public function testAssignValueToKeyMergesSiblingPathsUnderSameParent()
    {
        $payload = array();
        $payload = $this->helper->assignValueToKey($payload, 'data[a]', 1);
        $payload = $this->helper->assignValueToKey($payload, 'data[b]', 2);

        $this->assertSame(
            array(
                'data' => array(
                    'a' => 1,
                    'b' => 2,
                ),
            ),
            $payload
        );
    }

    public function testAssignValueToKeyArrayPathMatchesBracketNotation()
    {
        $fromBracket = $this->helper->assignValueToKey(array(), 'predmet[id]', 99);
        $fromArray = $this->helper->assignValueToKey(array(), array('predmet', 'id'), 99);

        $this->assertSame($fromBracket, $fromArray);
    }

    public function testCreatePayloadUsesNestedApiFieldNames()
    {
        $ref = ApiClientPayloadProbe::getInstance(\Ufw\Component\ApiClient\Client::getInstance());
        $ref->setFieldsMap(array(
            'data[title]' => 'title',
        ));
        $form = array(
            'title' => 'Odluka',
        );

        $payload = $this->helper->createPayload(
            $ref,
            array('data[title]'),
            $form
        );

        $this->assertSame(
            array(
                'data' => array(
                    'title' => 'Odluka',
                ),
            ),
            $payload
        );
    }

    public function testGetFormDataMapsNestedResponseLikeTraitR()
    {
        $probe = ApiClientReadProbe::getInstance(\Ufw\Component\ApiClient\Client::getInstance());
        $probe->setFieldsMap(array(
            'remote' => array(
                'title' => 'local_title',
                'meta' => array(
                    'id' => 'local_id',
                ),
            ),
        ));
        $probe->setData(array(
            'remote' => array(
                'title' => 'T',
                'meta' => array(
                    'id' => 42,
                ),
            ),
        ));

        $formData = $probe->exposeGetFormData();

        $this->assertSame(
            array(
                'local_title' => 'T',
                'local_id' => 42,
            ),
            $formData
        );
    }

    public function testNormalizeStringKeyedArrayKeepsStringKeysOnly()
    {
        if (!RuntimeProbe::apiClientHelperHasNormalizeStringKeyedArray()) {
            $this->markTestSkipped('normalizeStringKeyedArray ships with UFW 1.1 ApiClient helper.');
        }
        $in = array(
            'a' => 1,
            0 => 'drop',
            'nested' => array('k' => 'v'),
        );
        $out = $this->helper->normalizeStringKeyedArray($in);

        $this->assertSame(
            array(
                'a' => 1,
                'nested' => array('k' => 'v'),
            ),
            $out
        );
    }

    public function testNormalizeStringKeyedArrayUsedBeforeFormPopulate()
    {
        if (!RuntimeProbe::apiClientHelperHasNormalizeStringKeyedArray()) {
            $this->markTestSkipped('normalizeStringKeyedArray ships with UFW 1.1 ApiClient helper.');
        }
        $normalized = $this->helper->normalizeStringKeyedArray(array(
            'ptype' => '1',
            0 => 'ignored',
            'data' => array(
                'field' => 'x',
            ),
        ));

        $this->assertSame('1', $normalized['ptype']);
        $this->assertArrayNotHasKey(0, $normalized);
        $this->assertSame(array('field' => 'x'), $normalized['data']);
    }
}

class ApiClientPayloadProbe extends \Ufw\Component\ApiClient\Base
{
    public static function resetInstances()
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Base::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
    }

    /**
     * @param array<string, mixed> $map
     * @return $this
     */
    public function setFieldsMap(array $map)
    {
        $this->fieldsMap = $map;

        return $this;
    }
}

class ApiClientReadProbe extends \Ufw\Component\ApiClient\Base
{
    use \Ufw\Component\ApiClient\Base\TraitR;

    public static function resetInstances()
    {
        $prop = new \ReflectionProperty(\Ufw\Component\ApiClient\Base::class, '_instance');
        $prop->setAccessible(true);
        $prop->setValue(null, array());
    }

    /**
     * @return \Ufw\Component\ApiClient\Response|null
     */
    protected function _doRead()
    {
        return null;
    }

    /**
     * @param array<string, mixed> $map
     * @return $this
     */
    public function setFieldsMap(array $map)
    {
        $this->fieldsMap = $map;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function exposeGetFormData()
    {
        return $this->getFormData();
    }
}
