<?php

if (!defined('TYPO3_cliMode'))  die('You cannot run this script directly!');

// Include basis cli class
use \TYPO3\CMS\Core\Controller\CommandLineController;

class direct_mail_cli extends CommandLineController {

	/**
	 * Constructor
	 */
		function direct_mail_cli() {

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
		 * @return    string
		 */
		function cli_main() {

			// get task (function)
				$task = (string)$this->cli_args['_DEFAULT'][1];

				if (!$task){
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
	 * myFunction which is called over cli
	 *
	 */
	function massSend() {

		// Check if cronjob is already running:
		if (@file_exists(PATH_site . 'typo3temp/tx_directmail_cron.lock')) {
			// If the lock is not older than 1 day, skip index creation:
			if (filemtime(PATH_site . 'typo3temp/tx_directmail_cron.lock') > (time() - (60 * 60 * 24))) {
				die('TYPO3 Direct Mail Cron: Aborting, another process is already running!' . chr(10));
			} else {
				echo('TYPO3 Direct Mail Cron: A .lock file was found but it is older than 1 day! Processing mails ...' . chr(10));
			}
		}

		$lockfile = PATH_site . 'typo3temp/tx_directmail_cron.lock';
		touch($lockfile);
		// Fixing filepermissions
		\TYPO3\CMS\Core\Utility\GeneralUtility::fixPermissions($lockfile);

		/** @var $htmlmail \DirectMailTeam\DirectMail\Dmailer */
		$htmlmail = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Dmailer');
		$htmlmail->start();
		$htmlmail->runcron();

		unlink($lockfile);
	}
}

// Call the functionality
/** @var $mailerObj direct_mail_cli */
$mailerObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('direct_mail_cli');
$mailerObj->cli_main($_SERVER['argv']);

?>