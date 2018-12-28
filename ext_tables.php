<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$extPath = TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY);

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
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_sysdmail.xlf');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_group', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_sysdmailg.xlf');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_category', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_sysdmailcat.xlf');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_DirectMail', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_DirectMail.xlf');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_RecipientList', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_RecipientList.xlf');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Statistics', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_Statistics.xlf');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_MailerEngine', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_MailerEngine.xlf');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Configuration', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_Configuration.xlf');
//old
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_DirectMailNavFrame', 'EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_web_txdirectmail.xlf');


if (TYPO3_MODE == 'BE') {
    // add module before 'Help'
    if (!isset($TBE_MODULES['DirectMailNavFrame'])) {
        $temp_TBE_MODULES = array();
        foreach ($TBE_MODULES as $key => $val) {
            if ($key == 'help') {
                $temp_TBE_MODULES['DirectMailNavFrame'] = '';
                $temp_TBE_MODULES[$key] = $val;
            } else {
                $temp_TBE_MODULES[$key] = $val;
            }
        }

        $TBE_MODULES = $temp_TBE_MODULES;
    }

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        '',
        '',
        '',
        array(
            'routeTarget' => DirectMailTeam\DirectMail\Module\NavFrame::class . '::mainAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame',
            'icon' => 'EXT:direct_mail/Resources/Public/Images/module-directmail.svg',
            'labels' => array(
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangNavFrame.xlf',
            ),
        )
    );

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'DirectMail',
        'bottom',
        '',
        array(
            'routeTarget' => DirectMailTeam\DirectMail\Module\Dmail::class . '::mainAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_DirectMail',
            'workspaces' => 'online',
            'icon' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-directmail.svg',
            'labels' => array(
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangDirectMail.xlf',
            ),
            'navigationFrameModule' => 'DirectMailNavFrame',
            'navigationFrameModuleParameters' => array('currentModule' => 'DirectMailNavFrame_DirectMail'),
        )
    );

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'RecipientList',
        'bottom',
        '',
        array(
            'routeTarget' => DirectMailTeam\DirectMail\Module\RecipientList::class . '::mainAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_RecipientList',
            'workspaces' => 'online',
            'icon' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-recipient-list.svg',
            'labels' => array(
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangRecipientList.xlf',
            ),
            'navigationFrameModule' => 'DirectMailNavFrame',
            'navigationFrameModuleParameters' => array('currentModule' => 'DirectMailNavFrame_RecipientList'),
        )
    );

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'Statistics',
        'bottom',
        '',
        array(
            'routeTarget' => DirectMailTeam\DirectMail\Module\Statistics::class . '::mainAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_Statistics',
            'workspaces' => 'online',
            'icon'   => 'EXT:direct_mail/Resources/Public/Images/module-directmail-statistics.svg',
            'labels' => array(
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangStatistics.xlf',
            ),
            'navigationFrameModule' => 'DirectMailNavFrame',
            'navigationFrameModuleParameters' => array('currentModule' => 'DirectMailNavFrame_Statistics'),
        )
    );

    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'MailerEngine',
        'bottom',
        '',
        array(
            'routeTarget' => DirectMailTeam\DirectMail\Module\MailerEngine::class . '::mainAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_MailerEngine',
            'workspaces' => 'online',
            'icon'   => 'EXT:direct_mail/Resources/Public/Images/module-directmail-mailer-engine.svg',
            'labels' => array(
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangMailerEngine.xlf',
            ),
            'navigationFrameModule' => 'DirectMailNavFrame',
            'navigationFrameModuleParameters' => array('currentModule' => 'DirectMailNavFrame_MailerEngine'),
        )
    );


    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
        'DirectMailNavFrame',
        'Configuration',
        'bottom',
        '',
        array(
            'routeTarget' => DirectMailTeam\DirectMail\Module\Configuration::class . '::mainAction',
            'access' => 'group,user',
            'name' => 'DirectMailNavFrame_Configuration',
            'workspaces' => 'online',
            'icon'   => 'EXT:direct_mail/Resources/Public/Images/module-directmail-configuration.svg',
            'labels' => array(
                'll_ref' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallangConfiguration.xlf',
            ),
            'navigationFrameModule' => 'DirectMailNavFrame',
            'navigationFrameModuleParameters' => array('currentModule' => 'DirectMailNavFrame_Configuration'),
        )
    );
}


$GLOBALS['TBE_STYLES']['spritemanager']['singleIcons']['tcarecords-pages-contains-dmail'] = TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath($_EXTKEY) . 'Resources/Public/Icons/ext_icon_dmail_folder.gif';

if (TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getExtensionVersion('tt_address')) <= TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger('2.3.5')) {
    include_once(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY).'Configuration/TCA/Overrides/tt_address.php');
}

/** @var \TYPO3\CMS\Core\Imaging\IconRegistry $iconRegistry */
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Core\Imaging\IconRegistry::class
);

$iconRegistry->registerIcon(
    'direct_mail_newmail',
    \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
    ['source' => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/newmail.gif']
);

$iconRegistry->registerIcon(
    'direct_mail_preview_html',
    \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
    ['source' => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/preview_html.gif']
);

$iconRegistry->registerIcon(
    'direct_mail_preview_plain',
    \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
    ['source' => 'EXT:' . $_EXTKEY . '/Resources/Public/Icons/preview_txt.gif']
);
