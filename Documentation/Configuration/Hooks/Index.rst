

.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. ==================================================
.. DEFINE SOME TEXTROLES
.. --------------------------------------------------
.. role::   underline
.. role::   typoscript(code)
.. role::   ts(typoscript)
   :class:  typoscript
.. role::   php(code)


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
