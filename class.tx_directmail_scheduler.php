<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ivan Kartolo (ivan.kartolo(at)dkd.de)
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


/**
* Class tx_directmail_scheduler
*
* @author	Ivan Kartolo <ivan.kartolo@dkd.de>
* @package TYPO3
* @subpackage	tx_directmail
*/
class tx_directmail_scheduler extends tx_scheduler_Task {

	/**
	 * Function executed from scheduler.
	 * Send the newsletter
	 * 
	 * @return	void
	 */
	function execute() {
			// Check if cronjob is already running:
		$lockfile = PATH_site . 'typo3temp/tx_directmail_cron.lock';
		if (@file_exists($lockfile)) {
				// If the lock is not older than 1 day, something is wrong
			if (filemtime($lockfile) > (time() - (60*60*24))) {
				$GLOBALS['BE_USER']->writelog(4, 0, 1,
					'tx_directmail',
					'TYPO3 Direct Mail Cron: Aborting, another process is already running!',
					array()
				);
				return FALSE;
			} else {
				$GLOBALS['BE_USER']->writelog(4, 0, 0,
					'tx_directmail',
					'TYPO3 Direct Mail Cron: A .lock file was found but it is older than 1 day! Processing mails ...',
					array()
				);
			}
		}
		touch($lockfile);
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->start();
		$htmlmail->runcron();
		unlink($lockfile);
		return TRUE;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.tx_directmail_scheduler.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.tx_directmail_scheduler.php']);
}

?>