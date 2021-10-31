<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Direct Mail',
    'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
    'category' => 'module',
    'version' => '7.0.1',
    'state' => 'stable',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'Ivan Kartolo',
    'author_email' => 'ivan.kartolo@dkd.de',
    'author_company' => 'd.k.d Internet Service GmbH',
    'constraints' => [
        'depends' => [
            'tt_address' => '4.0.0-',
            'php' => '7.2.0',
            'typo3' => '11.5.0-11.9.99',
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
