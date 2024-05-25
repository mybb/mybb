<?php

declare(strict_types=1);

namespace MyBB\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'upgrade')]
class UpgradeCommand extends MaintenanceProcessCommand
{
    protected function configure(): void
    {
        $this->setDescription('Upgrades an existing MyBB board');

        parent::configure();
    }
}
