<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use Exception;
use Illuminate\Support\Arr;
use MyBB\Utilities\FileStamp;

abstract class Extension
{
    final public const MANIFEST_FILE_PATH = 'manifest.json';
    final public const CHECKSUMS_FILE_PATH = 'checksums';

    public const DEFAULT_VERSION = 'dev';

    /**
     * @var array<string, array{
     *   default: static,
     *   versions: array<string, static>
     * }>
     */
    private static array $instances = [];

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

    private readonly string $packageName;
    private readonly string $version;

    public static function codenameValid(string $value): bool
    {
        return preg_match('/[a-z_]+/', $value) === 1;
    }

    public static function get(string $packageName, ?string $version = null): static
    {
        if ($version === null) {
            $instance = &static::$instances[$packageName]['default'];
        } else {
            $instance = &static::$instances[$packageName]['versions'][$version];
        }

        return $instance ??= new static($packageName, $version);
    }

    public function __construct(string $packageName, ?string $version = null)
    {
        $this->packageName = $packageName;

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

            if (file_exists($path)) {
                $content = file_get_contents($path);

                if ($content === false) {
                    throw new Exception('Could not open manifest file: ' . $path);
                }

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

    /**
     * @return null|array<string, string[]>
     *
     * @see \MyBB\Maintenance\getDeclaredFileChecksums()
     */
    public function getDeclaredFileChecksums(): ?array
    {
        if (!isset($this->declaredFileChecksums)) {
            $declaredFiles = [];

            $path = $this->getAbsolutePath() . '/' . static::CHECKSUMS_FILE_PATH;

            if (!file_exists($path)) {
                return null;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($lines === false) {
                throw new Exception('Could not open checksums file: ' . $path);
            }

            foreach ($lines as $line) {
                $parts = explode(' ', $line, 2);

                if (count($parts) !== 2) {
                    continue;
                }

                $declaredChecksum = trim($parts[0]);
                $relativePath = trim($parts[1]);

                if (!isset($declaredFiles[$relativePath])) {
                    $declaredFiles[$relativePath] = [];
                }

                $declaredFiles[$relativePath][] = $declaredChecksum;
            }

            $this->declaredFileChecksums = $declaredFiles;
        }

        return $this->declaredFileChecksums;
    }

    /**
     * Returns a list of file checksum mismatches by type.
     * Files not declared with checksums are ignored.
     *
     * @return null|array{
     *   changed: string[],
     *   missing: string[],
     * }
     *
     * @see \MyBB\Maintenance\getFileVerificationErrors()
     */
    public function getFileVerificationErrors(): ?array
    {
        $algorithm = 'sha512';
        $bufferLength = 8192;

        $extensionAbsolutePath = $this->getAbsolutePath();

        $fileChecksums = $this->getDeclaredFileChecksums();

        if ($fileChecksums !== null) {
            $results = [
                'changed' => [],
                'missing' => [],
            ];

            // calculate & compare checksums
            foreach ($fileChecksums as $relativePath => $declaredChecksums) {
                $absolutePath = $extensionAbsolutePath . $relativePath;

                if (file_exists($absolutePath)) {
                    $handle = fopen($absolutePath, 'rb');
                    $hashingContext = hash_init($algorithm);

                    while (!feof($handle)) {
                        hash_update($hashingContext, fread($handle, $bufferLength));
                    }

                    fclose($handle);

                    $localChecksum = hash_final($hashingContext);

                    if (!in_array($localChecksum, $declaredChecksums, true)) {
                        $results['changed'][] = $relativePath;
                    }
                } else {
                    $results['missing'][] = $relativePath;
                }
            }

            return $results;
        } else {
            return null;
        }
    }
}
