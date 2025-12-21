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
        public ?int $id,
        public ThemeExtension $package,
        public string $name,
        public array $properties = [],
        public array $stylesheets = [],
        public string $allowedgroups = 'all',
    ) {
        $this->properties['templateset'] ??= 0;
        $this->properties['editortheme'] ??= 'mybb.css';
        $this->properties['disporder'] ??= [];
    }

    public function allowedForUser(array|int $user): bool
    {
        return (
            $this->allowedgroups === 'all' ||
            is_member($this->allowedgroups, $user)
        );
    }

    /**
     * Returns a stored representation of the instance.
     */
    public function toArray(): array
    {
        $array = [
            'package' => $this->package->getPackageName(),
            'name' => $this->name,
            'properties' => my_serialize($this->properties),
            'stylesheets' => my_serialize($this->stylesheets),
            'allowedgroups' => $this->allowedgroups,
        ];

        if ($this->id) {
            $array['tid'] = $this->id;
        }

        return $array;
    }
}
