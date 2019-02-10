<?php

namespace MyBB\Hashing;

use Illuminate\Hashing\AbstractHasher as AbstractHasher;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class MybbHasher extends AbstractHasher implements HasherContract
{
    /**
     * Indicates whether to perform an algorithm check.
     *
     * @var bool
     */
    protected $verifyAlgorithm = false;

    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     */
    public function make($value, array $options = [])
    {
        $salt = $options['salt'] ?? null;

        $hash = md5(md5($salt) . md5($value));

        return $hash;
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @param  array  $options
     * @return bool
     */
    public function check($value, $hashedValue, array $options = [])
    {
        $hashedUserString = self::make($value, $options);

        return hash_equals($hashedValue, $hashedUserString);
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = [])
    {
        return false;
    }
}
