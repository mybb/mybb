<?php

declare(strict_types=1);

namespace MyBB\Twig\Inspection;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Loader\LoaderInterface;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Calls given Inspections by visiting Twig Nodes recursively.
 */
class Inspector
{
    private Environment $twig;

    public function __construct(LoaderInterface $loader)
    {
        $this->twig = new Environment($loader);

        // prevent hard errors for utilities unknown to the Environment
        $this->twig->registerUndefinedFunctionCallback(static function ($name) {
            return new TwigFunction($name, static function (...$args) {
                return '';
            });
        });
        $this->twig->registerUndefinedFilterCallback(static function ($name) {
            return new TwigFilter($name, static function (...$args) {
                return '';
            });
        });
    }

    public function addInspection(NodeVisitorInterface $visitor): void
    {
        $this->twig->addNodeVisitor($visitor);
    }

    /**
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function inspectTemplate(string $name): void
    {
        $this->twig->parse(
            $this->twig->tokenize(
                $this->twig->getLoader()->getSourceContext($name)
            )
        );
    }
}
