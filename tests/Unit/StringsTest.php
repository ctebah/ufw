<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Utils\Strings;

class StringsTest extends TestCase
{
    public function testSingletonReturnsSameInstance()
    {
        $this->assertSame(Strings::getInstance(), Strings::getInstance());
    }

    public function testFormatToPhpFriendlyNameBuildsLowerCamelCase()
    {
        $strings = Strings::getInstance();
        $this->assertSame('fooBar', $strings->formatToPhpFriendlyName('foo-bar'));
        $this->assertSame('fooBarSuffix', $strings->formatToPhpFriendlyName('foo.bar', 'Suffix'));
    }

    public function testWordDelimiterCanBeReplaced()
    {
        $strings = Strings::getInstance();
        $strings->setWordDelimiter(array('_'));
        $this->assertSame('fooBar', $strings->formatToPhpFriendlyName('foo_bar'));
        $strings->setWordDelimiter(array('-', '.'));
    }

    public function testFormatToPhpFriendlyNameAcceptsPerCallDelimitersAndArrays()
    {
        $strings = Strings::getInstance();
        $this->assertSame('foobar_Baz', $strings->formatToPhpFriendlyName(array('foo-bar', 'baz'), '', array('_')));
        $this->assertSame('fooBar', $strings->formatToPhpFriendlyName('foo_bar', '', array('_')));
    }

    public function testGetWordDelimiterReturnsConfiguredDelimiters()
    {
        $strings = Strings::getInstance();
        $this->assertSame(array('-', '.'), $strings->getWordDelimiter());
    }

    public function testFormatToPhpFriendlyNameTreatsFailedPregReplaceAsEmptySegment()
    {
        $strings = new StringsPregNullProbe();
        $strings->failNextStrip = true;
        $this->assertSame('', $strings->formatToPhpFriendlyName('foo-bar'));
        $this->assertSame('fooBar', $strings->formatToPhpFriendlyName('foo-bar'));
    }
}

class StringsPregNullProbe extends Strings
{
    /** @var bool */
    public $failNextStrip = false;

    protected function pregReplaceStrip($segment)
    {
        if ($this->failNextStrip) {
            $this->failNextStrip = false;

            return null;
        }

        return parent::pregReplaceStrip($segment);
    }
}
