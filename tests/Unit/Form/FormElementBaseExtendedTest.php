<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Text;
use Ufw\Tests\Support\ApplicationFixtures;
use Ufw\Tests\Support\FormFixtureConfig;

class FormElementBaseExtendedTest extends TestCase
{
    use FormFixtureConfig;

    protected function setUp(): void
    {
        ApplicationFixtures::bareWithController(array_merge(self::formFixtureAppConfig(), array(
            'form' => array('helper' => 'bootstrap5'),
        )));
        \Ufw\Form\Element\Base::setRenderHelperName('bootstrap5');
    }

    protected function tearDown(): void
    {
        \Ufw\Form\Element\Base::setRenderHelperName(null);
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testMetadataMagicCallAndRenderScriptFallback()
    {
        $el = new Text('code', array(
            'metadata' => array('max' => 10),
            'label' => 'Code',
        ));
        $this->assertSame(array('max' => 10), $el->getMetadata());
        $this->assertSame('Code', $el->getLabel());
        $path = new \ReflectionProperty(Text::class, 'renderScriptPath');
        $path->setAccessible(true);
        $path->setValue($el, 'fields/text.php');
        $this->assertSame('bootstrap5/fields/text.php', $el->getRenderScript());
    }

    public function testIsValidSkipsMissingValidatorInstance()
    {
        $el = new class('x') extends Text {
            public function getValidator($name)
            {
                return null;
            }

            public function getValidatorNames()
            {
                return array('ghost');
            }
        };
        $this->assertTrue($el->isValid());
    }

    public function testGetHelperReturnsNullWithoutApplication()
    {
        ApplicationFixtures::tearDownRegistry();
        $el = new Text('orphan');
        $this->assertNull($el->getHelper('url'));
    }

    public function testAddClassRemoveClassAndRequiredValidation()
    {
        $el = new Text('req', array('required' => true, 'class' => 'form-control'));
        $el->addClass('is-invalid');
        $this->assertStringContainsString('is-invalid', (string) $el->getAttrib('class'));
        $el->removeClass('is-invalid');
        $this->assertFalse($el->isValid());
    }

    public function testGetRenderHelperNameFromApplicationConfig()
    {
        $el = new Text('t');
        $this->assertSame('bootstrap5', $el->getRenderHelperName());
    }
}
