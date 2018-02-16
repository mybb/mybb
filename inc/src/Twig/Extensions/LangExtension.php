<?php

namespace MyBB\Twig\Extensions;

/**
 * A Twig extension class to provide functionality related to translations.
 */
class LangExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
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
    public function __construct(\MyLanguage $lang)
    {
        $this->lang = $lang;
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('trans', [$this, 'trans'], [
                'is_safe' => ['html']
            ]),
        ];
    }

    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    public function getGlobals()
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
}