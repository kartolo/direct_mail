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

use Fetch\Server;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Class AnalyzeBounceMailAdditionalFields
 * This provides additional fields for the AnalyzeBounceMail task.
 *
 * @package DirectMailTeam\DirectMail\Scheduler
 * @author Ivan Kartolo <ivan.kartolo@gmail.com>
 */
class AnalyzeBounceMailAdditionalFields implements AdditionalFieldProviderInterface
{
    public function __construct()
    {
        // add locallang file
        $this->getLanguangeService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
    }

    /**
     * This method is used to define new fields for adding or editing a task
     * In this case, it adds an email field
     *
     * @param array $taskInfo reference to the array containing the info used in the add/edit form
     * @param AnalyzeBounceMail $task when editing, reference to the current task object. Null when adding.
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     *
     * @return array Array containg all the information pertaining to the additional fields
     *               The array is multidimensional, keyed to the task class name and each field's id
     *               For each field it provides an associative sub-array with the following:
     *               ['code'] => The HTML code for the field
     *               ['label'] => The label of the field (possibly localized)
     *               ['cshKey'] => The CSH key for the field
     *               ['cshLabel'] => The code of the CSH label
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule)
    {
        $serverHTML = '<input type="text" name="tx_scheduler[bounceServer]" value="' . ($task ? $task->getServer() : '') . '"/>';
        $portHTML = '<input type="text" name="tx_scheduler[bouncePort]" value="' . ($task ? $task->getPort() : '') . '"/>';
        $userHTML = '<input type="text" name="tx_scheduler[bounceUser]" value="' . ($task ? $task->getUser() : '') . '"/>';
        $passwordHTML = '<input type="password" name="tx_scheduler[bouncePassword]" value="' . ($task ? $task->getPassword() : '') . '"/>';
        $maxProcessedHTML = '<input type="text" name="tx_scheduler[bounceProcessed]" value="' . ($task ? $task->getMaxProcessed() : '') . '"/>';

        if($task){
            $serviceHTML = '<select name="tx_scheduler[bounceService]" id="bounceService">' .
                '<option value="imap" ' . ($task->getService() === 'imap'? 'selected="selected"' : '') . '>IMAP</option>' .
                '<option value="pop3" ' . ($task->getService() === 'pop3'? 'selected="selected"' : '') . '>POP3</option>' .
                '</select>';

        } else {
            $serviceHTML = '<select name="tx_scheduler[bounceService]" id="bounceService">' .
                '<option value="imap" >IMAP</option>' .
                '<option value="pop3" >POP3</option>' .
                '</select>';
        }

        $additionalFields = array();
        $additionalFields['server'] = $this->createAdditionalFields('server', $serverHTML);
        $additionalFields['port'] = $this->createAdditionalFields('port', $portHTML);
        $additionalFields['user'] = $this->createAdditionalFields('user', $userHTML);
        $additionalFields['password'] = $this->createAdditionalFields('password', $passwordHTML);
        $additionalFields['service'] = $this->createAdditionalFields('service', $serviceHTML);
        $additionalFields['maxProcessed'] = $this->createAdditionalFields('maxProcessed', $maxProcessedHTML);

        return $additionalFields;
    }

    /**
     * Takes care of saving the additional fields' values in the task's object
     *
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param AnalyzeBounceMail $task Reference to the scheduler backend module
     * @return void
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task)
    {
        $task->setServer($submittedData['bounceServer']);
        $task->setPort((int)$submittedData['bouncePort']);
        $task->setUser($submittedData['bounceUser']);
        $task->setPassword($submittedData['bouncePassword']);
        $task->setService($submittedData['bounceService']);
        $task->setMaxProcessed($submittedData['bounceProcessed']);
    }

    /**
     * Validates the additional fields' values
     *
     * @param array $submittedData An array containing the data submitted by the add/edit task form
     * @param SchedulerModuleController $schedulerModule Reference to the scheduler backend module
     * @return bool TRUE if validation was ok (or selected class is not relevant), FALSE otherwise
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule)
    {
        // check if PHP IMAP is installed
        if (extension_loaded('imap')) {
            // check if we can connect using the given data
            /** @var Server $mailServer */
            $mailServer = GeneralUtility::makeInstance(
                \Fetch\Server::class,
                $submittedData['bounceServer'],
                (int)$submittedData['bouncePort'],
                $submittedData['bounceService']
            );

            $mailServer->setAuthentication($submittedData['bounceUser'], $submittedData['bouncePassword']);

            try {
                $imapStream = $mailServer->getImapStream();
                $return = true;
            } catch (\Exception $e) {
                $schedulerModule->addMessage(
                    $this->getLanguangeService()->getLL('scheduler.bounceMail.dataVerification') .
                    $e->getMessage(),
                    FlashMessage::ERROR
                );
                $return = false;
            }
        } else {
            $schedulerModule->addMessage(
                $this->getLanguangeService()->getLL('scheduler.bounceMail.phpImapError'),
                FlashMessage::ERROR
            );
            $return = false;
        }

        return $return;
    }

    protected function createAdditionalFields($fieldName, $fieldHTML)
    {
        // create server input field
        return array(
            'code'     => $fieldHTML,
            'label'    => $this->getLanguangeService()->getLL('scheduler.bounceMail.' . $fieldName),
            'cshKey'   => $fieldName,
            'cshLabel' => $this->getLanguangeService()->getLL('scheduler.bounceMail.csh.' . $fieldName)
        );
    }

    /**
     * Get languange service
     *
     * @return LanguageService
     */
    protected function getLanguangeService()
    {
        return $GLOBALS['LANG'];
    }
}
