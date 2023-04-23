<?php
//https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ExtensionArchitecture/HowTo/BackendModule/ModuleConfiguration.html
use DirectMailTeam\DirectMail\Module\DmailController;
use DirectMailTeam\DirectMail\Module\RecipientListController;
use DirectMailTeam\DirectMail\Module\StatisticsController;
use DirectMailTeam\DirectMail\Module\MailerEngineController;
use DirectMailTeam\DirectMail\Module\ConfigurationController;

return [
    'directmail' => [
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangNavFrame.xlf',
        'iconIdentifier' => 'directmail-module',
        'navigationComponent' => '@typo3/backend/page-tree/page-tree-element',
        //'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
    ],
    'directmail_module_directmail' => [
        'parent' => 'directmail',
        'position' => ['top'],
        'access' => 'group,user',
        'workspaces' => 'live',
        'path' => '/module/directmail/directmail',
        'iconIdentifier' => 'directmail-module-directmail',
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangDirectMail.xlf',
        'routes' => [
            '_default' => [
                'target' => DmailController::class . '::indexAction',
            ],
        ],
    ],
    'directmail_module_recipientlist' => [
        'parent' => 'directmail',
        'position' => ['after' => 'directmail_module_directmail'],
        'access' => 'group,user',
        'workspaces' => 'live',
        'path' => '/module/directmail/recipientlist',
        'iconIdentifier' => 'directmail-module-recipient-list',
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangRecipientList.xlf',
        'routes' => [
            '_default' => [
                'target' => RecipientListController::class . '::indexAction',
            ],
        ],
    ],
    'directmail_module_statistics' => [
        'parent' => 'directmail',
        'position' => ['after' => 'directmail_module_recipientlist'],
        'access' => 'group,user',
        'workspaces' => 'live',
        'path' => '/module/directmail/statistics',
        'iconIdentifier' => 'directmail-module-statistics',
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangStatistics.xlf',
        'routes' => [
            '_default' => [
                'target' => StatisticsController::class . '::indexAction',
            ],
        ],
    ],
    'directmail_module_mailerengine' => [
        'parent' => 'directmail',
        'position' => ['after' => 'directmail_module_statistics'],
        'access' => 'group,user',
        'workspaces' => 'live',
        'path' => '/module/directmail/mailerengine',
        'iconIdentifier' => 'directmail-module-mailer-engine',
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangMailerEngine.xlf',
        'routes' => [
            '_default' => [
                'target' => MailerEngineController::class . '::indexAction',
            ],
        ],
    ],
    'directmail_module_configuration' => [
        'parent' => 'directmail',
        'position' => ['bottom '],
        'access' => 'group,user',
        'workspaces' => 'live',
        'path' => '/module/directmail/configuration',
        'iconIdentifier' => 'directmail-module-configuration',
        'labels' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangConfiguration.xlf',
        'routes' => [
            '_default' => [
                'target' => ConfigurationController::class . '::handleRequest',
            ],
        ],
    ],
];

