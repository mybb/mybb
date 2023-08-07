<?php

declare(strict_types=1);

namespace MyBB\Stopwatch;

/**
 * Stores an individual measurement of time and peak memory usage.
 */
class Period
{
    private float $startTime;
    private float $endTime;

    private int $peakMemory;

    /**
     * Saves the start time.
     */
    public function start(?float $time = null): void
    {
        $this->startTime = $time ?? microtime(true);
        $this->probe();
    }

    /**
     * Saves the end time.
     */
    public function stop(?float $time = null): float
    {
        $this->endTime ??= $time ?? microtime(true);

        return $this->getDuration();
    }

    public function stopped(): bool
    {
        return isset($this->endTime);
    }

    /**
     * Updates peak memory usage.
     */
    public function probe(): void
    {
        $memory = memory_get_usage(true);

        if (!isset($this->peakMemory) || $memory > $this->peakMemory) {
            $this->peakMemory = $memory;
        }
    }

    /**
     * Calculates the duration.
     */
    public function getDuration(): float
    {
        return ($this->endTime ?? microtime(true)) - $this->startTime;
    }

    /**
     * Returns the maximum memory usage.
     */
    public function getPeakMemory(): int
    {
        return $this->peakMemory;
    }
}
