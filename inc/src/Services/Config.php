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

    public function get($key, $default = null)
    {
        return $this->loader->get($key, $default);
    }

    public function has($key)
    {
        return $this->loader->has($key);
    }

    public function all()
    {
        return $this->loader->all();
    }

    public function set($key, $value = null)
    {
        return $this->loader->set($key, $value);
    }
}