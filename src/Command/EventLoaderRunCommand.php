<?php

namespace App\Command;

use App\src\Contract\EventLoaderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:event-loader:run',
    description: 'Add a short description for your command',
)]
class EventLoaderRunCommand extends Command
{
    public function __construct(private readonly EventLoaderInterface $eventLoader)
    {
        parent::__construct();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventLoader->run();
        return Command::SUCCESS;
    }
}
