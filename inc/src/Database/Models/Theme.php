<?php

declare(strict_types=1);

namespace MyBB\Database\Models;

use MyBB\Extensions\Theme\Theme as ThemeExtension;

/**
 * A configured instance of a Theme.
 */
class Theme
{
    /**
     * @param array<string, mixed> $properties Raw properties loaded from storage.
     */
    public function __construct(
        public ?int $id,
        public ThemeExtension $package,
        public string $name,
        public array $properties = [],
        public array $stylesheets = [],
        public string $allowedgroups = 'all',
    ) {}

    /**
     * Returns effective theme properties.
     *
     * Combines package-defined defaults with stored properties, where
     * stored values override defaults when the same key exists.
     *
     * @return array<string, mixed>
     */
    public function getResolvedProperties(): array
    {
        return array_replace(
            $this->package->getPropertyDefaults(),
            $this->properties,
        );
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
            'properties' => my_serialize($this->getResolvedProperties()),
            'stylesheets' => my_serialize($this->stylesheets),
            'allowedgroups' => $this->allowedgroups,
        ];

        if ($this->id) {
            $array['tid'] = $this->id;
        }

        return $array;
    }
}
