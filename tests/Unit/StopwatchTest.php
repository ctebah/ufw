<?php
namespace Ufw\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ufw\Utils\Stopwatch;

class StopwatchTest extends TestCase
{
    /**
     * @param float $secondsAgo
     * @return Stopwatch
     */
    private function stopwatchElapsedSecondsAgo($secondsAgo)
    {
        $sw = new Stopwatch();
        $ref = new \ReflectionProperty(Stopwatch::class, 'startedAt');
        $ref->setAccessible(true);
        list($usec, $sec) = explode(' ', microtime());
        $now = ((float) $usec + (float) $sec);
        $ref->setValue($sw, $now - $secondsAgo);
        return $sw;
    }

    public function testElapsedIncreasesAfterSleep()
    {
        $sw = new Stopwatch();
        usleep(20000);
        $this->assertGreaterThan(0.0, $sw->elapsed());
    }

    public function testResetRestartsElapsedFromZero()
    {
        $sw = new Stopwatch();
        usleep(20000);
        $sw->reset();
        usleep(1000);
        $elapsed = $sw->elapsed();
        $this->assertGreaterThan(0.0, $elapsed);
        $this->assertLessThan(0.05, $elapsed);
    }

    public function testElapsedHrFormatsSubMinuteDuration()
    {
        $sw = new Stopwatch();
        $formatted = $sw->elapsed_hr(2);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $formatted);
    }

    public function testElapsedHrFormatsMinuteRange()
    {
        $sw = $this->stopwatchElapsedSecondsAgo(125.5);
        $this->assertSame('02:05.50', $sw->elapsed_hr(2));
    }

    public function testElapsedHrFormatsHourRange()
    {
        $sw = $this->stopwatchElapsedSecondsAgo(3665.25);
        $this->assertSame('01:01:05.25', $sw->elapsed_hr(2));
    }

    public function testElapsedHrWithZeroDecimals()
    {
        $sw = $this->stopwatchElapsedSecondsAgo(2.4);
        $this->assertSame('2', $sw->elapsed_hr(0));
    }
}
