<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;

#[AsCommand(name: 'app:test:slack')]
class TestSlackCommand extends Command
{
    public function __construct(private NotifierInterface $notifier)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $notification = (new Notification('Test Slack Alert', ['chat/slack']))
            ->content("Hello from Symfony app!");

        $this->notifier->send($notification);
        $output->writeln('<info>Slack message sent successfully!</info>');
        return Command::SUCCESS;
    }
}
