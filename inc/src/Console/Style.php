<?php

declare(strict_types=1);

namespace MyBB\Console;

use MyBB\Extensions\Extension;
use MyBB\Extensions\Plugin\Plugin;
use MyBB\Extensions\Theme\Theme;
use MyBB\Extensions\Theme\ThemeType;
use MyBB\View\Locator\Exception as LocatorException;
use MyBB\View\Locator\Locator;
use MyBB\View\Locator\ViewletLocator;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

class Style extends SymfonyStyle
{
    /**
     * @param string[] $items
     */
    public static function inlineListing(array $items): string
    {
        return implode(
            ' <minor>·</minor> ',
            $items,
        );
    }

    public static function filesystemPath(string $path): string
    {
        $applicationAbsolutePath = Path::canonicalize(MYBB_ROOT);

        if (Path::isBasePath($applicationAbsolutePath, $path)) {
            $relativePath = Path::makeRelative($path, $applicationAbsolutePath);

            return
                '<base-path>' .  OutputFormatter::escape($applicationAbsolutePath) . '/' . '</base-path>' .
                '<path>' . OutputFormatter::escape($relativePath) . '</path>';
        } else {
            return OutputFormatter::escape($path);
        }
    }

    public static function url(string $url): string
    {
        $urlEscaped = OutputFormatter::escape($url);

        return '<href="' . $urlEscaped . '">' . $urlEscaped . '</>';
    }

    /**
     * @throws LocatorException
     */
    public static function locator(Locator $locator): string
    {
        if ($locator instanceof ViewletLocator) {
            $decoratedString = OutputFormatter::escape($locator->getString());

            $prefix =
                ViewletLocator::NAMESPACE_PREFIX .
                $locator->getNamespace() .
                ViewletLocator::DIRECTORY_SEPARATOR .
                $locator->getType()->getDirectoryName() .
                ViewletLocator::DIRECTORY_SEPARATOR;

            $prefixEscaped = OutputFormatter::escape($prefix);

            if (str_starts_with($decoratedString, $prefixEscaped)) {
                $decoratedString = substr_replace(
                    $decoratedString,
                    '<path>' . $prefixEscaped . '</path>',
                    0,
                    strlen($prefixEscaped),
                );
            }

            return $decoratedString;
        } else {
            return '<base-path>' . OutputFormatter::escape($locator->getString()) . '</base-path>';
        }
    }

    public static function extensionStyle(?Extension $extension): string
    {
        if ($extension === null) {
            return 'minor';
        }

        return match ($extension::class) {
            Plugin::class => 'plugin',
            Theme::class => match ($extension->getType()) {
                ThemeType::CORE => 'core',
                ThemeType::ORIGINAL => 'extension',
                ThemeType::BOARD => 'generic',
            },
            default => 'minor',
        };
    }

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        parent::__construct($input, $output);

        $formatter = $output->getFormatter();

        // structural elements
        $formatter->setStyle(
            'block-title',
            new OutputFormatterStyle(options: ['bold']),
        );

        // generic elements
        $formatter->setStyle(
            'key',
            new OutputFormatterStyle(options: ['bold']),
        );
        $formatter->setStyle(
            'value',
            new OutputFormatterStyle(),
        );
        $formatter->setStyle(
            'base-path',
            new OutputFormatterStyle('green'),
        );
        $formatter->setStyle(
            'path',
            new OutputFormatterStyle('bright-green'),
        );
        $formatter->setStyle(
            'code',
            new OutputFormatterStyle('green'),
        );

        // generic semantics
        $formatter->setStyle(
            'minor',
            new OutputFormatterStyle('gray'),
        );
        $formatter->setStyle(
            'neutral',
            new OutputFormatterStyle('gray'),
        );
        $formatter->setStyle(
            'negative',
            new OutputFormatterStyle('bright-red'),
        );
        $formatter->setStyle(
            'positive',
            new OutputFormatterStyle('bright-green'),
        );
        $formatter->setStyle(
            'signal',
            new OutputFormatterStyle('cyan'),
        );
        $formatter->setStyle(
            'warning',
            new OutputFormatterStyle('yellow'),
        );

        // entity semantics
        $formatter->setStyle(
            'generic',
            new OutputFormatterStyle('white'),
        );
        $formatter->setStyle(
            'core',
            new OutputFormatterStyle('bright-blue'),
        );
        $formatter->setStyle(
            'extension',
            new OutputFormatterStyle('bright-magenta'),
        );
        $formatter->setStyle(
            'plugin',
            new OutputFormatterStyle('bright-yellow'),
        );
        $formatter->setStyle(
            'theme-model',
            new OutputFormatterStyle('bright-cyan'),
        );
    }
}
