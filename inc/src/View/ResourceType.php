<?php

declare(strict_types=1);

namespace MyBB\View;

use MyBB\Utilities\CodeLanguage;

enum ResourceType: string
{
    case IMAGE = 'image';
    case SCRIPT = 'script';
    case STYLE = 'style';
    case TEMPLATE = 'template';

    public static function tryFromFilename(string $filename): ?self
    {
        $language = CodeLanguage::tryFromFilename($filename);

        if ($language === null) {
            return null;
        } else {
            return self::tryFromCodeLanguage($language);
        }
    }

    public static function tryFromCodeLanguage(CodeLanguage $language): ?self
    {
        return match ($language) {
            CodeLanguage::JAVASCRIPT
                => self::SCRIPT,
            CodeLanguage::CSS,
            CodeLanguage::SASS,
            CodeLanguage::SCSS
                => self::STYLE,
            CodeLanguage::TWIG
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
