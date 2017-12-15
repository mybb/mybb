<?php

namespace MyBB\Services\Config;

use MyBB\Contracts\Config\LoaderInterface;

class FileConfigLoader implements LoaderInterface
{
    public function get(String $key, $default = null)
    {
        // TODO: Implement get() method.
    }

    public function has(String $key) : bool
    {
        // TODO: Implement has() method.
    }

    public function all()
    {
        // TODO: Implement all() method.
    }

    public function set(String $key, $value = null)
    {
        // TODO: Implement set() method.
    }
}