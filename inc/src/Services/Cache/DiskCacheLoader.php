<?php

namespace MyBB\Services\Cache;

use MyBB\Contracts\Cache\CacheItemInterface;

class DiskCacheLoader implements CacheItemInterface
{
    public function pull($key, $default = null)
    {
        // TODO: Implement pull() method.
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function has($key)
    {
        // TODO: Implement has() method.
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        // TODO: Implement get() method.
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param \DateInterval|\DateTimeInterface|float|int $minutes
     * @return mixed
     */
    public function put($key, $value, $minutes)
    {
        // TODO: Implement put() method.
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param \DateInterval|\DateTimeInterface|float|int $minutes
     * @return mixed
     */
    public function add($key, $value, $minutes)
    {
        // TODO: Implement add() method.
    }

    /**
     * @param string $key
     * @param int|mixed $value
     * @return mixed
     */
    public function increment($key, $value = 1)
    {
        // TODO: Implement increment() method.
    }

    /**
     * @param string $key
     * @param int|mixed $value
     * @return mixed
     */
    public function decrement($key, $value = 1)
    {
        // TODO: Implement decrement() method.
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function forget($key)
    {
        // TODO: Implement forget() method.
    }

}