<?php

if (!defined('TYPO3_cliMode'))  die('You cannot run this script directly!');

// Include basis cli class
require_once(PATH_t3lib.'class.t3lib_cli.php');

class direct_mail_cli extends t3lib_cli {

	/**
	 * Constructor
	 */
		function direct_mail_cli () {

				// Running parent class constructor
				parent::t3lib_cli();

				// Setting help texts:
				$this->cli_help['name'] = 'Name of script';
				$this->cli_help['synopsis'] = '###OPTIONS###';
				$this->cli_help['description'] = 'Class with basic functionality for CLI scripts';
				$this->cli_help['examples'] = '/.../cli_dispatch.phpsh EXTKEY TASK';
				$this->cli_help['author'] = 'Ivan Kartolo, (c) 2008';
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
		function massSend(){
			global $TYPO3_CONF_VARS;

					// Check if cronjob is already running:
		if (@file_exists (PATH_site.'typo3temp/tx_directmail_cron.lock')) {
				// If the lock is not older than 1 day, skip index creation:
			if (filemtime (PATH_site.'typo3temp/tx_directmail_cron.lock') > (time() - (60*60*24))) {
				die('TYPO3 Direct Mail Cron: Aborting, another process is already running!'.chr(10));
			} else {
				echo('TYPO3 Direct Mail Cron: A .lock file was found but it is older than 1 day! Processing mails ...'.chr(10));
			}
		}

		$lockfile = PATH_site.'typo3temp/tx_directmail_cron.lock';
		touch ($lockfile);
		// Fixing filepermissions
		t3lib_div::fixPermissions($lockfile);
// TODO: remove htmlmail
		require_once(PATH_t3lib.'class.t3lib_cs.php');
		require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.dmailer.php');

		/** @var $htmlmail dmailer */
		/** @var $htmlmail dmailer */
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->start();
		$htmlmail->runcron();

		unlink ($lockfile);
		}
}

// Call the functionality
/** @var $cleanerObj direct_mail_cli */
/** @var $cleanerObj direct_mail_cli */
$cleanerObj = t3lib_div::makeInstance('direct_mail_cli');
$cleanerObj->cli_main($_SERVER['argv']);

?>