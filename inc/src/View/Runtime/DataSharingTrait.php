<?php

declare(strict_types=1);

namespace MyBB\View\Runtime;

use Twig\Environment;

trait DataSharingTrait
{
    /**
     * @var array<string, scalar>
     */
    private array $sharedData = [];

    private Environment $twig;

    /**
     * @param array<string, scalar> $data
     */
    public function setSharedData(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->sharedData[$key] = $value;

            // update existing value (new globals cannot be set after Twig initialization)
            if (isset($this->twig) && array_key_exists($key, $this->sharedData)) {
                $this->twig->addGlobal($key, $value);
            }
        }
    }

    public function getSharedData(?string $key = null): array|null|int|float|string|bool
    {
        if ($key === null) {
            return $this->sharedData;
        } else {
            return $this->sharedData[$key] ?? null;
        }
    }

    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }
}
