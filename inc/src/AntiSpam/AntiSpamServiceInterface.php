<?php

declare(strict_types=1);

namespace MyBB\AntiSpam;

/**
 * Interface to check content against an anti-spam service.
 */
interface AntiSpamServiceInterface
{
    /**
     * Check whether a user is a spammer according to the anti-spam service.
     *
     * @param string $username The username of the user to check.
     * @param string $emailAddress The email address of the user to check.
     * @param string $ipAddress The IP address of the user to check.
     *
     * @return \MyBB\AntiSpam\CheckResult The result of the check.
     */
    public function checkUser(string $username, string $emailAddress, string $ipAddress): CheckResult;
}
