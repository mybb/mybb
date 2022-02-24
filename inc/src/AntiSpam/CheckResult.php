<?php

declare(strict_types=1);

namespace MyBB\AntiSpam;

use InvalidArgumentException;

class CheckResult
{
    /**
     * The check passed, the service doesn't believe the content is spam.
     */
    const PASS = 0;

    /**
     * The check failed, the service believes the content is spam.
     */
    const FAIL = 1;

    /**
     * The check errored out when contacting the service.
     */
    const ERROR = 2;

    /**
     * One of {@see \MyBB\AntiSpam\CheckResult::PASS}, {@see \MyBB\AntiSpam\CheckResult::FAIL},
     * {@see \MyBB\AntiSpam\CheckResult::ERROR}.
     *
     * @var int
     */
    private $result;

    /**
     * @var string
     */
    private $error;

    /**
     * @var float
     */
    private $totalConfidence;

    /**
     * @var int
     */
    private $numberOfChecks;

    /**
     * @var float
     */
    private $usernameConfidence;

    /**
     * @var float
     */
    private $emailAddressConfidence;

    /**
     * @var float
     */
    private $ipAddressConfidence;

    public function __construct()
    {
        $this->result = static::PASS;
        $this->error = '';
        $this->totalConfidence = 0.0;
        $this->numberOfChecks = 0;
        $this->usernameConfidence = 0.0;
        $this->emailAddressConfidence = 0.0;
        $this->ipAddressConfidence = 0.0;
    }

    /**
     * @return float
     */
    public function getAverageConfidence(): float
    {
        return $this->totalConfidence / $this->numberOfChecks;
    }

    /**
     * @return int
     */
    public function getResult(): int
    {
        return $this->result;
    }

    /**
     * @param int $result
     *
     * @return self
     *
     * @throws \InvalidArgumentException Thrown if {@see $result} is not one of {@see \MyBB\AntiSpam\CheckResult::PASS},
     * {@see \MyBB\AntiSpam\CheckResult::FAIL}, {@see \MyBB\AntiSpam\CheckResult::ERROR}.
     */
    public function setResult(int $result): self
    {
        if (!in_array($result, [static::PASS, static::FAIL, static::ERROR])) {
            throw new InvalidArgumentException('Result must be one of pass, fail or error');
        }

        $this->result = $result;

        return $this;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @param string $error
     *
     * @return self
     */
    public function setError(string $error): self
    {
        $this->error = $error;

        return $this;
    }

    /**
     * @return float
     */
    public function getTotalConfidence(): float
    {
        return $this->totalConfidence;
    }

    /**
     * @param float $totalConfidence
     *
     * @return self
     */
    public function setTotalConfidence(float $totalConfidence): self
    {
        $this->totalConfidence = $totalConfidence;

        return $this;
    }

    /**
     * @return int
     */
    public function getNumberOfChecks(): int
    {
        return $this->numberOfChecks;
    }

    /**
     * @param int $numberOfChecks
     *
     * @return self
     */
    public function setNumberOfChecks(int $numberOfChecks): self
    {
        $this->numberOfChecks = $numberOfChecks;

        return $this;
    }

    /**
     * @return float
     */
    public function getUsernameConfidence(): float
    {
        return $this->usernameConfidence;
    }

    /**
     * @param float $usernameConfidence
     *
     * @return self
     */
    public function setUsernameConfidence(float $usernameConfidence): self
    {
        $this->usernameConfidence = $usernameConfidence;

        $this->totalConfidence += $usernameConfidence;
        $this->numberOfChecks++;

        return $this;
    }

    /**
     * @return float
     */
    public function getEmailAddressConfidence(): float
    {
        return $this->emailAddressConfidence;
    }

    /**
     * @param float $emailAddressConfidence
     *
     * @return self
     */
    public function setEmailAddressConfidence(float $emailAddressConfidence): self
    {
        $this->emailAddressConfidence = $emailAddressConfidence;

        $this->totalConfidence += $emailAddressConfidence;
        $this->numberOfChecks++;

        return $this;
    }

    /**
     * @return float
     */
    public function getIpAddressConfidence(): float
    {
        return $this->ipAddressConfidence;
    }

    /**
     * @param float $ipAddressConfidence
     *
     * @return self
     */
    public function setIpAddressConfidence(float $ipAddressConfidence): self
    {
        $this->ipAddressConfidence = $ipAddressConfidence;

        $this->totalConfidence += $ipAddressConfidence;
        $this->numberOfChecks++;

        return $this;
    }
}
