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
}
