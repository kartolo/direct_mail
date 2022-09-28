<?php
namespace DirectMailTeam\DirectMail\Command;

use DirectMailTeam\DirectMail\Dmailer;
use DirectMailTeam\DirectMail\Readmail;
use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use Fetch\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AnalyzeBounceMailCommand extends Command
{
    private ?LanguageService $languageService = null;

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
        $this->setLanguageService();

        $server = '';
        $port = 0;
        $user = '';
        $password = '';
        $type = '';
        $count = 0;
        // check if PHP IMAP is installed
        if (!extension_loaded('imap')) {
            $io->error($this->languageService->getLL('scheduler.bounceMail.phpImapError'));
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
            if(!in_array($type, ['imap', 'pop3'])) {
                $io->warning('Type: only imap or pop3');
                return Command::FAILURE;
            }
        }
        if ($input->getOption('count')) {
            $count = (int)$input->getOption('count');
            //$io->writeln($count);
        }

        // try connect to mail server
        $mailServer = $this->connectMailServer($server, $port, $type, $user, $password, $io);
        if ($mailServer instanceof Server) {
            // we are connected to mail server
            // get unread mails
            $messages = $mailServer->search('UNSEEN', $count);
            if(count($messages)) {
                /** @var Message $message The message object */
                foreach ($messages as $message) {
                    // process the mail
                    if ($this->processBounceMail($message)) {
                        //$io->writeln($message->getSubject());
                        // set delete
                        $message->delete();
                    } 
                    else {
                        $message->setFlag('SEEN');
                    }
                }
            }
            // expunge to delete permanently
            $mailServer->expunge();
            imap_close($mailServer->getImapStream());
            return Command::SUCCESS;
        }
        else {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Process the bounce mail
     * @param Message $message the message object
     * @return bool true if bounce mail can be parsed, else false
     */
    private function processBounceMail($message)
    {
        /** @var Readmail $readMail */
        $readMail = GeneralUtility::makeInstance(Readmail::class);
        
        // get attachment
        $attachmentArray = $message->getAttachments();
        $midArray = [];
        if (is_array($attachmentArray)) {
            // search in attachment
            foreach ($attachmentArray as $attachment) {
                $bouncedMail = $attachment->getData();
                // Find mail id
                $midArray = $readMail->find_XTypo3MID($bouncedMail);
                if (false === empty($midArray)) {
                    // if mid, rid and rtbl are found, then stop looping
                    break;
                }
            }
        } 
        else {
            // search in MessageBody (see rfc822-headers as Attachments placed )
            $midArray = $readMail->find_XTypo3MID($message->getMessageBody());
        }
        
        if (empty($midArray)) {
            // no mid, rid and rtbl found - exit
            return false;
        }
        
        // Extract text content
        $cp = $readMail->analyseReturnError($message->getMessageBody());
        
        $row = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->selectForAnalyzeBounceMail($midArray['rid'], $midArray['rtbl'], $midArray['mid']);
        
        // only write to log table, if we found a corresponding recipient record
        if (!empty($row)) {
            $tableMaillog = 'sys_dmail_maillog';
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableMaillog);
            try {
                $midArray['email'] = $row['email'];
                $insertFields = [
                    'tstamp' => $this->getTimestampFromAspect(),
                    'response_type' => -127,
                    'mid' => (int)$midArray['mid'],
                    'rid' => (int)$midArray['rid'],
                    'email' => $midArray['email'],
                    'rtbl' => $midArray['rtbl'],
                    'return_content' => serialize($cp),
                    'return_code' => (int)$cp['reason']
                ];
                $connection->insert($tableMaillog, $insertFields);
                $sql_insert_id = $connection->lastInsertId($tableMaillog);
                return (bool)$sql_insert_id;
            } catch (\Doctrine\DBAL\DBALException $e) {
                // Log $e->getMessage();
                return false;
            }
        }
        else {
            return false;
        }
    }
    
    /**
     * Create connection to mail server.
     * Return mailServer object or false on error
     *
     * @param string $server
     * @param int $port
     * @param string $type
     * @param string $user
     * @param string $password
     * @param SymfonyStyle $io
     * @return bool|Server
     */
    private function connectMailServer(string $server, int $port, string $type, string $user, string $password, SymfonyStyle $io)
    {
        // check if we can connect using the given data
        /** @var Server $mailServer */
        $mailServer = GeneralUtility::makeInstance(
            Server::class,
            $server,
            $port,
            $type
        );
        
        // set mail username and password
        $mailServer->setAuthentication($user, $password);
        
        try {
            $imapStream = $mailServer->getImapStream();
            return $mailServer;
        } catch (\Exception $e) {
            $io->error($this->languageService->getLL('scheduler.bounceMail.dataVerification').$e->getMessage());
            return false;
        }
    }
    
    /**
     * 
     * @return int
     */
    private function getTimestampFromAspect(): int {
        $context = GeneralUtility::makeInstance(Context::class);
        return $context->getPropertyFromAspect('date', 'timestamp');
    }
    
    /**
     * @return void
     */
    private function setLanguageService(): void {
        $languageServiceFactory = GeneralUtility::makeInstance(LanguageServiceFactory::class);
        $this->languageService = $languageServiceFactory->create('en'); //@TODO
        $this->languageService->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
    }
}
