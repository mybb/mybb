<?php

declare(strict_types=1);

namespace MyBB\Utilities\ManagedValue;

use InvalidArgumentException;
use MyBB\Utilities\Arrays;

/**
 * A filesystem-based store.
 *
 * Locks and caches at the top key level.
 */
class FilesystemNestedStore implements NestedStoreInterface
{
    protected readonly string $basePath;

    /**
     * Data read from storage, cached by top-level keys.
     *
     * @var array<string, mixed>
     */
    protected array $cache = [];

    /**
     * @var array<string, resource|false>
     */
    protected array $filePointers;

    /**
     * @param string[] $path
     */
    public function __construct(
        protected readonly array $path,
    )
    {
        $this->basePath = implode('/', $path);
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function get(array $path): mixed
    {
        $key = $path[0] ?? throw new InvalidArgumentException();

        if (!array_key_exists($key, $this->cache)) {
            $this->loadFile($key);
        }

        return Arrays::getNested($this->cache, $path);
    }

    public function set(array $path, mixed $value): bool
    {
        $key = $path[0] ?? throw new InvalidArgumentException();

        if (!array_key_exists($key, $this->cache)) {
            $this->loadFile($key);
        }

        Arrays::setNested($this->cache, $path, $value);

        return $this->saveFile($key);
    }

    public function delete(array $path): bool
    {
        $key = array_shift($path) ?? throw new InvalidArgumentException();

        Arrays::deleteNested($this->cache, [$key, ...$path]);

        if ($path === []) {
            return unlink($this->getFilePath($key));
        }

        return true;
    }

    /**
     * Locks the top-level path key.
     */
    public function lock(array $path, bool $exclusive = true): bool
    {
        $key = $path[0] ?? throw new InvalidArgumentException();

        $filePath = $this->getFilePath($key);
        $directoryPath = dirname($filePath);

        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, recursive: true);
        }

        $pointer = $this->filePointers[$key] ??= fopen($filePath, 'c');

        if ($pointer === false) {
            return false;
        }

        return flock($pointer, $exclusive ? LOCK_EX : LOCK_SH);
    }

    /**
     * Unlocks the top-level path key.
     */
    public function unlock(array $path): bool
    {
        $key = $path[0] ?? throw new InvalidArgumentException();

        if ($fp = $this->filePointers[$key]) {
            $result = flock($fp, LOCK_UN);
            fclose($fp);

            unset($this->filePointers[$key]);

            return $result;
        } else {
            return false;
        }
    }

    protected function loadFile(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if ($content === false) {
                return false;
            }

            $decoded = json_decode($content, true);

            if ($decoded === null) {
                return false;
            }

            $this->cache[$key] = $decoded;

            return true;
        } else {
            return false;
        }
    }

    protected function saveFile(string $key): bool
    {
        $path = $this->getFilePath($key);

        mkdir(dirname($path), recursive: true);

        $encoded = json_encode($this->cache[$key], JSON_PRETTY_PRINT);

        if ($encoded === false) {
            return false;
        }

        return file_put_contents($path, $encoded) !== false;
    }

    /**
     * @pure
     */
    protected function getFilePath(string $key): string
    {
        return $this->basePath . '/' . $key . '.json';
    }
}
