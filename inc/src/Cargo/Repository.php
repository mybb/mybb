<?php

declare(strict_types=1);

namespace MyBB\Cargo;

use Exception;
use Illuminate\Support\Arr;
use MyBB\Utilities\FileStamp;
use RuntimeException;
use UnexpectedValueException;

/**
 * Manages entities and related metadata in a common, human-readable format.
 */
abstract class Repository implements RepositoryInterface
{
    final public const SCOPE_SHARED = 'shared';
    final public const SCOPE_ENTITY = 'entity';

    public const ANCESTOR_DECLARATIONS_KEY = 'inherits';
    public const INHERIT_BY_DEFAULT = true;

    protected const REMOVE_NULL_PROPERTIES = true;
    protected const REMOVE_EMPTY_PROPERTIES = true;

    /**
     * Identifier of the metadata file and the entities array.
     */
    public const NAME = null;

    /**
     * @var array<self::SCOPE_*, array<string, mixed>>
     */
    protected array $properties = [
        self::SCOPE_SHARED => [],
        self::SCOPE_ENTITY => [],
    ];

    protected bool $propertiesLoaded = false;

    protected FileStamp $propertiesStamp;

    protected string $inheritanceManagedValueValidationType = FileStamp::TYPE_CHECKSUM;

    /**
     * Returns the resulting set of properties assigned the same entity key.
     *
     * @param array[] $properties
     */
    public static function getMergedProperties(array $properties): array
    {
        return array_merge_recursive(...$properties);
    }

    /**
     * Returns the Repository's identifier unique in a potential inheritance hierarchy.
     */
    abstract public function getHierarchicalIdentifier(): string;

    /**
     * Returns properties declared at the top level.
     */
    public function getSharedProperties(): array
    {
        $this->loadProperties();

        return $this->properties[self::SCOPE_SHARED];
    }

    /**
     * Returns a property declared at the top level.
     */
    public function getSharedProperty(string $key): mixed
    {
        $this->loadProperties();

        return $this->properties[self::SCOPE_SHARED][$key] ?? null;
    }
    /**
     * Sets a property declared at the top level.
     */
    public function setSharedProperty(string $key, mixed $value): void
    {
        if ($key === static::NAME) {
            throw new UnexpectedValueException('Illegal property key `' . $key . '`');
        }

        $this->properties[self::SCOPE_SHARED][$key] = $value;

        $this->writeProperties();
    }

    /**
     * Returns properties of a member entity.
     */
    public function getEntityProperties(?string $key = null): array
    {
        $this->loadProperties();

        if ($key === null) {
            return $this->properties[self::SCOPE_ENTITY];
        } else {
            return $this->properties[self::SCOPE_ENTITY][$key] ?? [];
        }
    }

    /**
     * Sets properties of member entity.
     */
    public function setEntityProperties(string $key, array $data): void
    {
        // ordinary merge to overwrite
        $this->properties[self::SCOPE_ENTITY][$key] = array_merge(
            $this->properties[self::SCOPE_ENTITY][$key] ?? [],
            $data,
        );

        $this->writeProperties();
    }

    /**
     * Whether the repository, and all member entities, are declared as inherited.
     */
    public function declaredInherited(): bool
    {
        $value = $this->getSharedProperty(self::ANCESTOR_DECLARATIONS_KEY);

        return (
             // boolean values supported
            $value === true ||
            (self::INHERIT_BY_DEFAULT && $value === null)
        );
    }

    /**
     * Whether the member entity is declared as inherited.
     */
    public function entityDeclaredInherited(string $key): bool
    {
        $value = $this->getEntityProperties($key)[self::ANCESTOR_DECLARATIONS_KEY] ?? null;

        return (
             // boolean or array values supported
            $value === true ||
            is_array($value) ||
            (self::INHERIT_BY_DEFAULT && $value === null)
        );
    }

    /**
     * Returns member entities declared as not inherited.
     */
    public function getEntitiesDeclaredDisinherited(): array
    {
        return array_filter(
            array_keys($this->getEntityProperties()),
            fn (string $key) => !$this->entityDeclaredInherited($key),
        );
    }

    /**
     * Returns a stamp value used for cache validation.
     */
    public function getStamp(): ?array
    {
        $this->loadProperties();

        return $this->propertiesStamp->getStamp();
    }

    /**
     * Whether the given stamp indicates the cache is up to date.
     */
    public function stampValid(FileStamp $stamp): bool
    {
        return $stamp->isValid(
            $this->getPropertiesFilePath(),
            $this->inheritanceManagedValueValidationType,
        );
    }

    protected function loadProperties(): void
    {
        if (!$this->propertiesLoaded) {
            $this->properties = $this->readProperties();
        }
    }

    protected function readProperties(): array
    {
        $results = [
            self::SCOPE_SHARED => [],
            self::SCOPE_ENTITY => [],
        ];

        $path = $this->getPropertiesFilePath();

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if ($content === false) {
                throw new Exception('Could not open properties file `' . $path . '`');
            }

            $this->propertiesStamp = FileStamp::fromFile($path, $content);

            $data = json_decode(
                $content,
                flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
            );

            $results[self::SCOPE_SHARED] = Arr::except($data, [static::NAME]);

            if (
                isset($data[static::NAME]) &&
                is_array($data[static::NAME])
            ) {
                $results[self::SCOPE_ENTITY] = $data[static::NAME];
            }
        } else {
            $this->propertiesStamp = FileStamp::fromNonexistentFile();
        }

        return $results;
    }

    protected function writeProperties(): void
    {
        $path = $this->getPropertiesFilePath();

        // create directory
        $directory = dirname($path);

        if (!is_dir($directory)) {
            if (!mkdir($directory, recursive: true)) {
                throw new RuntimeException('Failed to create directory `' . $directory . '`');
            }
        }

        // lock
        $fp = fopen($path, 'c+');

        if ($fp === false) {
            throw new RuntimeException('Failed to open `' . $path . '`');
        }

        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Failed to acquire exclusive lock for `' . $path . '`');
        }

        try {
            // merge
            $currentFileData = $this->readProperties();

            foreach ([self::SCOPE_SHARED, self::SCOPE_ENTITY] as $level) {
                $this->properties[$level] = array_merge(
                    $currentFileData[$level],
                    $this->properties[$level],
                );
            }

            $this->normalizeProperties();

            // write
            $newFileData = $this->properties[self::SCOPE_SHARED];
            $newFileData[static::NAME] = $this->properties[self::SCOPE_ENTITY];

            $content = json_encode($newFileData, JSON_PRETTY_PRINT);

            if (
                ftruncate($fp, 0) === false ||
                fseek($fp, 0) === -1 ||
                fwrite($fp, $content) === false
            ) {
                throw new RuntimeException('Failed to write to `' . $path . '`');
            }

            $this->propertiesStamp = FileStamp::fromFile($path, $content);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    protected function normalizeProperties(): void
    {
        // shared properties

        if (static::REMOVE_NULL_PROPERTIES) {
            $this->properties[self::SCOPE_SHARED] = array_filter(
                $this->properties[self::SCOPE_SHARED],
                fn (array $properties) => $properties !== null,
            );
        }

        ksort($this->properties[self::SCOPE_SHARED], SORT_NATURAL);


        // entity properties

        if (static::REMOVE_EMPTY_PROPERTIES) {
            $this->properties[self::SCOPE_ENTITY] = array_filter(
                $this->properties[self::SCOPE_ENTITY],
                fn (array $properties) => $properties !== [],
            );
        }

        if (static::REMOVE_NULL_PROPERTIES) {
            foreach ($this->properties[self::SCOPE_ENTITY] as &$entityProperties) {
                $entityProperties = array_filter(
                    $entityProperties,
                    fn(array $properties) => $properties !== null,
                );
            }
        }

        ksort($this->properties[self::SCOPE_ENTITY], SORT_NATURAL);
    }

    abstract protected function getPropertiesFilePath(): string;
}
