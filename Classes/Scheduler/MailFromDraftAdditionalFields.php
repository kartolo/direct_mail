<?php

namespace DirectMailTeam\DirectMail\Scheduler;

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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;

/**
 * Aditional fields provider class for usage with the Scheduler's MailFromDraft task.
 *
 * @author		Benjamin Mack <benni@typo3.org>
 */
class MailFromDraftAdditionalFields implements AdditionalFieldProviderInterface
{
    /**
     * This method is used to define new fields for adding or editing a task
     * In this case, it adds an email field.
     *
     * @param array                                                     $taskInfo     reference to the array containing the info used in the add/edit form
     * @param object                                                    $task         when editing, reference to the current task object. Null when adding
     * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject reference to the calling object (Scheduler's BE module)
     *
     * @return array Array containg all the information pertaining to the additional fields
     *               The array is multidimensional, keyed to the task class name and each field's id
     *               For each field it provides an associative sub-array with the following:
     *               ['code']		=> The HTML code for the field
     *               ['label']		=> The label of the field (possibly localized)
     *               ['cshKey']		=> The CSH key for the field
     *               ['cshLabel']	=> The code of the CSH label
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $parentObject)
    {

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
        $draftsInternal = BackendUtility::getRecordsByField('sys_dmail', 'type', 2);
        $draftsExternal = BackendUtility::getRecordsByField('sys_dmail', 'type', 3);
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
            $fieldHtml .= '<option>'.'No drafts found. Please add one first through the direct mail process'.'</option>';
        } else {
            foreach ($drafts as $draft) {
                // see #44577
                $selected = ($task->draftUid == $draft['uid'] ? ' selected="selected"' : '');
                $fieldHtml .= '<option value="'.$draft['uid'].'"'.$selected.'>'.$draft['subject'].' ['.$draft['uid'].']</option>';
            }
        }
        $fieldHtml = '<select name="tx_scheduler[selecteddraft]" id="'.$fieldID.'">'.$fieldHtml.'</select>';

        $additionalFields = array();
        $additionalFields[$fieldID] = array(
            'code' => $fieldHtml,
            // TODO: add LLL label 'LLL:EXT:scheduler/mod1/locallang.xml:label.email',
            'label' => 'Choose Draft to create DirectMail from',
            // TODO! add CSH
            'cshKey' => '',
            'cshLabel' => $fieldID,
        );

        return $additionalFields;
    }

    /**
     * This method checks any additional data that is relevant to the specific task
     * If the task class is not relevant, the method is expected to return true.
     *
     * @param array                                                     $submittedData Reference to the array containing the data submitted by the user
     * @param \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject  Reference to the calling object (Scheduler's BE module)
     *
     * @return bool True if validation was ok (or selected class is not relevant), false otherwise
     */
    public function validateAdditionalFields(array &$submittedData, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject)
    {
        $draftUid = $submittedData['selecteddraft'] = intval($submittedData['selecteddraft']);
        if ($draftUid > 0) {
            $draftRecord = BackendUtility::getRecord('sys_dmail', $draftUid);
            if ($draftRecord['type'] == 2 || $draftRecord['type'] == 3) {
                $result = true;
            } else {
                // TODO: localization
                $parentObject->addMessage('No draft record selected', FlashMessage::ERROR);
                $result = false;
            }
        } else {
            // TODO: localization
            $parentObject->addMessage('No drafts found. Please add one first through the direct mail process', FlashMessage::ERROR);
            $result = false;
        }

        return $result;
    }

    /**
     * This method is used to save any additional input into the current task object
     * if the task class matches.
     *
     * @param array                                  $submittedData Array containing the data submitted by the user
     * @param \TYPO3\CMS\Scheduler\Task\AbstractTask $task          Reference to the current task object
     */
    public function saveAdditionalFields(array $submittedData, \TYPO3\CMS\Scheduler\Task\AbstractTask $task)
    {
        $task->setDraft($submittedData['selecteddraft']);
    }
}
