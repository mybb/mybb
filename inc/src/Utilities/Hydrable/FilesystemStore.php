<?php

declare(strict_types=1);

namespace MyBB\Utilities\Hydrable;

use MyBB\Utilities\Arrays;

class FilesystemStore implements StoreInterface
{
    /**
     * @var array<string, resource>
     */
    private array $filePointers;

    /**
     * @var array<string, array>
     */
    private array $cache = [];

    public function __construct(
        private readonly string $basePath,
    ) {}

    public function get(string $key, array $path = []): mixed
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->loadFile($key);
        }

        return Arrays::getNested($this->cache, [$key, ...$path]);
    }

    public function set(string $key, array $path, array $value): bool
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->loadFile($key);
        }

        Arrays::setNested($this->cache, [$key, ...$path], $value);

        return $this->saveFile($key);
    }

    public function delete(string $key, array $path = []): void
    {
        Arrays::deleteNested($this->cache, [$key, ...$path]);

        if ($path === []) {
            unlink($this->getFilePath($key));
        }
    }

    public function lock(string $key, bool $exclusive = true): bool
    {
        $filePath = $this->getFilePath($key);
        $directoryPath = dirname($filePath);

        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, recursive: true);
        }

        $pointer = $this->filePointers[$key] ??= fopen($filePath, 'c');

        return flock($pointer, $exclusive ? LOCK_EX : LOCK_SH);
    }

    public function unlock(string $key): bool
    {
        if ($fp = $this->filePointers[$key]) {
            $result = flock($fp, LOCK_UN);
            fclose($fp);

            unset($this->filePointers[$key]);

            return $result;
        } else {
            return false;
        }
    }

    private function loadFile(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if ($content === false) {
                return false;
            }

            $decoded = json_decode($content, true);

            $this->cache[$key] = $decoded;

            return true;
        } else {
            return false;
        }
    }

    private function saveFile(string $key): bool
    {
        $path = $this->getFilePath($key);

        mkdir(dirname($path), recursive: true);

        return file_put_contents(
            $path,
            json_encode($this->cache[$key], JSON_PRETTY_PRINT),
        ) !== false;
    }

    private function getFilePath(string $key): string
    {
        return $this->basePath . '/' . $key . '.json';
    }
}
