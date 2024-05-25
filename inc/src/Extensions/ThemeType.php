<?php

declare(strict_types=1);

namespace MyBB\Extensions;

use InvalidArgumentException;

enum ThemeType
{
    case CORE;
    case ORIGINAL;
    case BOARD;

    public static function tryFromPackageName(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->packageNameValid($value)) {
                return $case;
            }
        }

        return null;
    }

    public function packageNameValid(string $value): bool
    {
        return (
            str_starts_with($value, $this->getPrefix()) &&
            $this->packageIdentifierValid(
                $this->getPackageIdentifier($value)
            )
        );
    }

    public function getPackageIdentifier(string $value): string
    {
        $prefix = $this->getPrefix();

        if (!str_starts_with($value, $prefix)) {
            throw new InvalidArgumentException();
        }

        return substr($value, strlen($prefix));
    }

    public function getPrefix(): string
    {
        return match ($this) {
            self::CORE => 'core.',
            self::ORIGINAL => '',
            self::BOARD => 'theme.',
        };
    }

    public function getPackageNameFromIdentifier(string $identifier): string
    {
        return $this->getPrefix() . $identifier;
    }

    private function packageIdentifierValid(string $value): bool
    {
        return match ($this) {
            self::CORE, self::ORIGINAL => Extension::codenameValid($value),
            self::BOARD => ctype_digit($value),
        };
    }
}
