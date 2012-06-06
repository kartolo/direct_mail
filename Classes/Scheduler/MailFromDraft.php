<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Benjamin Mack <benni@typo3.org>
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
 * Class tx_directmail_Scheduler_MailFromDraft
 * takes a specific draft and compiles it again, and then creates another
 * directmail record that is ready for sending right away
 *
 * @author	Benjamin Mack <benni@typo3.org>
 * @package TYPO3
 * @subpackage	tx_directmail
 */
class tx_directmail_Scheduler_MailFromDraft extends tx_scheduler_Task {

	public $draftUid = NULL;

	/**
	 * setter function to set the draft ID that the task should use
	 * @param 	integer 	$draftUid	the UID of the sys_dmail record (needs to be of type=3 or type=4)
	 * @param 	void
	 */
	function setDraft($draftUid) {
		$this->draftUid = $draftUid;
	}

	/**
	 * Function executed from scheduler.
	 * Creates a new newsletter record, and sets the scheduled time to "now"
	 * 
	 * @return	bool
	 */
	function execute() {
		if ($this->draftUid > 0) {
			$draftRecord = t3lib_BEfunc::getRecord('sys_dmail', $this->draftUid);
			
				// make a real record out of it
			unset($draftRecord['uid']);
			$draftRecord['tstamp'] = time();
			$draftRecord['type'] -= 2;	// set the right type (3 => 1, 2 => 0)
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_dmail', $draftRecord);
			$dmailUid = $GLOBALS['TYPO3_DB']->sql_insert_id();

				// fetch the cloned record
			$mailRecord = t3lib_BEfunc::getRecord('sys_dmail', $dmailUid);

				// get some parameters from tsConfig
			$tsConfig = t3lib_BEfunc::getModTSconfig($draftRecord['pid'], 'mod.web_modules.dmail');
			$defaultParams = $tsConfig['properties'];

			tx_directmail_static::fetchUrlContentsForDirectMailRecord($mailRecord, $defaultParams);

			$mailRecord = t3lib_BEfunc::getRecord('sys_dmail', $dmailUid);
			if ($mailRecord['mailContent'] && $mailRecord['renderedsize'] > 0) {
				$updateData = array(
					'scheduled' => time(),
					'issent'    => 1
				);
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_dmail', 'uid = ' . intval($dmailUid), $updateData);
			}

		}
		return TRUE;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/Classes/Scheduler/MailFromDraft.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/Classes/Scheduler/MailFromDraft.php']);
}

?>