<?php
namespace Ufw\Tests\Unit\Form;

use PHPUnit\Framework\TestCase;
use Ufw\Form\Element\Base;
use Ufw\Form\Element\Date;
use Ufw\Form\Element\DateRange;
use Ufw\Form\Element\Text;
use Ufw\Form\Validator\Identical;
use Ufw\Tests\Support\ApplicationFixtures;

class ElementAttributesTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationFixtures::tearDownRegistry();
        parent::tearDown();
    }

    public function testMagicGetExposesOptionAndAttribValues(): void
    {
        ApplicationFixtures::bareWithController();
        $el = new Text('title');
        $el->setOption('label', 'L');
        $el->setAttrib('title', 'field-title');
        $this->assertSame('text', $el->type);
        $this->assertSame('L', $el->label);
        $this->assertSame('field-title', $el->title);
    }

    public function testMagicCallReturnsNullForUnknownMethods(): void
    {
        ApplicationFixtures::bareWithController();
        $el = new Text('t');
        $this->assertNull($el->notARealElementMethod());
    }

    public function testDateGetValueRawReturnsEmptyForNonStringValue(): void
    {
        ApplicationFixtures::bareWithController();
        $date = new Date('started');
        $valueProp = new \ReflectionProperty(Base::class, 'value');
        $valueProp->setAccessible(true);
        $valueProp->setValue($date, 20260101);
        $this->assertSame('', $date->getValueRaw());
    }

    public function testDateGetValueRawParsesDisplayFormatToIso(): void
    {
        ApplicationFixtures::bareWithController();
        $date = new Date('when');
        $date->setValue('2020-01-02');
        $this->assertSame('2020-01-02', $date->getValueRaw());
        $date->setValue('15. 03. 2024.');
        $this->assertSame('2024-03-15', $date->getValueRaw());
    }

    public function testDateGetValueRawReturnsEmptyWhenDisplayStringDoesNotParse(): void
    {
        ApplicationFixtures::bareWithController();
        $date = new Date('when');
        $date->setValue('xx. xx. xxxx.');
        $this->assertSame('', $date->getValueRaw());
    }

    public function testDateRangeGetValueToReturnsNullWhenValueIsNotArray(): void
    {
        ApplicationFixtures::bareWithController();
        $range = new DateRange('period');
        $range->setValue('plain');
        $this->assertNull($range->getValueTo());
    }

    public function testSetValueSkipsNonFilterInstance(): void
    {
        ApplicationFixtures::bareWithController();
        $el = new Text('t');
        $el->setFilter('broken', new \stdClass());
        $el->setValue('hello');
        $this->assertSame('hello', $el->getValue());
    }

    public function testAddFilterRejectsDefinitionWithoutName(): void
    {
        ApplicationFixtures::bareWithController();
        $el = new Text('t');
        $this->expectException(\InvalidArgumentException::class);
        $el->addFilter(array('options' => array()));
    }

    public function testIdenticalValidatorRequiresMatchingContextToken(): void
    {
        ApplicationFixtures::bareWithController();
        $v = new Identical('token');
        $this->assertFalse($v->isValid('x', array()));
        $this->assertFalse($v->isValid('x', array('token' => 'y')));
        $this->assertFalse($v->isValid('a', array()));
    }

    public function testIdenticalValidatorMatchesContextToken(): void
    {
        ApplicationFixtures::bareWithController();
        $v = new Identical('password');
        $this->assertTrue($v->isValid('secret', array('password' => 'secret')));
    }

    public function testIdenticalValidatorMissingTokenAndLiteralMatch(): void
    {
        ApplicationFixtures::bareWithController();
        $missing = new Identical(null);
        $this->assertFalse($missing->isValid('x', array()));
        $this->assertArrayHasKey(Identical::MISSING_TOKEN, $missing->getErrors());

        $literal = new Identical('fixed');
        $this->assertTrue($literal->isValid('fixed'));
        $this->assertFalse($literal->isValid('other'));

        $byIndex = new Identical(0);
        $this->assertTrue($byIndex->isValid('a', array(0 => 'a')));
    }
}
