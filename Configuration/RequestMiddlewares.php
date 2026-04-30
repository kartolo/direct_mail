<?php

return [
    'frontend' => [
        'direct-mail/jumpurl-controller' => [
            'target' => \DirectMailTeam\DirectMail\Middleware\JumpurlController::class,
            'before' => [
                'friends-of-typo3/jumpurl',
            ],
        ],
        'direct-mail/simulate-usergroup' => [
            'target' => \DirectMailTeam\DirectMail\Middleware\SimulateUsergroup::class,
            'after' => [
                'typo3/cms-frontend/authentication',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
