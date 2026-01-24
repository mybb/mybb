<?php

namespace MyBB\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UserExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_name', [$this, 'getFormattedName'], [
                'is_safe' => ['html'],
            ]),
            new TwigFilter('is_member', [$this, 'isMember']),
        ];
    }

    /**
     * Returns the formatted username for a user.
     *
     * @param int|array $user Either a user ID or a user data array.
     * @return string Formatted username.
     */
    public function getFormattedName(int|array $user): string
    {
        if (is_int($user)) {
            $user = get_user($user);
        }

        if (empty($user['username'])) {
            return '';
        }

        return format_name(htmlspecialchars_uni($user['username']), $user['usergroup'] ?? 0, $user['displaygroup'] ?? 0);
    }

    /**
     * Check if a user is a member of the specified group(s).
     *
     * @param array<int> $groups Array of group IDs to check membership for. Pass [-1] to check if user is in any group.
     * @param int|array|null $user Either a user ID, user data array, or null for the current user.
     * @return bool True if the user is a member of any of the specified groups.
     */
    public function isMember(array $groups, int|array|null $user = null): bool
    {
        if ($user === null) {
            return (bool)is_member($groups);
        }
        return (bool)is_member($groups, $user);
    }
}
