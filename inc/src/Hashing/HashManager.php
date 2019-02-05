<?php

namespace MyBB\Hashing;

use Illuminate\Support\Str;

class HashManager extends \Illuminate\Hashing\HashManager
{
    /**
     * Create an instance of the Mybb hash Driver.
     *
     * @return \MyBB\Hashing\MybbHasher
     */
    public function createMybbDriver(): MybbHasher
    {
        return new MybbHasher();
    }

    /**
     * Returns whether a driver of given name exists.
     *
     * @param string $name
     * @return bool
     */
    public function driverExists(string $name): bool
    {
        return (
            isset($this->customCreators[$name]) ||
            method_exists($this, 'create' . Str::studly($name) . 'Driver')
        );
    }
}
