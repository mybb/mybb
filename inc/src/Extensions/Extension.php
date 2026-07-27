<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use JsonException;
use MyBB\Extensions\Exception as ExtensionException;
use MyBB\Extensions\Traits\IntegrityTrait;
use MyBB\Utilities\FileStamp;

abstract class Extension
{
    use IntegrityTrait;

    /**
     * @var class-string<Repository<static>>
     */
    public const REPOSITORY_CLASS = Repository::class;

    /**
     * The path to the manifest file, relative to the Extension's package directory.
     */
    final public const MANIFEST_FILE_PATH = 'extension.json';

    /**
     * The application root-relative path to the directory containing Extension packages of the type.
     */
    public const EXTENSION_TYPE_BASE_PATH = '';

    /**
     * The absolute path to the directory containing Extension packages of the type.
     */
    public const EXTENSION_TYPE_ABSOLUTE_BASE_PATH = '';

    /**
     * The value used when no version is specified in the manifest.
     */
    public const DEFAULT_VERSION = 'dev';

    /**
     * The validated manifest data.
     *
     * @var array<string, mixed>
     */
    protected array $manifest;

    protected FileStamp $manifestStamp;

    /**
     * The version from the manifest, or the default version if not set.
     */
    protected readonly string $version;

    public function __construct(
        protected readonly string $packageName,
        protected readonly Filesystem $filesystem,
    ) {}

    public static function codenameValid(string $value): bool
    {
        return preg_match('/^[a-zA-Z_]+$/', $value) === 1;
    }

    /**
     * Definitions and validation of manifest fields.
     *
     * @return array<string, array{
     *   required: bool,
     *   type: string,
     *   value?: scalar|callable,
     * }>
     *
     * @note https://wiki.php.net/rfc/closures_in_const_expr
     */
    protected static function getManifestFields(): array
    {
        return [
            'version' => [
                'required' => false,
                'type' => 'string',
                'value' => fn ($value) => preg_match('/^[A-Za-z0-9.-]+$/', $value),
            ],
        ];
    }

    /**
     * Whether the Extension's package exists in the filesystem.
     */
    public function exists(): bool
    {
        return $this->filesystem->isDirectory(
            $this->getAbsolutePath()
        );
    }

    /**
     * Returns the absolute path to the Extension's package directory.
     */
    public function getAbsolutePath(): string
    {
        return static::EXTENSION_TYPE_ABSOLUTE_BASE_PATH . '/' . $this->getPackageName();
    }

    /**
     * Returns the filesystem identifier of the Extension's package.
     */
    public function getPackageName(): string
    {
        return $this->packageName;
    }

    /**
     * Returns the version from the manifest, or the default version if not set.
     *
     * @throws ExtensionException if the manifest is not valid.
     */
    public function getVersion(): string
    {
        return $this->version ??=
            $this->getManifest()['version'] ?? self::DEFAULT_VERSION;
    }

    /**
     * @return ?array<string, mixed> The validated manifest data.
     *
     * @throws ExtensionException if the manifest is not valid.
     */
    public function getManifest(): ?array
    {
        if (!isset($this->manifest)) {
            $path = $this->getManifestFilePath();

            if ($this->filesystem->isFile($path)) {
                $content = $this->filesystem->get($path);

                $this->manifestStamp = FileStamp::fromFile($path, $content);

                try {
                    $values = json_decode(
                        $content,
                        flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
                    );
                } catch (JsonException $e) {
                    throw new ExtensionException(
                        'Invalid manifest JSON',
                        $this,
                        previous: $e,
                    );
                }

                $this->validateManifestValues($values);

                $this->manifest = $values;
            } else {
                $this->manifestStamp = FileStamp::fromNonexistentFile();

                return null;
            }
        }

        return $this->manifest;
    }

    /**
     * Returns the path to the Extension's manifest file.
     */
    public function getManifestFilePath(): string
    {
        return $this->getAbsolutePath() . '/' . static::MANIFEST_FILE_PATH;
    }

    /**
     * @throws ExtensionException if the manifest is not valid.
     */
    public function validateManifestValues(array $values): void
    {
        foreach (static::getManifestFields() as $name => $field) {
            $error = null;

            if (Arr::has($values, $name)) {
                $value = Arr::get($values, $name);

                if (gettype($value) === $field['type']) {
                    if (isset($field['value'])) {
                        if (
                            (is_callable($field['value']) && !$field['value']($value)) ||
                            (is_scalar($field['value']) && $field['value'] !== $value)
                        ) {
                            $error = 'value is invalid';
                        }
                    }
                } else {
                    $error = 'value must be of type ' . $field['type'];
                }
            } elseif ($field['required']) {
                $error = 'not found';
            }

            if ($error) {
                throw new ExtensionException(
                    'Manifest field `' . $name . '` ' . $error,
                    $this,
                );
            }
        }
    }

    /**
     * @throws ExtensionException if the manifest is not valid.
     */
    public function getManifestStamp(): ?array
    {
        if (!isset($this->manifestStamp)) {
            $this->getManifest();
        }

        return $this->manifestStamp->getStamp();
    }

    /**
     * @param FileStamp::TYPE_* $type
     */
    public function manifestStampValid(?array $stamp, string $type): bool
    {
        return (new FileStamp($stamp))->isValid(
            $this->getManifestFilePath(),
            $type,
        );
    }
}
