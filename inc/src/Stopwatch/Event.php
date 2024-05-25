<?php

declare(strict_types=1);

namespace MyBB\Stopwatch;

/**
 * Stores time and memory usage measurements.
 */
class Event
{
    /**
     * @var Period[]
     */
    private array $periods = [];

    /**
     * Creates a new period, and saves the start time.
     */
    public function addPeriod(?float $time = null): Period
    {
        $period = new Period();

        $period->start($time);

        $this->periods[] = $period;

        return $period;
    }

    public function stopFirstRunningPeriod(?float $time): float
    {
        foreach ($this->periods as $period) {
            if (!$period->stopped()) {
                return $period->stop($time);
            }
        }

        throw new \LogicException('Attempting to stop event with no running periods');
    }

    /**
     * Calculates the duration of an event.
     */
    public function getDuration(): float
    {
        return array_sum(
            array_map(
                fn (Period $period) => $period->getDuration(),
                $this->periods,
            ),
        );
    }

    /**
     * Returns the maximum memory usage.
     */
    public function getPeakMemory(): float
    {
        return max(
            array_map(
                fn (Period $period) => $period->getPeakMemory(),
                $this->periods,
            ),
        );
    }

    /**
     * @return Period[]
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }
}
