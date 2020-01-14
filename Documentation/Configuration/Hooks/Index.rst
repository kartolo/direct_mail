.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


Hooks
------------------------

Following hooks are available in direct_mail:

.. _hooks_cmd_finalmail:
cmd_finalmail
'''''''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail']``

   Method
         ``cmd_finalmail``

   Description
         This hook can be used to influence the last step of the sending wizard. E.g. add import
         button, so that user has to import the recipient before finalizing the sending wizard

.. _hooks_cmd_stats:
cmd_stats
'''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats']``

   Method
         ``cmd_stats_postProcess``

   Description
         This hook can be used to influence the output of the overall statistic output

.. _hooks_cmd_stats_linkResponses:
cmd_stats_linkResponses
'''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats_linkResponses']``

   Method
         ``cmd_stats_linkResponses``

   Description
         This hook can be used to influence the output of the statistics section "link responses" 

.. _hooks_renderCType:
renderCType
'''''''''''

.. container:: table-row

   Property
         ``$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['renderCType']``

   Method
         ``renderPlainText``

   Description
         use this hook to render plain text version of unknown CType (plugin output, etc.)


.. _hooks_mailMarkersHook:
mailMarkersHook
'''''''''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook']``

   Method
         userFunc

   Description
         With this hook, you can write your own userFunc and manipulate
         how marker in the mail should be replaced.


.. _hooks_mailHeadersHook:
mailHeadersHook
''''''''''''''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailHeadersHook']``

   Method
         userFunc

   Description
         With this hook, you can add or edit the headers of the e-mail for each recipient


.. _hooks_cmd_displayImport:
cmd_displayImport
'''''''''''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['cmd_displayImport']``

   Method
         ``cmd_displayImport``

   Description
         Use this hook if you have your own importer.


.. _hooks_doImport:
doImport
''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['doImport']``

   Method
         ``doImport``

   Description
         This hook is called everytime a recipient record is inserted.


.. _hooks_mailFromDraft:
mailFromDraft
'''''''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['direct_mail']['mailFromDraft']``

   Method
         ``postInsertClone``

         ``enqueueClonedDmail``

   Description
         ``postInsertClone`` will be called after the draft record is cloned. Use this to manipulate
         the cloned record.

         ``enqueueClonedDmail`` will be called before enqueueing the cloned draft record to the
         direct_mail mailin engine


.. _hooks_cmd_compileMailGroup:
cmd_compileMailGroup
''''''''''''''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup']``
         ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup']``

   Data type
         ``cmd_compileMailGroup_postProcess``

   Description
         Manipulate the generated ``id_list`` from various recipient lists.


.. _hooks_queryInfoHook:
queryInfoHook
'''''''''''''

.. container:: table-row

   Property
         ``$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['queryInfoHook']``

   Data type
         ``queryInfoHook``

   Description
         This hooks allows the list of recipients to be post-processed before the direct_mail mailer
         starts sending the messages.


Sample implementation for the business logic used by direct_mail_userfunc.

.. code-block:: php

    \Causal\LbCal\DirectMail\RecipientList::participants:
    
    public function participants(array &$params, $pObj)
    {
        // Custom business logic to populate $params['lists']['PLAINLIST']
        //$params['lists']['PLAINLIST'] = ...
        
        /**
         * Persist the configuration being used so that
         * @see \Causal\LbCal\Hooks\DirectMail::updateRecipientsWhenSending()
         * may do the job again.
         *
         * We could persist $params['groupUid'] instead of the current configuration but if
         * the configuration is changed afterwards, this could have unexpected side-effects
         * from the point of view of the user preparing the newsletter so it's better to
         * persist the current (approved) configuration of the list of recipients.
         *
         * BEWARE: Known limitation at this time: we do not handle dynamically regenerating
         *         a compound/aggregate list of recipients (sys_dmail_group|type = 4)
         */
        $params['lists']['tx_directmailuserfunc_itemsprocfunc'] = __CLASS__ . '->' . __FUNCTION__;
        $params['lists']['tx_directmailuserfunc_params'] = $params['userParams'];
    }

Sample implementation of the proposed hook

.. code-block:: php

    public function updateRecipientsWhenSending(array $params, Dmailer $pObj) : void
    {
        $query_info =& $params['query_info'];
        if (isset($query_info['id_lists']['tx_directmailuserfunc_itemsprocfunc'])) {
            // The list of recipients is dynamic and should now be regenerated
            $itemsProcFunc = $query_info['id_lists']['tx_directmailuserfunc_itemsprocfunc'];
    
            $itemsProcFuncParams = [
                'groupUid' => null, // We don't have that information and know we won't use it anyway
                'lists' => &$query_info['id_lists'],
                'userParams' => $query_info['id_lists']['tx_directmailuserfunc_params'],
            ];
    
            // Clear everything so that the list of recipients is generated from a clean state
            $query_info['id_lists'] = [
                'tt_address' => [],
                'fe_users' => [],
                'PLAINLIST' => [],
            ];
    
            GeneralUtility::callUserFunction($itemsProcFunc, $itemsProcFuncParams, $this);
    
            // Unset information for this hook since it is not needed anymore
            // and would be misinterpreted by Dmailer otherwise
            unset($query_info['id_lists']['tx_directmailuserfunc_itemsprocfunc']);
            unset($query_info['id_lists']['tx_directmailuserfunc_params']);
    
            // Persist final list of recipients to the database so that next run of the scheduler
            // can work on the next chunk if needed
            GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_dmail')
                ->update(
                    'sys_dmail',
                    [ 'query_info' => serialize($query_info) ],
                    [ 'uid' => (int)$params['row']['uid'] ]
                );
        }
    }
