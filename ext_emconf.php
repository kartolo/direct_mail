<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Direct Mail',
    'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
    'category' => 'module',
    'author' => 'Ivan Kartolo',
    'author_email' => 'ivan.kartolo@dkd.de',
    'author_company' => 'd.k.d Internet Service GmbH',
    'state' => 'alpha',
    'version' => '10.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'lowlevel' => '13.4.0-13.99.99',
            'tt_address' => '9.0.0-9.0.99',
            'php' => '8.2.0-8.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'DirectMailTeam\\DirectMail\\' => 'Classes/',
        ],
    ],
];
