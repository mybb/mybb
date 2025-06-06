<?php

declare(strict_types=1);

namespace MyBB\View;

use MyBB\Utilities\FileStamp;
use MyBB\View\Themelet\Decorator\PublishableThemelet;

enum Optimization: int
{
    /**
     * No custom cache is used.
     */
    case NONE = 1;

    /**
     * Cache is verified to account for changes during Extension development.
     */
    case WATCH = 2;

    /**
     * Lightweight cache validation to respond to high-level changes.
     */
    case BALANCED = 3;

    /**
     * Cache is not verified to maximize optimization.
     */
    case PERFORMANCE = 4;

    /**
     * Returns configuration values according to chosen mode.
     */
    public function getDirective(string $name): mixed
    {
        $level = $this->value;

        return match ($name) {
            'hierarchy.cache' => $level > self::WATCH->value,
            'hierarchy.cacheValidation' => $level < self::PERFORMANCE->value,
            'hierarchy.cacheValidationType' => FileStamp::TYPE_MODIFICATION_TIME,

            'publication.publishMode' => match (true) {
                $level <= self::NONE->value => PublishableThemelet::PUBLISH_ALWAYS,
                $level >= self::PERFORMANCE->value => PublishableThemelet::PUBLISH_NEVER,
                default => PublishableThemelet::PUBLISH_AUTO,
            },
            'publication.all' => $level <= self::WATCH->value,
            'publication.resolutionValidation' => $level <= self::BALANCED->value,
            'publication.sourceValidation' => $level <= self::WATCH->value,
            'publication.runtimeCache' => true,

            'twig.autoReload' => $level <= self::WATCH->value,
        };
    }
}
