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
        $language = ResourceLanguage::tryFromFilename($filename);

        if ($language === null) {
            return null;
        } else {
            return self::tryFromLanguage($language);
        }
    }

    public static function tryFromLanguage(ResourceLanguage $language): ?self
    {
        return match ($language) {
            ResourceLanguage::JAVASCRIPT
                => self::SCRIPT,
            ResourceLanguage::CSS,
            ResourceLanguage::SASS,
            ResourceLanguage::SCSS
                => self::STYLE,
            ResourceLanguage::TWIG
                => self::TEMPLATE,
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
