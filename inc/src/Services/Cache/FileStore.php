<?php

namespace MyBB\Services\Cache;

use Exception;
use MyBB\Contracts\Cache\Store;
use Illuminate\Filesystem\Filesystem;
use MyBB\Services\Support\InteractsWithTime;

class FileStore implements Store
{
    use InteractsWithTime;

    /**
     * The Illuminate Filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The file cache directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * Create a new file cache store instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem $files
     * @param  string $directory
     */
    public function __construct(Filesystem $files, $directory)
    {
        $this->files = $files;
        $this->directory = $directory;
    }

    /**
     * @return mixed
     */
    public function connect(): bool
    {
        if (!$this->files->isWritable($this->directory)) {
            return false;
        }

        return true;
    }

    /**
     * @return mixed
     */
    public function disconnect(): bool
    {
        return true;
    }

    /**
     * @param string $key
     * @return null
     */
    public function fetch($key)
    {
        return $this->getPayload($key)['data'] ?? null;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function put(string $key, $value, $minutes = 0)
    {
        $this->ensureCacheDirectoryExists($path = $this->path($key));

        $this->files->put(
            $path, $this->expiration($minutes) . serialize($value), true
        );
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        if ($this->files->exists($file = $this->path($key))) {
            return $this->files->delete($file);
        }
        return false;
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush(): bool
    {
        if (!$this->files->isDirectory($this->directory)) {
            return false;
        }

        foreach ($this->files->directories($this->directory) as $directory) {
            if (!$this->files->deleteDirectory($directory)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine the size of an item from the cache.
     * @param string $key
     * @return int
     */
    public function size_of(string $key): int
    {
        $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);

        $path = $this->directory . '/' . implode('/', $parts) . '/' . $hash;

        if (!$this->files->exists($path)) {
            return 0;
        }

        return $this->files->size($path);
    }

    /**
     * Retrieve an item and expiry time from the cache by key.
     *
     * @param  string $key
     * @return array
     */
    protected function getPayload(string $key): array
    {
        $path = $this->path($key);

        try {
            $expire = substr($contents = $this->files->get($path, true), 0, 10);
        } catch (Exception $e) {
            return $this->emptyPayload();
        }


        if ($this->currentTime() >= $expire) {
            $this->delete($key);

            return $this->emptyPayload();
        }

        $data = unserialize(substr($contents, 10));

        $time = ($expire - $this->currentTime()) / 60;

        return compact('data', 'time');
    }

    /**
     * Get a default empty payload for the cache.
     *
     * @return array
     */
    protected function emptyPayload()
    {
        return ['data' => null, 'time' => null];
    }

    /**
     * @param $path
     * @return bool
     */
    protected function ensureCacheDirectoryExists($path)
    {
        if (!$this->files->exists(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }

    /**
     * Get the full path for the given cache key.
     *
     * @param  string $key
     * @return string
     */
    protected function path($key)
    {
        $parts = array_slice(str_split($hash = sha1($key), 2), 0, 2);

        return $this->directory . '/' . implode('/', $parts) . '/' . $hash;
    }

    /**
     * Get the expiration time based on the given minutes.
     *
     * @param  float|int $minutes
     * @return int
     */
    protected function expiration($minutes)
    {
        $time = $this->availableAt((int)($minutes * 60));

        return $minutes === 0 || $time > 9999999999 ? 9999999999 : (int)$time;
    }
}
