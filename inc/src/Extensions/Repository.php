<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use Illuminate\Filesystem\Filesystem;

/**
 * A repository and registry for object representations of Extensions.
 *
 * @template T of Extension
 */
abstract class Repository
{
    /**
     * @var class-string<T>
     */
    public const ENTITY_CLASS = Extension::class;

    /**
     * Extension object instances, indexed by package name.
     *
     * @var array<string, T>
     */
    protected array $instances = [];

    public function __construct(
        protected readonly Filesystem $filesystem,
    ) {}

    /**
     * Returns the absolute path to the directory containing Extension packages of the type.
     */
    final public static function getAbsoluteBasePath(): string
    {
        return static::ENTITY_CLASS::EXTENSION_TYPE_ABSOLUTE_BASE_PATH;
    }

    /**
     * Returns object representation of the given Extension package.
     *
     * @return T
     */
    public function get(string $packageName): Extension
    {
        return $this->instances[$packageName] ??= new (static::ENTITY_CLASS)($packageName, $this->filesystem);
    }

    /**
     * Returns object representation of the given Extension package, or null if it doesn't exist.
     *
     * @return ?T
     */
    public function getExisting(string $packageName): ?Extension
    {
        $instance = $this->get($packageName);

        if ($instance->exists()) {
            return $instance;
        } else {
            return null;
        }
    }

    /**
     * Returns object representations of Extensions found in the filesystem.
     *
     * @return array<string, T>
     */
    public function getAll(): array
    {
        $instances = [];

        foreach ($this->getKeys() as $key) {
            $instances[$key] = $this->get($key);
        }

        return $instances;
    }

    protected function getKeys(): array
    {
        return array_map(
            basename(...),
            $this->filesystem->directories(static::getAbsoluteBasePath()),
        );
    }
}
