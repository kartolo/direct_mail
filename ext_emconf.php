<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "direct_mail".
 *
 * Auto generated 29-07-2013 16:04
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF['direct_mail'] = [
    'title' => 'Direct Mail',
    'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
    'category' => 'module',
    'shy' => 0,
    'version' => '7.1.0-dev',
    'dependencies' => 'cms,tt_address',
    'conflicts' => 'sr_direct_mail_ext,it_dmail_fix,plugin_mgm,direct_mail_123',
    'priority' => '',
    'loadOrder' => '',
    'module' => 'mod1,mod2,mod3,mod4,mod5,mod6',
    'state' => 'stable',
    'uploadfolder' => 1,
    'createDirs' => '',
    'modify_tables' => 'tt_content,tt_address,fe_users',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'Ivan Kartolo',
    'author_email' => 'ivan.kartolo@dkd.de',
    'author_company' => 'd.k.d Internet Service GmbH',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'constraints' => [
        'depends' => [
            'tt_address' => '4.0.0-',
            'php' => '7.2.0',
            'typo3' => '10.4.0-10.4.99',
            'jumpurl' => '8.0.0-',
            'rdct' => '2.0.0'
        ],
        'conflicts' => [
            'sr_direct_mail_ext' => '',
            'it_dmail_fix' => '',
            'plugin_mgm' => '',
            'direct_mail_123' => '',
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
    'autoload' => [
        'psr-4' => [
            'DirectMailTeam\\DirectMail\\' => 'Classes/',
            'Fetch\\' => 'Resources/Private/Php/Fetch/src/Fetch/'
        ]
    ],
];
