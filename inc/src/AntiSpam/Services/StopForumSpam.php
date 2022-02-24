<?php

declare(strict_types=1);

namespace MyBB\AntiSpam\Services;

use MyBB;
use MyBB\AntiSpam\AntiSpamServiceInterface;
use MyBB\AntiSpam\CheckResult;
use MyBB\Plugins\HookManager;

class StopForumSpam implements AntiSpamServiceInterface
{
    private const URL = 'https://api.stopforumspam.org/api?confidence&json';

    /**
     * @var \MyBB
     */
    private $mybb;

    /**
     * @var \MyBB\Plugins\HookManager
     */
    private $plugins;

    public function __construct(MyBB $mybb, HookManager $plugins)
    {
        $this->mybb = $mybb;
        $this->plugins = $plugins;
    }

    /**
     * @inheritDoc
     */
    public function checkUser(string $username, string $emailAddress, string $ipAddress): CheckResult
    {
        $data = [];

        $result = new CheckResult();

        if ($this->mybb->settings['stopforumspam_min_username_weighting_before_spam'] > 0 &&
            !is_null($username) && $username !== '') {
            $data['username'] = urlencode($username);
        }

        if ($this->mybb->settings['stopforumspam_min_email_weighting_before_spam'] > 0 &&
            !is_null($emailAddress) && $emailAddress !== '') {
            $data['email'] = urlencode($emailAddress);
        }

        if ($this->mybb->settings['stopforumspam_min_ip_address_weighting_before_spam'] > 0 &&
            !is_null($ipAddress) && $ipAddress !== '') {
            $isInternalIpAddress = !filter_var(
                $ipAddress,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE |  FILTER_FLAG_NO_RES_RANGE
            );

            if ($isInternalIpAddress) {
                $result->setResult(CheckResult::PASS);

                return $result;
            }

            $data['ip'] = urlencode($ipAddress);
        }

        if (empty($data)) {
            $result->setResult(CheckResult::PASS);

            return $result;
        }

        $url = static::URL . '&' . http_build_query($data);

        $response = fetch_remote_file($url);

        if ($response === null) {
            $result->setResult(CheckResult::ERROR);
            $result->setError('no_response');

            return $result;
        }

        $responseJson = @json_decode($response);

        if (is_null($responseJson)) {
            $result->setResult(CheckResult::ERROR);
            $result->setError(@json_last_error_msg());

            return $result;
        }

        if (isset($responseJson->error)) {
            $result->setResult(CheckResult::ERROR);
            $result->setError($responseJson->error);

            return $result;
        }

        if (isset($data['username']) && $responseJson->username->appears) {
            $usernameConfidence = (float) $responseJson->username->confidence;

            if ($usernameConfidence >= $this->mybb->settings['stopforumspam_min_username_weighting_before_spam']) {
                $result->setResult(CheckResult::FAIL);
            }

            $result->setUsernameConfidence($usernameConfidence);
        }

        if (isset($data['email']) && $responseJson->email->appears) {
            $emailConfidence = (float) $responseJson->email->confidence;

            if ($emailConfidence >= $this->mybb->settings['stopforumspam_min_email_weighting_before_spam']) {
                $result->setResult(CheckResult::FAIL);
            }

            $result->setEmailAddressConfidence($emailConfidence);
        }

        if (isset($data['ip']) && $responseJson->ip->appears) {
            $ipConfidence = (float) $responseJson->ip->confidence;

            if ($ipConfidence >= $this->mybb->settings['stopforumspam_min_ip_address_weighting_before_spam']) {
                $result->setResult(CheckResult::FAIL);
            }

            $result->setIpAddressConfidence($ipConfidence);
        }

        $averageConfidence = $result->getAverageConfidence();

        if ($averageConfidence >= $this->mybb->settings['stopforumspam_min_weighting_before_spam']) {
            $result->setResult(CheckResult::FAIL);
        }

        $this->plugins->runHooks('stopforumspam_check_pre_return', $result);

        return $result;
    }
}
