<?php

declare(strict_types=1);

namespace MyBB\View;

enum ResourceType: string
{
    case IMAGE = 'image';
    case SCRIPT = 'script';
    case STYLE = 'style';
    case TEMPLATE = 'template';

    public static function tryFromFilename(string $filename): ?self
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return self::tryFromFilenameExtension($extension);
    }

    public static function tryFromFilenameExtension(string $extension): ?self
    {
        return match ($extension) {
            'js' => self::SCRIPT,
            'css', 'scss' => self::STYLE,
            'twig' => self::TEMPLATE,
            default => null,
        };
    }

    public static function tryFromPlural(string $value): ?self
    {
        return self::tryFrom(
            rtrim($value, 's')
        );
    }

    public function getPlural(): string
    {
        return $this->value . 's';
    }

    public function getDirectoryName(): string
    {
        return $this->getPlural();
    }
}
