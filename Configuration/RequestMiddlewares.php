<?php

return [
    'frontend' => [
        'direct-mail/jumpurl-controller' => [
            'target' => \DirectMailTeam\DirectMail\Middleware\JumpurlController::class,
            'before' => [
                'friends-of-typo3/jumpurl',
            ],
        ],
        'direct-mail/simulate-frontend-user-group' => [
            'target' => \DirectMailTeam\DirectMail\Middleware\SimulateFrontendUserGroup::class,
            'before' => [
                'typo3/cms-frontend/tsfe',
            ],
            'after' => [
                'typo3/cms-frontend/authentication',
            ],
        ],
    ],
];
