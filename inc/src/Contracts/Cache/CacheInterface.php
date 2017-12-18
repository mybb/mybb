<?php

namespace MyBB\Contracts\Cache;

interface CacheInterface
{
    /**
     * Connect and initialize this handle.
     *
     * @return boolean true if successful, false on failure
     */
    public function connect();

    /**
     * Disconnect from this handle.
     *
     * @return mixed
     */
    public function disconnect();

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
    public function fetch($key, $default = null);

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
     * @return void
     */
    public function put($key, $value);

    /**
     * Store an item in the cache if the key does not exist.
     *
     * @param  string $key
     * @param  mixed $value
     * @return bool
     */
    public function add($key, $value);

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     * @return bool
     */
    public function delete($key);

    /**
     * Determine the size
     *
     * @param string $name
     * @return mixed
     */
    public function size_of($name = '');
}
