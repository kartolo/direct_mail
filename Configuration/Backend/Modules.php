<?php
return [
    'directmail' => [
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangNavFrame.xlf',
        'iconIdentifier' => 'directmail-module',
        'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
    ],
    'directmail_module_directmail' => [
        'parent' => 'directmail',
        'position' => ['before' => '*'],
        'access' => 'group,user',
        'workspaces' => 'live',
        'path' => '/module/directmail/directmail',
        'iconIdentifier' => 'directmail-module-directmail',
        'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangDirectMail.xlf',
        'routes' => [
            '_default' => [
                'target' => DirectMailTeam\DirectMail\Module\DmailController::class . '::indexAction',
            ],
        ],
    ],
    'directmail_module_configuration' => [
        'parent' => 'directmail',
        'position' => ['before' => '*'],
        'access' => 'group,user',
        'workspaces' => 'live',
        'path' => '/module/directmail/configuration',
        'iconIdentifier' => 'directmail-module-configuration',
        'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangConfiguration.xlf',
        'routes' => [
            '_default' => [
                'target' => DirectMailTeam\DirectMail\Module\ConfigurationController::class . '::indexAction',
            ],
        ],
    ],
];

