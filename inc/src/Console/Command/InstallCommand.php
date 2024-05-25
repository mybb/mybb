<?php

declare(strict_types=1);

namespace MyBB\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'install')]
class InstallCommand extends MaintenanceProcessCommand
{
    protected function configure(): void
    {
        $this->setDescription('Installs or re-installs MyBB');

        parent::configure();
    }
}
