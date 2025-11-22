<?php

declare(strict_types=1);

namespace MyBB\Extensions\Plugin;

/**
 * @extends \MyBB\Extensions\Repository<Plugin>
 */
class Repository extends \MyBB\Extensions\Repository
{
    public const ENTITY_CLASS = Plugin::class;

    protected function getKeys(): array
    {
        return array_map(
            fn(string $path) => basename($path, '.php'),
            $this->filesystem->glob(static::getAbsoluteBasePath() . '*.php'),
        );
    }
}
