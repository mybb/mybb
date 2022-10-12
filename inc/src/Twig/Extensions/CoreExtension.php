<?php

namespace MyBB\Twig\Extensions;

use MyBB;
use MyBB\Utilities\BreadcrumbManager;
use MyLanguage;
use pluginSystem;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CoreExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var \MyBB $mybb
     */
    protected $mybb;

    /**
     * @var \MyLanguage $lang
     */
    protected $lang;

    /**
     * @var ?\pluginSystem $plugins
     */
    protected $plugins;

    /**
     * @var BreadcrumbManager $breadcrumbManager
     */
    protected $breadcrumbManager;

    public function __construct(
        MyBB $mybb,
        MyLanguage $lang,
        ?pluginSystem $plugins,
        BreadcrumbManager $breadcrumbManager
    ) {
        $this->mybb = $mybb;
        $this->lang = $lang;
        $this->plugins = $plugins;
        $this->breadcrumbManager = $breadcrumbManager;
    }

    /**
     * Returns a list of global variables to add to the existing list.
     *
     * @return array An array of global variables
     */
    public function getGlobals(): array
    {
        global $jsTemplates;

        return [
            'mybb' => $this->mybb,
            'jsTemplates' => $jsTemplates,
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('my_date', [$this, 'date'], [
                'needs_environment' => true,
                'is_safe' => ['html'],
            ]),
            new TwigFilter('my_number_format', [$this, 'numberFormat']),
            new TwigFilter('remove_page_one', [$this, 'removePageOne']),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction(
                'build_breadcrumb_navigation',
                [$this, 'buildBreadcrumbNavigation'],
                [
                    'needs_environment' => true,
                    'is_safe' => ['html'],
                ]
            ),
            new TwigFunction(
                'multi_page',
                [$this, 'buildMultiPage'],
                [
                    'needs_environment' => true,
                    'is_safe' => ['html'],
                ]
            ),
            new TwigFunction(
                'render_avatar',
                [$this, 'renderAvatar'],
                [
                    'needs_environment' => true,
                    'is_safe' => ['html'],
                ]
            ),
            new TwigFunction(
                'include',
                [$this, 'twig_include'],
                [
                    'needs_environment' => true,
                    'needs_context' => true,
                    'is_safe' => ['all']
                ]
            ),
        ];
    }

    function twig_include(Environment $env, $context, $template, $variables = [], $withContext = true, $ignoreMissing = false, $sandboxed = false)
    {
        global $plugins;

        $name = false;
        if (is_array($template)) {
            foreach ($template as $tpl) {
                if ($env->getLoader()->exists($tpl)) {
                    $name = $tpl;
                    break;
                }
            }
        } else {
            $name = $template;
        }
        if ($name) {
            // Note that unlike the related `template` hook in `inc/src/functions.php`,
            // $name and $variables are not passed by reference here, because changing
            // either has no effect anyway.
            $params = ['name' => $name, 'variables' => $variables];
            $plugins->run_hooks('template_include', $params);
        }
    }

    /**
     * Format a timestamp to a readable value.
     *
     * @param \Twig\Environment $environment Twig environment to use to render the timestamp template.
     * @param \DateTime|int|string|null $timestamp The timestamp to format. If empty, the current date will be used.
     * @param string $format The format to use when formatting the timestamp. Defaults to 'relative'.
     * @param string $offset The offset to use when formatting the timestamp.
     * Defaults to using the user's settings or the board's settings if the user doesn't have a preference set.
     * @param bool $useRelativeFormatting Whether to use relative formatting for the day: 'today' and 'yesterday'.
     * Defaults to true.
     *
     * @return string The formatted timestamp, using the `partials/time.twig` template to wrap the timestamp.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function date(
        Environment $environment,
        $timestamp,
        string $format = 'relative',
        string $offset = '',
        bool $useRelativeFormatting = true
    ): string {
        if (is_numeric($timestamp)) {
            // timestamp is a numeric timestamp
            $dateTime = (new \DateTime())->setTimestamp($timestamp);
        } elseif (is_string($timestamp)) {
            // timestamp string
            $dateTime = new \DateTime($timestamp);
        } elseif ($timestamp instanceof \DateTime) {
            // timestamp is already a DateTime
            $dateTime = $timestamp;
        } else {
            // no valid timestamp passed, so use the current date time
            $dateTime = new \DateTime();
        }

        if (!$offset && $offset != '0') {
            if (isset($this->mybb->user['uid']) && $this->mybb->user['uid'] != 0 &&
                isset($this->mybb->user['timezone'])) {
                $offset = $this->mybb->user['timezone'];
                $dstCorrection = (bool)$this->mybb->user['dst'];
            } elseif (defined("IN_ADMINCP")) {
                global $mybbadmin;
                $offset = $mybbadmin['timezone'];
                $dstCorrection = (bool)$mybbadmin['dst'];
            } else {
                $offset = $this->mybb->settings['timezoneoffset'];
                $dstCorrection = (bool)$this->mybb->settings['dstcorrection'];
            }

            if (is_numeric($offset)) {
                // Support both numeric timezones, and string timezones such as 'Europe/London'.
                $offset = (float)$offset;
            }

            // If DST correction is enabled, add an additional hour to the timezone.
            if ($dstCorrection && is_float($offset)) {
                ++$offset;
            }
        }

        if ($offset == '-') {
            $offset = 0.0;
        }

        // Offset is a float, so now convert it into hour representation. Example: +9.5 becomes +09:30.
        if (is_numeric($offset)) {
            $offset = (float)$offset;
            $hours = floor($offset);
            $minutes = ($offset - $hours) * 60;

            $offset = sprintf("%+'.02d:%'.02d", $hours, $minutes);
        }

        // \DateTimeZone supports both string style timezone specifiers, such as 'Europe/London' and time style.
        $timezone = new \DateTimeZone($offset);
        $dateTime->setTimezone($timezone);

        // The date portion of the date time, with the time being 00:00:00
        $date = \DateTime::createFromFormat('!Y-m-d', $dateTime->format('Y-m-d'), $timezone);

        /** @var ?\DateTime $dateToday */
        /** @var ?\DateTime $dateYesterday */
        $dateToday = $dateYesterday = null;
        if ($useRelativeFormatting &&
            ($format == $this->mybb->settings['dateformat'] || $format == 'relative' || $format == 'normal')) {
            $currentDateTime = new \DateTime('now', $timezone);

            $dateToday = \DateTime::createFromFormat('!Y-m-d', $currentDateTime->format('Y-m-d'), $timezone);

            // clone dateToday, rather than copy reference as datetime methods apply modifications to the instance
            $dateYesterday = clone $dateToday;
            $dateYesterday->modify('-1 day');
        }

        // Format the time using the setting values, to be used in the title
        $formattedUsingSettings = $dateTime->format($this->mybb->settings['dateformat']);
        $formattedUsingSettings .= $this->mybb->settings['datetimesep'];
        $formattedUsingSettings .= $dateTime->format($this->mybb->settings['timeformat']);

        $formattedDateString = $formattedUsingSettings;

        switch ($format) {
            case 'relative':
                // Get the difference between now and the timestamp, in seconds
                $diff = (new \DateTime('now', $timezone))->getTimestamp() - $dateTime->getTimestamp();

                $relative = [
                    'prefix' => '',
                    'suffix' => '',
                ];

                if ($useRelativeFormatting && abs($diff) < 3600) {
                    // less than an hour ago
                    if ($diff < 0) {
                        $diff = abs($diff);
                    } else {
                        $relative['suffix'] = $this->lang->rel_ago;
                    }

                    $relative['minute'] = floor($diff / 60);

                    if ($relative['minute'] <= 1) {
                        $relative['minute'] = 1;
                        $relative['plural'] = $this->lang->rel_minutes_single;
                    } else {
                        $relative['plural'] = $this->lang->rel_minutes_plural;
                    }

                    if ($diff < 60) {
                        // less than a minute
                        $relative['prefix'] = $this->lang->rel_less_than;
                    }

                    $formattedDateString = $this->lang->sprintf(
                        $this->lang->rel_time,
                        $relative['prefix'],
                        $relative['minute'],
                        $relative['plural'],
                        $relative['suffix']
                    );
                } elseif ($useRelativeFormatting && abs($diff) < 43200) {
                    // less than 12 hours ago
                    if ($diff < 0) {
                        $diff = abs($diff);
                        $relative['prefix'] = $this->lang->rel_in;
                    } else {
                        $relative['suffix'] = $this->lang->rel_ago;
                    }

                    $relative['hour'] = floor($diff / 3600);

                    if ($relative['hour'] <= 1) {
                        $relative['hour'] = 1;
                        $relative['plural'] = $this->lang->rel_hours_single;
                    } else {
                        $relative['plural'] = $this->lang->rel_hours_plural;
                    }

                    $formattedDateString = $this->lang->sprintf(
                        $this->lang->rel_time,
                        $relative['prefix'],
                        $relative['hour'],
                        $relative['plural'],
                        $relative['suffix']
                    );
                } else {
                    $formattedDateString = $date->format($this->mybb->settings['dateformat']);
                    if ($useRelativeFormatting) {
                        if ($dateToday == $date) {
                            $formattedDateString = $this->lang->today;
                        } elseif ($dateYesterday == $date) {
                            $formattedDateString = $this->lang->yesterday;
                        }
                    }

                    $formattedDateString .= $this->mybb->settings['datetimesep'];
                    $formattedDateString .= $dateTime->format($this->mybb->settings['timeformat']);
                }
                break;
            case 'normal':
                // Normal format both date and time
                $formattedDateString = $dateTime->format($this->mybb->settings['dateformat']);

                if ($useRelativeFormatting) {
                    if ($dateToday == $date) {
                        $formattedDateString = $this->lang->today;
                    } elseif ($dateYesterday == $date) {
                        $formattedDateString = $this->lang->yesterday;
                    }
                }

                $formattedDateString .= $this->mybb->settings['datetimesep'];
                $formattedDateString .= $dateTime->format($this->mybb->settings['timeformat']);
                break;
            default:
                if ($useRelativeFormatting && $format == $this->mybb->settings['dateformat']) {
                    if ($dateToday == $date) {
                        $formattedDateString = $this->lang->today;
                    } elseif ($dateYesterday == $date) {
                        $formattedDateString = $this->lang->yesterday;
                    }
                } else {
                    $formattedDateString = $dateTime->format($format);
                }
                break;
        }

        if (!is_null($this->plugins)) {
            $args = [
                'dateTime' => &$dateTime,
                'formattedDateString' => &$formattedDateString,
                'formattedUsingSettings' => &$formattedUsingSettings,
            ];

            $this->plugins->run_hooks('my_date', $args);
        }

        return $environment->render('partials/time.twig', [
            'iso_date' => $dateTime->format(\DateTime::RFC3339),
            'formatted_date' => $formattedDateString,
            'formatted_using_settings' => $formattedUsingSettings,
        ]);
    }

    /**
     * Format a number according to settings.
     *
     * @param mixed $number The number to format.
     *
     * @return string The formatted numerical value.
     */
    public function numberFormat($number): string
    {
        if ($number == '-') {
            return $number;
        }

        if (is_int($number)) {
            return number_format(
                $number,
                0,
                $this->mybb->settings['decpoint'],
                $this->mybb->settings['thousandssep']
            );
        } else {
            $parts = explode('.', $number, 2);

            if (count($parts) == 2) {
                $decimals = my_strlen($parts[1]);
            } else {
                $decimals = 0;
            }

            return number_format(
                (double)$number,
                $decimals,
                $this->mybb->settings['decpoint'],
                $this->mybb->settings['thousandssep']
            );
        }
    }

    /**
     * Build the breadcrumb navigation.
     *
     * @param \Twig\Environment $twig Twig environment to use to render the breadcrumb template.
     *
     * @return string The formatted breadcrumb navigation trail.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function buildBreadcrumbNavigation(Environment $twig): string
    {
        return $twig->render('partials/breadcrumb.twig', [
            'breadcrumbs' => $this->breadcrumbManager,
        ]);
    }

    /**
     * If $url has a page parameter and the page is page 1, remove the page parameter.
     *
     * @param string $url The URL to check.
     *
     * @return string The cleaned URL.
     */
    public function removePageOne(string $url): string
    {
        $url = str_replace('-page-1.html', '.html', $url);
        $url = preg_replace('/&amp;page=1$/', '', $url);

        return $url;
    }

    /**
     * Generate a listing of pages for a resource.
     *
     * @param \Twig\Environment $twig Twig environment to use to render the pagination template.
     * @param int $count The total number of items.
     * @param int $perPage The number of items to be shown per page.
     * @param int $page The current page number.
     * @param string $url The URL format to use for page links.
     * If {page} is specified, the value will be replaced with the page #.
     * @param boolean $breadcrumb Whether or not the multipage is being shown in the navigation breadcrumb.
     *
     * @return string The generated pagination links.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function buildMultiPage(
        Environment $twig,
        int $count,
        int $perPage,
        int $page,
        string $url,
        bool $breadcrumb = false
    ): string {
        if ($count <= $perPage) {
            return '';
        }

        $url = str_replace("&amp;", "&", $url);
        $url = htmlspecialchars_uni($url);

        $numPages = ceil($count / $perPage);
        $multiPage = [];


        if ($page > 1) {
            $prev = $page - 1;
            $multiPage['previous_page_url'] = fetch_page_url($url, $prev);
        }

        // Maximum number of "page bits" to show
        if (!$this->mybb->settings['maxmultipagelinks']) {
            $this->mybb->settings['maxmultipagelinks'] = 5;
        }

        $from = $page - floor($this->mybb->settings['maxmultipagelinks'] / 2);
        $to = $page + floor($this->mybb->settings['maxmultipagelinks'] / 2);

        if ($from <= 0) {
            $from = 1;
            $to = $from + $this->mybb->settings['maxmultipagelinks'] - 1;
        }

        if ($to > $numPages) {
            $to = $numPages;
            $from = $numPages - $this->mybb->settings['maxmultipagelinks']+1;
            if ($from <= 0) {
                $from = 1;
            }
        }

        if ($to == 0) {
            $to = $numPages;
        }

        $multiPage['from'] = $from;
        $multiPage['to'] = $to;
        $multiPage['total_pages'] = $numPages;

        if ($from > 1) {
            if ($from - 1 == 1) {
                $this->lang->multipage_link_start = '';
            }

            $multiPage['start_page_url'] = fetch_page_url($url, 1);
        }

        $multiPage['pages'] = [];
        for ($pageNum = $from; $pageNum <= $to; ++$pageNum) {
            $pageFor['num'] = $pageNum;
            $pageFor['page_url'] = fetch_page_url($url, $pageNum);

            $multiPage['pages'][] = $pageFor;
        }

        if ($to < $numPages) {
            if ($to + 1 == $numPages) {
                $this->lang->multipage_link_end = '';
            }

            $multiPage['end_page_url'] = fetch_page_url($url, $numPages);
        }

        if ($page < $numPages) {
            $next = $page + 1;
            $multiPage['next_page_url'] = fetch_page_url($url, $next);
        }

        if ($breadcrumb == false &&
            $numPages > ($this->mybb->settings['maxmultipagelinks'] + 1) &&
            $this->mybb->settings['jumptopagemultipage'] == 1) {
            // When the 2nd parameter is set to 1, fetch_page_url thinks it's the first page and removes it from the
            // URL as it's unnecessary
            $multiPage['jump_url'] = fetch_page_url($url, 1);
        }

        return $twig->render('partials/multipage.twig', [
            'multipage' => $multiPage,
            'page' => $page,
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Render the given avatar using the avatar template.
     *
     * @param \Twig\Environment $twig Twig environment to use to render the pagination template.
     * @param string|null $url The URL to the avatar to render, or null for a default avatar.
     * @param string|null $alt The alternative text to use for the avatar.
     * @param string|null $class An optional CSS class or list of CSS classes to apply to the avatar template.
     *
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderAvatar(
        Environment $twig,
        ?string $url = '',
        ?string $alt = '',
        ?string $class = ''
    ): string {
        $url = trim($url);
        $alt = trim($alt);
        $class = trim($class);

        if (empty($url)) {
            return $twig->render('partials/default_avatar.twig', [
                'class' => $class,
            ]);
        }

        return $twig->render('partials/avatar.twig', [
            'url' => $url,
            'alt' => $alt,
            'class' => $class,
        ]);
    }
}
