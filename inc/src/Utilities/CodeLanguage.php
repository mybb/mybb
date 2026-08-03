<?php

declare(strict_types=1);

namespace MyBB\Utilities;

use ScssPhp\ScssPhp\Ast\Sass\Statement\Stylesheet;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\Syntax;
use Throwable;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\TwigFilter;
use Twig\TwigFunction;

enum CodeLanguage: string
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

    /**
     * Returns whether syntax errors were detected, or `null` if no checks were performed.
     */
    public function syntaxValid(string $content): ?bool
    {
        $results = $this->getSyntaxErrors($content, null);

        return $results === null
            ? null
            : $results === [];
    }

    /**
     * Returns an array of detected errors, or `null` if no checks were performed.
     *
     * @return ?Throwable[]
     *
     * @note May call internal third-party features to ignore configuration-related issues.
     */
    public function getSyntaxErrors(string $content, ?string $path): ?array
    {
        return match ($this) {
            self::CSS,
            self::SASS,
            self::SCSS,
                => $this->getStyleSyntaxErrors($content),
            self::TWIG,
                => $this->getTwigSyntaxErrors($content, $path),
            default => null,
        };
    }

    private function getStyleSyntaxErrors(string $content): array
    {
        $syntax = match ($this) {
            self::CSS => Syntax::CSS,
            self::SASS => Syntax::SASS,
            self::SCSS => Syntax::SCSS,
        };

        try {
            Stylesheet::parse($content, $syntax);
        } catch (SassException $e) {
            if (!str_contains($e->getMessage(), 'Undefined')) {
                return [$e];
            }
        }

        return [];
    }

    private function getTwigSyntaxErrors(string $content, ?string $path): array
    {
        $twig = new Environment(
            new ArrayLoader()
        );

        $twig->registerUndefinedFunctionCallback(function ($name) {
            return new TwigFunction($name, static function (...$args) {
                return '';
            });
        });
        $twig->registerUndefinedFilterCallback(function ($name) {
            return new TwigFilter($name, static function (...$args) {
                return '';
            });
        });

        try {
            $twig->parse(
                $twig->tokenize(
                    new Source(
                        $content,
                        $path ?? '',
                    )
                )
            );
        } catch (SyntaxError $e) {
            return [$e];
        }

        return [];
    }
}
