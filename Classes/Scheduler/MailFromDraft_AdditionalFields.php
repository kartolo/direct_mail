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
 * Aditional fields provider class for usage with the Scheduler's MailFromDraft task
 *
 * @author		Benjamin Mack <benni@typo3.org>
 * @package		TYPO3
 * @subpackage	direct_mail
 *
 * $Id: $
 */
class tx_directmail_Scheduler_MailFromDraft_AdditionalFields implements tx_scheduler_AdditionalFieldProvider {

	/**
	 * This method is used to define new fields for adding or editing a task
	 * In this case, it adds an email field
	 *
	 * @param	array					$taskInfo: reference to the array containing the info used in the add/edit form
	 * @param	object					$task: when editing, reference to the current task object. Null when adding.
	 * @param	tx_scheduler_Module		$parentObject: reference to the calling object (Scheduler's BE module)
	 * @return	array					Array containg all the information pertaining to the additional fields
	 *									The array is multidimensional, keyed to the task class name and each field's id
	 *									For each field it provides an associative sub-array with the following:
	 *										['code']		=> The HTML code for the field
	 *										['label']		=> The label of the field (possibly localized)
	 *										['cshKey']		=> The CSH key for the field
	 *										['cshLabel']	=> The code of the CSH label
	 */
	public function getAdditionalFields(array &$taskInfo, $task, tx_scheduler_Module $parentObject) {

			// Initialize extra field value
		if (empty($taskInfo['selecteddraft'])) {
			if ($parentObject->CMD == 'edit') {
					// In case of edit, and editing a test task, set to internal value if not data was submitted already
				$taskInfo['selecteddraft'] = $task->draftUid;
			} else {
					// Otherwise set an empty value, as it will not be used anyway
				$taskInfo['selecteddraft'] = '';
			}
		}
		
		// fetch all available drafts
		$drafts = array();
		$draftsInternal = t3lib_BEfunc::getRecordsByField('sys_dmail', 'type', 2);
		$draftsExternal = t3lib_BEfunc::getRecordsByField('sys_dmail', 'type', 3);
		if (is_array($draftsInternal)) {
			$drafts = array_merge($drafts, $draftsInternal);
		}
		if (is_array($draftsExternal)) {
			$drafts = array_merge($drafts, $draftsExternal);
		}

			// Create the input field
		$fieldID = 'task_selecteddraft';
		$fieldHtml = '';
		
		if (count($drafts) === 0) {
				// TODO: localization
			$fieldHtml .= '<option>' . 'No drafts found. Please add one first through the direct mail process'. '</option>';
		} else {
			foreach ($drafts as $draft) {
				$selected = ($task->draftUid == $draft['uid'] ? ' selected="selected"' : '');	// see #44577
				$fieldHtml .= '<option value="' . $draft['uid'] . '"' . $selected . '>' . $draft['subject'] . ' [' . $draft['uid'] . ']</option>';
			}
		}
		$fieldHtml = '<select name="tx_scheduler[selecteddraft]" id="' . $fieldID . '">' . $fieldHtml . '</select>';
		

		$additionalFields = array();
		$additionalFields[$fieldID] = array(
			'code'     => $fieldHtml,
			'label'    => 'Choose Draft to create DirectMail from',	// TODO: add LLL label 'LLL:EXT:scheduler/mod1/locallang.xml:label.email',
			'cshKey'   => '',	// TODO! add CSH
			'cshLabel' => $fieldID
		);

		return $additionalFields;
	}

	/**
	 * This method checks any additional data that is relevant to the specific task
	 * If the task class is not relevant, the method is expected to return true
	 *
	 * @param	array					$submittedData: reference to the array containing the data submitted by the user
	 * @param	tx_scheduler_Module		$parentObject: reference to the calling object (Scheduler's BE module)
	 * @return	boolean					True if validation was ok (or selected class is not relevant), false otherwise
	 */
	public function validateAdditionalFields(array &$submittedData, tx_scheduler_Module $parentObject) {
		$draftUid = $submittedData['selecteddraft'] = intval($submittedData['selecteddraft']);
		if ($draftUid > 0) {
			$draftRecord = t3lib_BEfunc::getRecord('sys_dmail', $draftUid);
			if ($draftRecord['type'] == 2 || $draftRecord['type'] == 3) {
				$result = TRUE;
			} else {
				// TODO: localization
				$parentObject->addMessage('No draft record selected', t3lib_FlashMessage::ERROR);
				$result = FALSE;
			}
		} else {
			// TODO: localization
			$parentObject->addMessage('No drafts found. Please add one first through the direct mail process', t3lib_FlashMessage::ERROR);
			$result = FALSE;
		}

		return $result;
	}

	/**
	 * This method is used to save any additional input into the current task object
	 * if the task class matches
	 *
	 * @param	array				$submittedData: array containing the data submitted by the user
	 * @param	tx_scheduler_Task	$task: reference to the current task object
	 * @return	void
	 */
	public function saveAdditionalFields(array $submittedData, tx_scheduler_Task $task) {
		$task->setDraft($submittedData['selecteddraft']);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/Classes/Scheduler/MailFromDraft_AdditionalFields.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/Classes/Scheduler/MailFromDraft_AdditionalFields.php']);
}

?>