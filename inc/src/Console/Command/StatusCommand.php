<?php

declare(strict_types=1);

namespace MyBB\Console\Command;

use MyBB\Maintenance\InstallationState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'status',
    description: 'Returns the detected installation state',
)]
class StatusCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            name: 'code',
            mode: InputOption::VALUE_NONE,
            description: 'Return status code',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('code')) {
            $value = (string)InstallationState::get()->value;

            $output->write($value);
        } else {
            $lang = \MyBB\app(\MyLanguage::class);

            $value = strip_tags(InstallationState::getDescription($lang));

            $output->writeln($value);
        }

        return Command::SUCCESS;
    }
}
