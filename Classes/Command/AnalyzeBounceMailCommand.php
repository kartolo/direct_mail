<?php
namespace DirectMailTeam\DirectMail\Command;

use DirectMailTeam\DirectMail\Dmailer;
use Fetch\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AnalyzeBounceMailCommand extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('This command will get bounce mail from the configured mailbox')
            ->addOption(
                'server',
                's',
                InputOption::VALUE_REQUIRED,
                'Server URL/IP'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_REQUIRED,
                'Port number'
            )
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'Username'
            )
            ->addOption(
                'password',
                'pw',
                InputOption::VALUE_REQUIRED,
                'Password'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Type of mailserver (imap or pop3)'
            )
            ->addOption(
                'count',
                'c',
                InputOption::VALUE_REQUIRED,
                'Number of bounce mail to be processed'
            )
            //->setHelp('')
        ;
    }
    
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        
        $server = '';
        $port = 0;
        $user = '';
        $password = '';
        $type = '';
        $count = 0;
        // check if PHP IMAP is installed
        if (!extension_loaded('imap')) {
            $io->error('Please install PHP IMAP extension'); //scheduler.bounceMail.phpImapError
            return Command::FAILURE;
        }
        
        if ($input->getOption('server')) {
            $server = $input->getOption('server');
            //$io->writeln($server);
        }
        if ($input->getOption('port')) {
            $port = (int)$input->getOption('port');
            //$io->writeln($port);
        }
        if ($input->getOption('user')) {
            $user = $input->getOption('user');
            //$io->writeln($user);
        }
        if ($input->getOption('password')) {
            $password = $input->getOption('password');
            //$io->writeln($password);
        }
        if ($input->getOption('type')) {
            $type = $input->getOption('type');
            //$io->writeln($type);
            if(in_array($type, ['imap', 'pop3'])) {
                $io->warning('Type: only imap or pop3');
                return Command::FAILURE;
            }
        }
        if ($input->getOption('count')) {
            $count = $input->getOption('count');
            //$io->writeln($count);
        }
        
        // check if we can connect using the given data
        /** @var Server $mailServer */
        $mailServer = GeneralUtility::makeInstance(
            \Fetch\Server::class,
            $server,
            $port,
            $type
        );
        
        $mailServer->setAuthentication($user, $password);
        
        try {
            $imapStream = $mailServer->getImapStream();
        } catch (\Exception $e) {
            $io->error('Connection using the given data is failed. Error message: '.$e->getMessage()); //scheduler.bounceMail.dataVerification
            return Command::FAILURE;
        }
        
        $io->writeln('Test');
        return Command::SUCCESS;
    }
}