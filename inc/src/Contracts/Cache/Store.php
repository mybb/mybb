<?php

namespace MyBB\Contracts\Cache;

interface Store
{

    /**
     * Connect and initialize this handle.
     *
     * @return bool
     */
    public function connect(): bool;

    /**
     * Disconnect from this handle.
     *
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * @param $key
     * @return mixed
     */
    public function fetch($key);

    /**
     * Store an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  float|int $minutes
     * @return void
     */
    public function put(string $key, $value, $minutes);

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Remove all items from the cache.
     * @return bool
     */
    public function flush(): bool;

    /**
     * Determine the size of an item from the cache.
     *
     * @param string $key
     * @return int
     */
    public function size_of(string $key): int;
}
