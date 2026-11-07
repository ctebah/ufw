<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;

class FormElementRenderTest extends TestCase
{
    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array(
            'form' => array('helper' => 'bootstrap5'),
            'dispatch' => array('routes' => array()),
        ));
    }

    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testElementToStringUsesFormRenderHelper()
    {
        $el = new Text('title', array('label' => 'Title'));
        $html = (string) $el;
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('title', $html);
    }

    public function testLabelPlacementAndRenderScriptAccessors()
    {
        $el = new Text('x', array('label' => 'X'));
        $el->setLabelPlacement('below');
        $this->assertSame('below', $el->getLabelPlacement());
        $el->setRenderScript('custom/field.php');
        $this->assertSame('custom/field.php', $el->getRenderScript());
    }

    public function testElementIssetAndMagicSetLabelPlacement()
    {
        $el = new Text('y');
        $el->labelPlacement = 'left';
        $this->assertSame('left', $el->getLabelPlacement());
        $this->assertTrue(isset($el->name));
    }
}
