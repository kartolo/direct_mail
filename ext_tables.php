<?php

use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use \TYPO3\CMS\Backend\Sprite\SpriteManager;

if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$extPath = ExtensionManagementUtility::extPath($_EXTKEY);

ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/boundaries/','Direct Mail Content Boundaries');
ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/plaintext/', 'Direct Mail Plain text');
ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/tt_news_plaintext/', 'Direct Mail News Plain text');

	// Category field disabled by default in backend forms.
ExtensionManagementUtility::addPageTSConfig('
	TCEFORM.tt_content.module_sys_dmail_category.disabled = 1
	TCEFORM.tt_address.module_sys_dmail_category.disabled = 1
	TCEFORM.fe_users.module_sys_dmail_category.disabled = 1
	TCEFORM.sys_dmail_group.select_categories.disabled = 1
');

/**
 * Setting up the direct mail module
 */
ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_sysdmail.xml');
ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_group','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_sysdmailg.xml');
ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_category','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_sysdmailcat.xml');
ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM2','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_txdirectmailM2.xml');
ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM3','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_txdirectmailM3.xml');
ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM4','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_txdirectmailM4.xml');
ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM5','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_txdirectmailM5.xml');
ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM6','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_txdirectmailM6.xml');
//old
ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_txdirectmailM1','EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_csh_web_txdirectmail.xml');


if (TYPO3_MODE == 'BE') {
	// add module before 'Help'
	if (!isset($TBE_MODULES['txdirectmailM1']))	{
		$temp_TBE_MODULES = array();
		foreach($TBE_MODULES as $key => $val) {
			if ($key == 'help') {
				$temp_TBE_MODULES['txdirectmailM1'] = '';
				$temp_TBE_MODULES[$key] = $val;
			} else {
				$temp_TBE_MODULES[$key] = $val;
			}
		}

		$TBE_MODULES = $temp_TBE_MODULES;
	}

	ExtensionManagementUtility::addModule('txdirectmailM1', '', '', $extPath . 'mod1/',
		array(
			'script' => '_DISPATCH',
			'access' => 'group,user',
			'name' => 'txdirectmailM1',
			'labels' => array(
				'tabs_images' => array(
					'tab' => 'EXT:direct_mail/Resources/Public/Images/module-directmail.svg',
				),
			'll_ref' => 'LLL:EXT:direct_mail/mod1/locallang_mod.xml',
			),
		)
	);

	ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM2', 'bottom', $extPath . 'mod2/',
		array(
			'script' => '_DISPATCH',
			'access' => 'group,user',
			'name' => 'txdirectmailM1_txdirectmailM2',
			'workspaces' => 'online',
			'labels' => array(
				'tabs_images' => array(
					'tab' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-directmail.svg',
				),
				'll_ref' => 'LLL:EXT:direct_mail/mod2/locallang_mod.xml',
			),
			'navigationFrameModule' => 'txdirectmailM1',
			'navigationFrameModuleParameters' => array('currentModule' => 'txdirectmailM1_txdirectmailM2'),
		)
	);

	ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM3', 'bottom', $extPath . 'mod3/',
		array(
			'script' => '_DISPATCH',
			'access' => 'group,user',
			'name' => 'txdirectmailM1_txdirectmailM3',
			'workspaces' => 'online',
			'labels' => array(
				'tabs_images' => array(
					'tab' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-recipient-list.svg',
				),
				'll_ref' => 'LLL:EXT:direct_mail/mod3/locallang_mod.xml',
			),
			'navigationFrameModule' => 'txdirectmailM1',
			'navigationFrameModuleParameters' => array('currentModule' => 'txdirectmailM1_txdirectmailM3'),
		)
	);

	ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM4', 'bottom', $extPath . 'mod4/',
		array(
			'script' => '_DISPATCH',
			'access' => 'group,user',
			'name' => 'txdirectmailM1_txdirectmailM4',
			'workspaces' => 'online',
			'labels' => array(
				'tabs_images' => array(
					'tab' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-statistics.svg',
				),
				'll_ref' => 'LLL:EXT:direct_mail/mod4/locallang_mod.xml',
			),
			'navigationFrameModule' => 'txdirectmailM1',
			'navigationFrameModuleParameters' => array('currentModule' => 'txdirectmailM1_txdirectmailM4'),
		)
	);

	ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM5', 'bottom', $extPath . 'mod5/',
		array(
			'script' => '_DISPATCH',
			'access' => 'group,user',
			'name' => 'txdirectmailM1_txdirectmailM5',
			'workspaces' => 'online',
			'labels' => array(
				'tabs_images' => array(
					'tab' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-mailer-engine.svg',
				),
				'll_ref' => 'LLL:EXT:direct_mail/mod5/locallang_mod.xml',
			),
			'navigationFrameModule' => 'txdirectmailM1',
			'navigationFrameModuleParameters' => array('currentModule' => 'txdirectmailM1_txdirectmailM5'),
		)
	);


	ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM6', 'bottom', $extPath . 'mod6/',
		array(
			'script' => '_DISPATCH',
			'access' => 'group,user',
			'name' => 'txdirectmailM1_txdirectmailM6',
			'workspaces' => 'online',
			'labels' => array(
				'tabs_images' => array(
					'tab' => 'EXT:direct_mail/Resources/Public/Images/module-directmail-configuration.svg',
				),
				'll_ref' => 'LLL:EXT:direct_mail/mod6/locallang_mod.xml',
			),
			'navigationFrameModule' => 'txdirectmailM1',
			'navigationFrameModuleParameters' => array('currentModule' => 'txdirectmailM1_txdirectmailM6'),
		)
	);
}

SpriteManager::addTcaTypeIcon(
	'pages',
	'contains-dmail',
	ExtensionManagementUtility::extRelPath($_EXTKEY) . 'res/gfx/ext_icon_dmail_folder.gif'
);

if (VersionNumberUtility::convertVersionNumberToInteger(ExtensionManagementUtility::getExtensionVersion('tt_address')) <= VersionNumberUtility::convertVersionNumberToInteger('2.3.5')) {
	include_once(ExtensionManagementUtility::extPath($_EXTKEY)."Configuration/TCA/Overrides/tt_address.php");
}
