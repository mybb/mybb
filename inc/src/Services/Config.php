<?php

namespace MyBB\Services;

use MyBB\Contracts\Config\LoaderInterface;

class Config implements LoaderInterface
{
    /**
     * Configuration loader
     *
     * @var LoaderInterface $loader
     */
    protected $loader;

    public function __construct(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    public function get(string $key, $default = null) : string
    {
        return $this->loader->get($key, $default);
    }

    public function has(string $key) : bool
    {
        return $this->loader->has($key);
    }

    public function all() : array
    {
        return $this->loader->all();
    }

    public function set(string $key, $value = null)
    {
        return $this->loader->set($key, $value);
    }
}