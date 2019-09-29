<?php

declare(strict_types=1);

namespace MyBB\Tests\Unit\AntiSpam;

use InvalidArgumentException;
use MyBB\AntiSpam\CheckResult;
use MyBB\Tests\Unit\TestCase;

class CheckResultTest extends TestCase
{
    public function testGetAverageConfidenceWithNoIndividualConfidences()
    {
        $checkResult = new CheckResult();

        $this->assertEquals('0.00', number_format($checkResult->getTotalConfidence(), 2));
        $this->assertEquals('0.00', number_format($checkResult->getResult(), 2));
        $this->assertEquals(0, $checkResult->getNumberOfChecks());
    }

    public function testGetAverageConfidenceWithAllIndividualConfidences()
    {
        $checkResult = (new CheckResult())
            ->setUsernameConfidence(99.95)
            ->setEmailAddressConfidence(31.64)
            ->setIpAddressConfidence(0.0);

        $this->assertEquals('131.59', number_format($checkResult->getTotalConfidence(), 2));
        $this->assertEquals('43.86', number_format($checkResult->getAverageConfidence(), 2));
        $this->assertEquals(3, $checkResult->getNumberOfChecks());
    }

    public function testGetAverageConfidenceWithSomeConfidences()
    {
        $checkResult = (new CheckResult())
            ->setUsernameConfidence(99.95)
            ->setEmailAddressConfidence(31.64);

        $this->assertEquals('131.59', number_format($checkResult->getTotalConfidence(), 2));
        $this->assertEquals('65.80', number_format($checkResult->getAverageConfidence(), 2));
        $this->assertEquals(2, $checkResult->getNumberOfChecks());
    }

    public function testSetResultWithInvalidValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Result must be one of pass, fail or error');

        (new CheckResult())
            ->setResult(10);
    }
}
