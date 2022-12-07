<?php
namespace DirectMailTeam\DirectMail\Command;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use DirectMailTeam\DirectMail\Dmailer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class InvokeMailerEngineCommand
 *
 * Starts sending the newsletter by invoking mailer engine via CLI
 *
 * Use TYPO3 CLI module dispatcher with `direct_mail:invokemailerengine`
 *
 * This class replaces the earlier version of EXT:direct_mail/cli/cli_direct_mail.php from Ivan Kartolo, (c) 2008
 * Executes the earlier solely option named 'masssend' which has been dropped as optional argument
 *
 * @package TYPO3
 * @subpackage tx_directmail
 * @author 2019 J.Kummer
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 2
 */
class InvokeMailerEngineCommand extends Command
{

    /**
     * Configure the command by defining the name, options and arguments
     */
    public function configure()
    {
        $this->setDescription('Invoke Mailer Engine of EXT:directmail');
        $this->setHelp('
Sends newsletters which are ready to send.

Depend on how many direct_mail newsletters are planned or left to get send out,
and the extension configuration for number of messages to be sent per cycle of the dmailer cron task,
this command will send the latest open newsletter queue,
like the recommended scheduler task or BE module for invoking maler engine will do.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        $lockfile = Environment::getPublicPath() . '/typo3temp/tx_directmail_cron.lock';

        // Check if cronjob is already running:
        if (@file_exists($lockfile)) {
            // If the lock is not older than 1 day, skip:
            if (filemtime($lockfile) > (time() - (60 * 60 * 24))) {
                $io->warning('TYPO3 Direct Mail Cron: Aborting, another process is already running!');
                return Command::FAILURE;
            } else {
                $io->writeln('TYPO3 Direct Mail Cron: A .lock file was found but it is older than 1 day! Processing mails ...');
            }
        }

        touch($lockfile);
        // Fixing filepermissions
        GeneralUtility::fixPermissions($lockfile);

        /**
         * The direct_mail engine
         * @var $htmlmail Dmailer
         */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->start();
        $htmlmail->runcron();

        unlink($lockfile);
        return Command::SUCCESS;
    }
}
