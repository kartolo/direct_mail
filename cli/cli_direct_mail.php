<?php

if (!defined('TYPO3_cliMode')) {
    die('You cannot run this script directly!');
}

// Include basis cli class
use \TYPO3\CMS\Core\Controller\CommandLineController;
use TYPO3\CMS\Core\Core\Environment;

class direct_mail_cli extends CommandLineController
{

    /**
     * Constructor
     */
    public function direct_mail_cli()
    {

            // Running parent class constructor
        parent::__construct();

        // Setting help texts:
        $this->cli_help['name'] = 'direct_mail';
        $this->cli_help['synopsis'] = '###OPTIONS###';
        $this->cli_help['description'] = 'Invoke direct_mail e-mail distribution engine';
        $this->cli_help['examples'] = '/.../cli_dispatch.phpsh direct_mail [masssend]';
        $this->cli_help['author'] = 'Ivan Kartolo, (c) 2008';
        $this->cli_options[] = array('masssend', 'Invoke sending of mails!');
    }

    /**
     * CLI engine
     *
     * @return    void
     */
    public function cli_main()
    {

            // get task (function)
        $task = (string)$this->cli_args['_DEFAULT'][1];

        if (!$task) {
            $this->cli_validateArgs();
            $this->cli_help();
            exit;
        }

        if ($task == 'masssend') {
            $this->massSend();
        }

        /**
         * Or other tasks
         * Which task shoud be called can you define in the shell command
         * /www/typo3/cli_dispatch.phpsh cli_example otherTask
         */
        if ($task == 'otherTask') {
            // ...
        }
    }

    /**
     * Start sending the newsletter
     *
     * @return void
     */
    public function massSend()
    {

        // Check if cronjob is already running:
        if (@file_exists(Environment::getPublicPath() . '/typo3temp/tx_directmail_cron.lock')) {
            // If the lock is not older than 1 day, skip index creation:
            if (filemtime(Environment::getPublicPath() . '/typo3temp/tx_directmail_cron.lock') > (time() - (60 * 60 * 24))) {
                die('TYPO3 Direct Mail Cron: Aborting, another process is already running!' . LF);
            } else {
                echo('TYPO3 Direct Mail Cron: A .lock file was found but it is older than 1 day! Processing mails ...' . LF);
            }
        }

        $lockfile = Environment::getPublicPath() . '/typo3temp/tx_directmail_cron.lock';
        touch($lockfile);
        // Fixing filepermissions
        \TYPO3\CMS\Core\Utility\GeneralUtility::fixPermissions($lockfile);

        /**
         * The direct_mail engine
         * @var $htmlmail \DirectMailTeam\DirectMail\Dmailer
         */
        $htmlmail = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Dmailer');
        $htmlmail->start();
        $htmlmail->runcron();

        unlink($lockfile);
    }
}

// Call the functionality
/**
 * Initializing the CLI class
 * @var $mailerObj direct_mail_cli
 */
$mailerObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('direct_mail_cli');
$mailerObj->cli_main($_SERVER['argv']);
