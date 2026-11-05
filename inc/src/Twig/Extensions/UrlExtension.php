<?php

namespace MyBB\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UrlExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('path', [$this, 'path']),
            new TwigFunction('url', [$this, 'url']),
            new TwigFunction('url_from', [$this, 'urlFrom']),
            new TwigFunction('get_profile_link', [$this, 'getProfileLink']),
            new TwigFunction('get_announcement_link', [$this, 'getAnnouncementLink']),
            new TwigFunction('get_forum_link', [$this, 'getForumLink']),
            new TwigFunction('get_thread_link', [$this, 'getThreadLink']),
            new TwigFunction('get_post_link', [$this, 'getPostLink']),
            new TwigFunction('get_event_link', [$this, 'getEventLink']),
            new TwigFunction('get_calendar_link', [$this, 'getCalendarLink']),
            new TwigFunction('get_calendar_week_link', [$this, 'getCalendarWeekLink']),
        ];
    }

    /**
     * Build a relative path by appending query parameters to a script or URL.
     *
     * @param string $script Base script or URL.
     * @param array $params Query parameters to append or override.
     * @param string|null $fragment Optional fragment identifier.
     *
     * @return string The resulting relative path.
     */
    public function path(string $script, array $params = [], ?string $fragment = null): string
    {
        return $this->build($script, $params, $fragment, false);
    }

    /**
     * Build an absolute URL by appending query parameters to a script or URL.
     *
     * @param string $script Base script or URL.
     * @param array $params Query parameters to append or override.
     * @param string|null $fragment Optional fragment identifier.
     *
     * @return string The resulting absolute URL.
     */
    public function url(string $script, array $params = [], ?string $fragment = null): string
    {
        return $this->build($script, $params, $fragment, true, 'bburl');
    }

    /**
     * Build an absolute URL by appending query parameters to a script or URL using a configured base setting.
     *
     * @param string $baseSetting Setting name to use as URL base.
     * @param string $script Base script or URL.
     * @param array $params Query parameters to append or override.
     * @param string|null $fragment Optional fragment identifier.
     *
     * @return string The resulting absolute URL.
     */
    public function urlFrom(string $baseSetting, string $script, array $params = [], ?string $fragment = null): string
    {
        return $this->build($script, $params, $fragment, true, $baseSetting);
    }

    /**
     * Internal URL builder.
     *
     * @param string $script Base script or URL.
     * @param array $params Query parameters to append or override.
     * @param string|null $fragment Optional fragment identifier.
     * @param bool $absolute Whether to return an absolute URL.
     * @param string $baseSetting Setting name to use as URL base when $absolute is true.
     *
     * @return string The resulting URL or path.
     */
    private function build(string $script, array $params, ?string $fragment, bool $absolute, string $baseSetting = 'bburl'): string
    {
        if ($absolute) {
            $hasScheme = (bool) parse_url($script, PHP_URL_SCHEME);
            $isProtocolRelative = str_starts_with($script, '//');

            if (!$hasScheme && !$isProtocolRelative) {
                global $mybb;

                $baseUrl = $mybb->settings[$baseSetting] ?? '';
                if (!is_string($baseUrl) || $baseUrl === '' || !my_validate_url($baseUrl, false, true)) {
                    $baseUrl = $mybb->settings['bburl'] ?? '';
                }

                $script = rtrim($baseUrl, '/') . '/' . ltrim($script, '/');
            }
        }

        $existingFragment = null;
        if (str_contains($script, '#')) {
            [$script, $existingFragment] = explode('#', $script, 2);
        }

        $base = $script;
        $existingQuery = '';
        if (str_contains($script, '?')) {
            [$base, $existingQuery] = explode('?', $script, 2);
        }

        $existingParams = [];
        if ($existingQuery !== '') {
            parse_str(str_replace('&amp;', '&', $existingQuery), $existingParams);
        }

        $merged = array_merge($existingParams, $params);
        $query = $merged !== [] ? http_build_query($merged, '', '&') : '';

        $out = $base;
        if ($query !== '') {
            $out .= '?' . $query;
        }

        $finalFragment = $fragment ?? $existingFragment;
        if ($finalFragment !== null && $finalFragment !== '') {
            $out .= '#' . $finalFragment;
        }

        return $out;
    }

    /**
     * Get the URL to view the given user's profile.
     *
     * @param int $userId The user's ID.
     *
     * @return string The URL path to the user's profile.
     */
    public function getProfileLink(int $userId): string
    {
        return get_profile_link($userId);
    }

    /**
     * Get the URL to view the given announcement,
     *
     * @param int $announcementId The announcement's ID.
     *
     * @return string The URL path to the announcement.
     */
    public function getAnnouncementLink(int $announcementId): string
    {
        return get_announcement_link($announcementId);
    }

    /**
     * Get the URL to view the given forum.
     *
     * @param int $forumId The forum's ID.
     * @param int $page An optional page number for the forum, if you want to link to a specific page.
     *
     * @return string The URL path to the forum.
     */
    public function getForumLink(int $forumId, int $page = 0): string
    {
        return get_forum_link($forumId, $page);
    }

    /**
     * Get the URL to view the given thread.
     *
     * @param int $threadId The thread's ID.
     * @param int $page An optional page number for the thread, if you want to link to a specific page.
     * @param string $action An optional action, such as 'lastpost', 'newpost', etc.
     *
     * @return string The URL path to the thread.
     */
    public function getThreadLink(int $threadId, int $page = 0, string $action = ''): string
    {
        return get_thread_link($threadId, $page, $action);
    }

    /**
     * Get the URL to view the given post.
     *
     * @param int $postId The post's ID.
     * @param int $threadId An optional thread ID that the post belongs to.
     * @param string $action An optional action.
     *
     * @return string The URL path to the post.
     */
    public function getPostLink(int $postId, int $threadId = 0, string $action = ''): string
    {
        return get_post_link($postId, $threadId, $action);
    }

    /**
     * Get the URL to view the given calendar event.
     *
     * @param int $eventId The event's ID.
     *
     * @return string The URL to the event.
     */
    public function getEventLink(int $eventId): string
    {
        return get_event_link($eventId);
    }

    /**
     * Get the URL to view the given calendar.
     *
     * @param int $calendarId The calendar's ID.
     * @param int $year The year to open the calendar to.
     * @param int $month The month to open the calendar to.
     * @param int $day The day to open the calendar to.
     *
     * @return string The URL to the calendar.
     */
    public function getCalendarLink(int $calendarId, int $year = 0, int $month = 0, int $day = 0): string
    {
        return get_calendar_link($calendarId, $year, $month, $day);
    }

    /**
     * Get the URL to view the given week within the given calendar.
     *
     * @param int $calendarId The calendar's ID.
     * @param int $week The number of the week.
     *
     * @return string The URL to the week within the calendar.
     */
    public function getCalendarWeekLink(int $calendarId, int $week): string
    {
        return get_calendar_week_link($calendarId, $week);
    }
}
