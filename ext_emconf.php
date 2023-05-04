<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Direct Mail',
    'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
    'category' => 'module',
    'author' => 'Ivan Kartolo',
    'author_email' => 'ivan.kartolo@dkd.de',
    'author_company' => 'd.k.d Internet Service GmbH',
    'state' => 'alpha',
    'clearcacheonload' => 0,
    'version' => '10.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.99.99',
            'lowlevel' => '12.4.0-12.99.99',
            'tt_address' => '7.1.0-7.1.99',
            'php' => '8.1.0-8.2.99',
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
