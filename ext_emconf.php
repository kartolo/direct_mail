<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Direct Mail',
    'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
    'category' => 'module',
    'author' => 'Ivan Kartolo',
    'author_email' => 'ivan.kartolo@dkd.de',
    'author_company' => 'd.k.d Internet Service GmbH',
    'state' => 'stable',
    'clearcacheonload' => 0,
    'version' => '9.1.3',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-11.99.99',
            'lowlevel' => '11.5.0-11.99.99',
            'tt_address' => '5.3.0-7.0.99',
            'php' => '7.4.0-8.1.99',
            'jumpurl' => '8.0.3-',
            'rdct' => '2.1.0',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'suggests' => [
    ],
    'autoload' => [
        'psr-4' => [
            'DirectMailTeam\\DirectMail\\' => 'Classes/',
        ],
    ],
];
