<?php

namespace MyBB\Contracts\Config;

interface LoaderInterface
{
    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param string|null $default A default value to use if the specified configuration value doesn't exist.
     * @return mixed
     */
    public function get(String $key, $default = null);

    /**
     * Determine if the given configuration value exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(String $key) : bool;

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
     * @param mixed $value
     * @return mixed
     */
    public function set(String $key, $value = null);
}