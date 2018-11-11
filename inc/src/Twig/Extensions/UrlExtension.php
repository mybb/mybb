<?php

namespace MyBB\Twig\Extensions;

class UrlExtension extends \Twig_Extension
{
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_profile_link', [$this, 'getProfileLink']),
            new \Twig_SimpleFunction('get_announcement_link', [$this, 'getAnnouncementLink']),
            new \Twig_SimpleFunction('get_forum_link', [$this, 'getForumLink']),
            new \Twig_SimpleFunction('get_thread_link', [$this, 'getThreadLink']),
            new \Twig_SimpleFunction('get_post_link', [$this, 'getPostLink']),
            new \Twig_SimpleFunction('get_event_link', [$this, 'getEventLink']),
            new \Twig_SimpleFunction('get_calendar_link', [$this, 'getCalendarLink']),
            new \Twig_SimpleFunction('get_calendar_week_link', [$this, 'getCalendarWeekLink']),
        ];
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
     *
     * @return string The URL path to the post.
     */
    public function getPostLink(int $postId, int $threadId = 0): string
    {
        return get_post_link($postId, $threadId);
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
