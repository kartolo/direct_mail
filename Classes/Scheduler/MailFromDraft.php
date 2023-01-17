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

use DirectMailTeam\DirectMail\DirectMailUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Class tx_directmail_Scheduler_MailFromDraft
 * takes a specific draft and compiles it again, and then creates another
 * directmail record that is ready for sending right away
 *
 * @author	Benjamin Mack <benni@typo3.org>
 */
class MailFromDraft extends AbstractTask
{
    public $draftUid;

    protected $hookObjects = [];

    /**
     * Setter function to set the draft ID that the task should use
     *
     * @param int $draftUid The UID of the sys_dmail record (needs to be of type=3 or type=4)
     */
    public function setDraft($draftUid)
    {
        $this->draftUid = $draftUid;
    }

    /**
     * Function executed from scheduler.
     * Creates a new newsletter record, and sets the scheduled time to "now"
     *
     * @return	bool
     */
    public function execute()
    {
        if ($this->draftUid > 0) {
            $this->initializeHookObjects();
            $hookParams = [];

            $draftRecord = BackendUtility::getRecord('sys_dmail', $this->draftUid);

            // update recipients
            $recipientGroups = explode(',', $draftRecord['recipientGroups']);
            $SOBE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\DirectMailTeam\DirectMail\Module\Dmail::class);
            $SOBE->init();

            $newRecipients = $SOBE->cmd_compileMailGroup($recipientGroups);

            // get some parameters from tsConfig
            $defaultParams = BackendUtility::getPagesTSconfig($draftRecord['pid'])['mod.']['web_modules.']['dmail.'] ?? [];

            // make a real record out of it
            unset($draftRecord['uid']);
            $draftRecord['tstamp'] = time();
            // set the right type (3 => 1, 2 => 0)
            $draftRecord['type'] -= 2;
            $draftRecord['query_info'] = serialize($newRecipients['queryInfo']);

            // check if domain record is set
            if (Environment::isCli()
                && (int)$draftRecord['type'] !== 1
                && !$this->checkUrlBase((int)$draftRecord['page'])
            ) {
                throw new \Exception('No site found in root line of page ' . $draftRecord['page'] . '!');
            }

            // Insert the new dmail record into the DB
            //$GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_dmail', $draftRecord);
            //$this->dmailUid = $GLOBALS['TYPO3_DB']->sql_insert_id();

            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $databaseConnectionSysDmailMail = $connectionPool->getConnectionForTable('sys_dmail');
            $databaseConnectionSysDmailMail->insert(
                'sys_dmail',
                $draftRecord
            );
            $this->dmailUid = (int)$databaseConnectionSysDmailMail->lastInsertId('sys_dmail');

            // Call a hook after insertion of the cloned dmail record
            // This hook can get used to modify fields of the direct mail.
            // For example the current date could get appended to the subject.
            $hookParams['draftRecord'] = &$draftRecord;
            $hookParams['defaultParams'] = &$defaultParams;
            $this->callHooks('postInsertClone', $hookParams);

            // fetch the cloned record
            $mailRecord = BackendUtility::getRecord('sys_dmail', $this->dmailUid);

            // fetch mail content
            $result = DirectMailUtility::fetchUrlContentsForDirectMailRecord($mailRecord, $defaultParams, true);

            if ($result['errors'] !== []) {
                throw new \Exception('Failed to fetch contents: ' . implode(', ', $result['errors']));
            }

            $mailRecord = BackendUtility::getRecord('sys_dmail', $this->dmailUid);
            if ($mailRecord['mailContent'] && $mailRecord['renderedsize'] > 0) {
                $updateData = [
                    'scheduled' => time(),
                    'issent'    => 1,
                ];
                // Call a hook before enqueuing the cloned dmail record into
                // the direct mail delivery queue
                $hookParams['mailRecord'] = &$mailRecord;
                $hookParams['updateData'] = &$updateData;
                $this->callHooks('enqueueClonedDmail', $hookParams);
                // Update the cloned dmail so it will get sent upon next
                // invocation of the mailer engine
                $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
                $connection = $connectionPool->getConnectionForTable('sys_dmail');
                $connection->update(
                    'sys_dmail', // table
                    $updateData, // value array
                    [ 'uid' => (int)$this->dmailUid ] // where
                );
            }
        }
        return true;
    }

    /**
     * Get the base URL
     *
     * @param int $pageId
     * @return bool
     * @throws SiteNotFoundException
     * @throws InvalidRouteArgumentsException
     */
    protected function checkUrlBase(int $pageId): bool
    {
        if ($pageId > 0) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            if (!empty($siteFinder->getAllSites())) {
                $site = $siteFinder->getSiteByPageId($pageId);
                $base = $site->getBase();
                if ($base->getHost()) {
                    return true;
                }
            } else {
                return false; // No site found in root line of pageId
            }
        }

        return false; // No valid pageId
    }

    /**
     * Calls the passed hook method of all configured hook object instances
     *
     * @param string $hookMethod The hook method name
     * @param array $hookParams The hook params
     */
    public function callHooks($hookMethod, array $hookParams)
    {
        foreach ($this->hookObjects as $hookObjectInstance) {
            $hookObjectInstance->$hookMethod($hookParams, $this);
        }
    }

    /**
     * Initializes hook objects for this class
     *
     * @throws \Exception
     */
    public function initializeHookObjects()
    {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['direct_mail']['mailFromDraft'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['direct_mail']['mailFromDraft'] as $hookObj) {
                $hookObjectInstance = GeneralUtility::makeInstance($hookObj);
                if (!(is_object($hookObjectInstance) && ($hookObjectInstance instanceof MailFromDraftHookInterface))) {
                    throw new \Exception('Hook object for "mailFromDraft" must implement the "MailFromDraftHookInterface"!', 1400866815);
                }
                $this->hookObjects[] = $hookObjectInstance;
            }
        }
    }
}
