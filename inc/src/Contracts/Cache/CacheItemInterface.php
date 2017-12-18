<?php

namespace MyBB\Contracts\Cache;

interface CacheItemInterface
{
    /**
     * Determine if an item exists in the cache.
     *
     * @param  string $key
     * @return bool
     */
    public function has($key);

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Retrieve an item from the cache and delete it.
     *
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function pull($key, $default = null);

    /**
     * Store an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  \DateTimeInterface|\DateInterval|float|int $minutes
     * @return void
     */
    public function put($key, $value, $minutes);

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  \DateTimeInterface|\DateInterval|float|int $minutes
     * @return bool
     */
    public function add($key, $value, $minutes);

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @return int|bool
     */
    public function increment($key, $value = 1);

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @return int|bool
     */
    public function decrement($key, $value = 1);

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     * @return bool
     */
    public function forget($key);
}
