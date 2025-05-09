<?php

declare(strict_types=1);

namespace MyBB\View;

enum ResourceLanguage: string
{
    case CSS = 'CSS';
    case JAVASCRIPT = 'JavaScript';
    case SASS = 'Sass';
    case SCSS = 'SCSS';
    case TWIG = 'Twig';

    public static function tryFromFilename(string $filename): ?self
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return self::tryFromFilenameExtension($extension);
    }

    public static function tryFromFilenameExtension(string $extension): ?self
    {
        return match ($extension) {
            'css' => self::CSS,
            'js' => self::JAVASCRIPT,
            'sass' => self::SASS,
            'scss' => self::SCSS,
            'twig' => self::TWIG,
            default => null,
        };
    }
}
