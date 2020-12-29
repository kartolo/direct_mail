<?php

return [
    'frontend' => [
        'direct-mail/jumpurl-controller' => [
            'target' => \DirectMailTeam\DirectMail\Middleware\JumpurlController::class,
            'before' => [
                'friends-of-typo3/jumpurl'
            ],
        ],
    ],
];
