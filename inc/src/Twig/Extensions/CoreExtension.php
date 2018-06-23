<?php

namespace MyBB\Twig\Extensions;

class CoreExtension extends \Twig_Extension
{
    /**
     * @var \MyBB $mybb
     */
    private $mybb;

    /**
     * @var \MyLanguage $lang
     */
    private $lang;

    /**
     * @var \pluginSystem $plugins
     */
    private $plugins;

    public function __construct(\MyBB $mybb, \MyLanguage $lang, \pluginSystem $plugins)
    {
        $this->mybb = $mybb;
        $this->lang = $lang;
        $this->plugins = $plugins;
    }

    public function getFilters()
    {
        return [
            new \Twig_Filter('my_date', [$this, 'myDate'], [
                'needs_environment' => true,
                'is_safe' => ['html'],
            ]),
        ];
    }

    /**
     * Format a timestamp to a readable value.
     *
     * @param \Twig_Environment $environment twig environment to use to render the timestamp template.
     * @param \DateTime|int|string|null $timestamp The timestamp to format. If empty, the current date will be used.
     * @param string $format The format to use when formatting the timestamp. Defaults to 'relative'.
     * @param string $offset The offset to use when formatting the timestamp.
     * Defaults to using the user's settings or the board's settings if the user doesn't have a preference set.
     * @param bool $useRelativeFormatting Whether to use relative formatting for the day: 'today' and 'yesterday'.
     * Defaults to true.
     *
     * @return string The formatted timestamp, using the `partials/time.twig` template to wrap the timestamp.
     *
     * @throws \Twig_Error_Loader Thrown if the `partials/time.twig` template could not be loaded.
     * @throws \Twig_Error_Runtime Thrown if an error occurs when rendering the `partials/time.twig` template.
     * @throws \Twig_Error_Syntax Thrown if the `partials/time.twig` template contains invalid syntax.
     */
    public function myDate(
        \Twig_Environment $environment,
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
                            $formattedDateString = $this->lang->yeserday;
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

        return $environment->render('partials/time.twig', [
            'iso_date' => $dateTime->format(\DateTime::RFC3339),
            'formatted_date' => $formattedDateString,
            'formatted_using_settings' => $formattedUsingSettings,
        ]);
    }
}
