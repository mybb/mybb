<?php

declare(strict_types=1);

namespace MyBB\Database\Models;

use MyBB\Extensions\Theme\Theme as ThemeExtension;

/**
 * A configured instance of a Theme.
 */
class Theme
{
    public function __construct(
        public int $id,
        public ThemeExtension $package,
        public string $name,
        public string $properties = 'a:0:{}',
        public string $stylesheets = 'a:0:{}',
        public string $allowedgroups = 'all',
    ) {}

    public function allowedForUser(array|int $user): bool
    {
        return (
            $this->allowedgroups === 'all' ||
            is_member($this->allowedgroups, $user)
        );
    }
}
