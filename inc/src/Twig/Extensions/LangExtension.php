<?php

namespace MyBB\Twig\Extensions;

use MyLanguage;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * A Twig extension class to provide functionality related to translations.
 */
class LangExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var \MyLanguage $lang
     */
    private $lang;

    /**
     * Create a new instance of the LangExtension.
     *
     * @param \MyLanguage $lang
     */
    public function __construct(MyLanguage $lang)
    {
        $this->lang = $lang;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('trans', [$this, 'trans'], [
                'is_safe' => ['html']
            ]),
        ];
    }

    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    public function getGlobals(): array
    {
        return [
            'lang' => $this->lang,
        ];
    }

    /**
     * Get a translation that takes a number of parameters.
     *
     * @param string $languageVariable The name of the language variable to translate.
     * @param array ...$params A list of parameters to inject into the translation.
     *
     * @return string The resolved translation string.
     */
    public function trans(string $languageVariable, ...$params) : string
    {
        return $this->lang->sprintf($this->lang->$languageVariable, ...$params);
    }

    /**
     * Report any custom filters
     *
     * @return array Twig\TwigFilter
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('lparse', array($this, 'lparseFilter')),
        );
    }

    /**
     * Language parse.
     *
     * @param string
     *
     * @return string Parsed language string
     */
    public function lparseFilter(string $contents)
    {
        $contents = preg_replace_callback("#<lang:([a-zA-Z0-9_]+)>#", array($this, 'lparseReplace'), $contents);
        return $contents;
    }

    /**
     * Callback for LangExtension::lparseFilter
     *
     * @param array
     *
     * @return string
     */
    public function lparseReplace(array $matches)
    {
        return $this->lang->{$matches[1]};
    }
}
