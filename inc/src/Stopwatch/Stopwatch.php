<?php

declare(strict_types=1);

namespace MyBB\Stopwatch;

/**
 * Manages Stopwatch Events.
 */
class Stopwatch
{
    public const DEFAULT_GROUP = 0;

    /**
     * Events by group.
     *
     * @var array<0|string, array<int|string, Event>>
     */
    private array $events = [];

    public function start(?string $name = null, ?string $group = null, ?float $time = null): Period
    {
        if ($name !== null) {
            $event = $this->getEvent($name, $group);
        }

        $event ??= $this->addEvent($name, $group);

        return $event->addPeriod($time);
    }

    public function stop(string $name, ?string $group = null, ?float $time = null): float
    {
        $event = $this->getEvent($name, $group);

        if ($event === null) {
            throw new \LogicException('Attempting to stop non-existent event');
        }

        return $event->stopFirstRunningPeriod($time);
    }

    public function addEvent(?string $name = null, ?string $group = null): Event
    {
        $event = new Event();

        if ($name === null) {
            $this->events[$group ?? self::DEFAULT_GROUP][] = $event;
        } else {
            $this->events[$group ?? self::DEFAULT_GROUP][$name] = $event;
        }

        return $event;
    }

    public function getEvent(string $name, ?string $group = null): ?Event
    {
        return $this->events[$group ?? self::DEFAULT_GROUP][$name] ?? null;
    }

    /**
     * @return ($group is null ? array<string, array<string, Event>> : array<string, Event>)
     */
    public function getEvents(?string $group = null): array
    {
        if ($group === null) {
            return $this->events;
        } else {
            return $this->events[$group] ?? [];
        }
    }
}
