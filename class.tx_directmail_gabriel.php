<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Ivan Kartolo (ivan.kartolo(at)dkd.de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

// TODO: remove htmlmail
require_once(t3lib_extMgm::extPath('gabriel', 'class.tx_gabriel_event.php'));
require_once(PATH_t3lib.'class.t3lib_htmlmail.php');
require_once(PATH_t3lib.'class.t3lib_cs.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.dmailer.php');

/**
 * Class tx_directmail_gabriel
 *
 * @author	Ivan Kartolo <ivan.kartolo@dkd.de>
 * @package TYPO3
 * @subpackage	tx_directmail
 */
class tx_directmail_gabriel extends tx_gabriel_event {
	/**
	 * Function executed from gabriel.
	 * Send the newsletter
	 *
	 * @return	void
	 */
	function execute() {
		// log this call as deprecated
		t3lib_div::deprecationLog("direct_mail for gabriel will be removed in the upcoming direct_mail release (version 3.1). Please use the scheduler");

		global $BE_USER;

		// Check if cronjob is already running:
		if (@file_exists (PATH_site.'typo3temp/tx_directmail_cron.lock')) {
				// If the lock is not older than 1 day, skip index creation:
			if (filemtime (PATH_site.'typo3temp/tx_directmail_cron.lock') > (time() - (60*60*24))) {
				$BE_USER->writelog(
					4,
					0,
					1,
					'tx_directmail',
					'TYPO3 Direct Mail Cron: Aborting, another process is already running!',
					array()
				);
			} else {
				$BE_USER->writelog(
					4,
					0,
					0,
					'tx_directmail',
					'TYPO3 Direct Mail Cron: A .lock file was found but it is older than 1 day! Processing mails ...',
					array()
				);
			}
		}

		$lockfile = PATH_site.'typo3temp/tx_directmail_cron.lock';
		touch ($lockfile);

		/** @var $htmlmail dmailer */
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->start();
		$htmlmail->runcron();

		unlink ($lockfile);
	} // end of 'function execute() {..}'



	/**
	 * PHP4 wrapper function for the class constructor
	 *
	 * @return 	void
	 */
	function tx_directmail_gabriel() {
		$this->__construct();
	} // end of 'function tx_gabriel_testevent() {..}'

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.tx_directmail_gabriel.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.tx_directmail_gabriel.php']);
}

?>