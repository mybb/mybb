<?php

namespace MyBB\Contracts\Config;

interface LoaderInterface
{
    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param null $default
     * @return mixed
     */
    public function get($key, $default = null);

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     * @return mixed
     */
    public function has($key);

    /**
     * Return all configuration values.
     *
     * @return array
     */
    public function all();

    /**
     * Set a configuration value.
     *
     * @param string $key
     * @param null $value
     * @return mixed
     */
    public function set($key, $value = null);
}