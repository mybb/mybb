<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

trait DataSharingTrait
{
    /**
     * @var array<string, scalar>
     */
    private array $sharedData = [];

    /**
     * @param array<string, scalar> $data
     */
    public function setSharedData(array $data): void
    {
        $this->sharedData = array_merge($this->sharedData, $data);
    }

    public function getSharedData(?string $key = null): array|null|int|float|string|bool
    {
        if ($key === null) {
            return $this->sharedData;
        } else {
            return $this->sharedData[$key] ?? null;
        }
    }
}
