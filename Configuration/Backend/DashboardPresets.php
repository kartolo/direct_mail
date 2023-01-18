<?php

return [
    'dm' => [
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang.xlf:dashboard.dm',
        'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang.xlf:dashboard.dm.description',
        #'iconIdentifier' => 'content-dashboard',
        'iconIdentifier' => 'tx-custom_dashboard_widgets-dashboard-icon',
        'defaultWidgets' => ['dm', 'dmMailEngineStatus', 'dmStatistics'],
        'showInWizard' => true,
    ],
];
