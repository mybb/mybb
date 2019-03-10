<?php

declare(strict_types = 1);

namespace MyBB\Hashing;

use Illuminate\Contracts\Hashing\Hasher;

interface WrappableHasher extends Hasher
{
    /**
     * Hash the given value.
     *
     * @param  string $hash
     * @return array
     */
    public function wrap(string $hash): string;
}
