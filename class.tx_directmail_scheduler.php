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
	 * @return	bool
	 */
	function execute() {
		/** @var $htmlmail dmailer */
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->start();
		$htmlmail->runcron();
		return TRUE;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.tx_directmail_scheduler.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.tx_directmail_scheduler.php']);
}

?>