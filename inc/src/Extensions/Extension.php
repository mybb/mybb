<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use MyBB\Extensions\Traits\IntegrityTrait;
use MyBB\Utilities\FileStamp;

abstract class Extension
{
    use IntegrityTrait;

    /**
     * @var class-string<Repository<static>>
     */
    public const REPOSITORY_CLASS = Repository::class;

    final public const MANIFEST_FILE_PATH = 'manifest.json';

    public const DEFAULT_VERSION = 'dev';


    /**
     * Definitions and validation of manifest fields.
     *
     * @var array<string, array{
     *   required: bool,
     *   type: string,
     *   value?: scalar|callable,
     * }>
     */
    protected array $manifestFields = [];

    protected array $manifest;
    protected array $declaredFileChecksums;

    private FileStamp $manifestStamp;

    private readonly string $version;

    public static function codenameValid(string $value): bool
    {
        return preg_match('/[a-z_]+/', $value) === 1;
    }

    public function __construct(
        protected readonly string $packageName,
        protected readonly Filesystem $filesystem,
        ?string $version = null,
    )
    {
        if ($version !== null) {
            $this->version = $version;
        }

        $this->manifestFields = [
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

    public function getAbsolutePath(): string
    {
        return static::EXTENSION_TYPE_ABSOLUTE_BASE_PATH . $this->getPackageName();
    }

    public function getPackageName(): string
    {
        return $this->packageName;
    }

    public function getVersion(): string
    {
        return $this->version ??=
            $this->getManifest()['version'] ?? self::DEFAULT_VERSION;
    }

    public function getManifest(): ?array
    {
        if (!isset($this->manifest)) {
            $path = $this->getManifestFilePath();

            if ($this->filesystem->isFile($path)) {
                $content = $this->filesystem->get($path);

                $this->manifestStamp = FileStamp::fromFile($path, $content);

                $values = json_decode(
                    $content,
                    flags: JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR,
                );

                $this->validateManifestValues($values);

                $this->manifest = $values;
            } else {
                $this->manifestStamp = FileStamp::fromNonexistentFile();

                return null;
            }
        }

        return $this->manifest;
    }

    public function getManifestFilePath(): string
    {
        return $this->getAbsolutePath() . '/' . static::MANIFEST_FILE_PATH;
    }

    public function validateManifestValues(array $values): void
    {
        foreach ($this->manifestFields as $name => $field) {
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
                throw new Exception('Package `' . $this->getPackageName() . '` manifest field `' . $name . '` ' . $error);
            }
        }
    }

    public function getManifestStamp(): ?array
    {
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
