<?php

return [
    'tx_directmail_pi1' => 'DirectMailTeam\\DirectMail\\Plugin\\DirectMail',
    'tx_directmail_checkjumpurl' => 'DirectMailTeam\\DirectMail\\Checkjumpurl',
    'tx_directmail_container' => 'DirectMailTeam\\DirectMail\\Container',
    'tx_directmail_static' => 'DirectMailTeam\\DirectMail\\DirectMailUtility',
    'dmailer' => 'DirectMailTeam\\DirectMail\\Dmailer',
    'tx_directmail_importer' => 'DirectMailTeam\\DirectMail\\Importer',
    'mailSelect' => 'DirectMailTeam\\DirectMail\\DmQueryGenerator',
    'readmail' => 'DirectMailTeam\\DirectMail\\Readmail',
    'tx_directmail_select_categories' => 'DirectMailTeam\\DirectMail\\SelectCategories',
    'tx_directmail_ttnews_plaintext' => 'DirectMailTeam\\DirectMail\\Hooks\\TtnewsPlaintextHook',
    'tx_directmail_configuration' => 'DirectMailTeam\\DirectMail\\Module\\ConfigurationController',
    'tx_directmail_dmail' => 'DirectMailTeam\\DirectMail\\Module\\DmailController',
    'tx_directmail_mailer_engine' => 'DirectMailTeam\\DirectMail\\Module\\MailerEngineController',
    'tx_directmail_navframe' => 'DirectMailTeam\\DirectMail\\Module\\NavFrameController',
    'tx_directmail_recipient_list' => 'DirectMailTeam\\DirectMail\\Module\\RecipientListController',
    'tx_directmail_statistics' => 'DirectMailTeam\\DirectMail\\Module\\StatisticsController',
    'tx_directmail_scheduler' => 'DirectMailTeam\\DirectMail\\Scheduler\\DirectmailScheduler',
    'tx_directmail_Scheduler_MailFromDraft' => 'DirectMailTeam\\DirectMail\\Scheduler\\MailFromDraft',
    'tx_directmail_Scheduler_MailFromDraft_AdditionalFields' => 'DirectMailTeam\\DirectMail\\Scheduler\\MailFromDraftAdditionalFields',
    'tx_directmail_Scheduler_MailFromDraftHook' => 'DirectMailTeam\\DirectMail\\Scheduler\\MailFromDraftHookInterface'
];
