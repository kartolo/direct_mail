<?php
declare(strict_types=1);

defined('TYPO3') || die();

// https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ExtensionArchitecture/BestPractises/ConfigurationFiles.html
(function () {
    // Category field disabled by default in backend forms.
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
    	TCEFORM.tt_content.module_sys_dmail_category.disabled = 1
    	TCEFORM.tt_address.module_sys_dmail_category.disabled = 1
    	TCEFORM.fe_users.module_sys_dmail_category.disabled = 1
    	TCEFORM.sys_dmail_group.select_categories.disabled = 1
    ');

    /**
     * Setting up the direct mail module
     */
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_group', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_category', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailcat.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_DirectMail', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_DirectMail.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_RecipientList', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_RecipientList.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Statistics', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_Statistics.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_MailerEngine', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_MailerEngine.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Configuration', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_Configuration.xlf');
    //old
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_DirectMailNavFrame', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_web_txdirectmail.xlf');

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        '',
        '',
        '',
        [
            'routeTarget' => DirectMailTeam\DirectMail\Module\NavFrameController::class . '::indexAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame',
            'icon' => 'EXT:direct_mail/Resources/Public/Images/module-directmail.svg',
            'labels' => [
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangNavFrame.xlf',
            ],
        ]
    );

    // https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ApiOverview/BackendModules/BackendModuleApi/Index.html#without-extbase
    // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.5/Deprecation-94094-NavigationFrameModuleInModuleRegistration.html
    
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'DirectMail',
        'bottom',
        '',
        [
            'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
            'routeTarget' => DirectMailTeam\DirectMail\Module\DmailController::class . '::indexAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_DirectMail',
            'workspaces' => 'online',
            'icon' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-directmail.svg',
            'labels' => [
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangDirectMail.xlf',
            ],
        ]
    );

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'RecipientList',
        'bottom',
        '',
        [
            'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
            'routeTarget' => DirectMailTeam\DirectMail\Module\RecipientListController::class . '::indexAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_RecipientList',
            'workspaces' => 'online',
            'icon' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-recipient-list.svg',
            'labels' => [
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangRecipientList.xlf',
            ],
        ]
    );

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'Statistics',
        'bottom',
        '',
        [
            'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
            'routeTarget' => DirectMailTeam\DirectMail\Module\StatisticsController::class . '::indexAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_Statistics',
            'workspaces' => 'online',
            'icon'   => 'EXT:direct_mail/Resources/Public/Images/module-directmail-statistics.svg',
            'labels' => [
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangStatistics.xlf',
            ],
        ]
    );
    
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'MailerEngine',
        'bottom',
        '',
        [
            'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
            'routeTarget' => DirectMailTeam\DirectMail\Module\MailerEngineController::class . '::indexAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_MailerEngine',
            'workspaces' => 'online',
            'icon'   => 'EXT:direct_mail/Resources/Public/Images/module-directmail-mailer-engine.svg',
            'labels' => [
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangMailerEngine.xlf',
            ],
        ]
    );

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'Configuration',
        'bottom',
        '',
        [
            'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
            'routeTarget' => DirectMailTeam\DirectMail\Module\ConfigurationController::class . '::indexAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_Configuration',
            'workspaces' => 'online',
            'icon'   => 'EXT:direct_mail/Resources/Public/Images/module-directmail-configuration.svg',
            'labels' => [
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangConfiguration.xlf',
            ],
        ]
    );

    if (TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getExtensionVersion('tt_address')) <= TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger('2.3.5')) {
        include_once(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('direct_mail').'Configuration/TCA/Overrides/tt_address.php');
    }
    
    $GLOBALS['TBE_STYLES']['skins']['direct_mail']['stylesheetDirectories'][] = 'EXT:direct_mail/Resources/Public/StyleSheets/';
})();