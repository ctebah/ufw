<?php
namespace Ufw\Utils;

/**
 * Simple high-resolution timer: {@see reset()} start time, {@see elapsed()} / {@see elapsed_hr()} for profiling output.
 */
class Stopwatch
{

    /**
     * @var float
     */
    protected $startedAt;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * @return float
     */
    protected function getmicrotime()
    {
        list ($usec, $sec) = explode(' ', microtime());
        return ((float) $usec + (float) $sec);
    }

    /**
     * @return void
     */
    public function reset()
    {
        $this->startedAt = $this->getmicrotime();
    }

    /**
     * @return float
     */
    public function elapsed()
    {
        return $this->getmicrotime() - $this->startedAt;
    }

    /**
     * @param int $decimals
     * @return string
     */
    public function elapsed_hr($decimals = 2)
    {
        $secondsNumber = 2 + ($decimals ? $decimals + 1 : 0);
        $seconds = $this->getmicrotime() - $this->startedAt;
        if ($seconds < 60) {
            $seconds = floor($seconds * pow(10, $decimals)) / pow(10, $decimals);
            return sprintf("%1.{$decimals}f", $seconds);
        } elseif ($seconds < 3600) {
            $mins = (int)($seconds / 60);
            $seconds = $seconds - $mins * 60;
            $seconds = floor($seconds * pow(10, $decimals)) / pow(10, $decimals);
            return sprintf("%02d:%0{$secondsNumber}.{$decimals}f", $mins, $seconds);
        } else {
            $hours = (int) ($seconds / 3600);
            $seconds = $seconds - $hours * 3600;
            $mins = (int) ($seconds / 60);
            $seconds = $seconds - $mins * 60;
            $seconds = floor($seconds * pow(10, $decimals)) / pow(10, $decimals);
            return sprintf("%02d:%02d:%0{$secondsNumber}.{$decimals}f", $hours, $mins, $seconds);
        }
    }
}
