<?php

namespace MyBB\Services\Config;

use MyBB\Contracts\Config\LoaderInterface;

class DatabaseConfigLoader implements LoaderInterface
{
    public function get(string $key, $default = null)
    {
        // TODO: Implement get() method.
    }

    public function has(string $key) : bool
    {
        // TODO: Implement has() method.
    }

    public function all() : array
    {
        // TODO: Implement all() method.
    }

    public function set(string $key, $value = null)
    {
        // TODO: Implement set() method.
    }
}