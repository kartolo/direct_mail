<?php

use TYPO3\CMS\Core\Utility\VersionNumberUtility;

if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$extPath = TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY);

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/boundaries/','Direct Mail Content Boundaries');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/plaintext/', 'Direct Mail Plain text');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/tt_news_plaintext/', 'Direct Mail News Plain text');

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
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_sysdmail.xml');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_group','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_sysdmailg.xml');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_category','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_sysdmailcat.xml');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM2','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_txdirectmailM2.xml');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM3','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_txdirectmailM3.xml');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM4','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_txdirectmailM4.xml');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM5','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_txdirectmailM5.xml');
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM6','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_txdirectmailM6.xml');
//old
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_txdirectmailM','EXT:'.$_EXTKEY.'/Resources/Private/Language/locallang_csh_web_txdirectmail.xml');


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
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule('txdirectmailM1', '', '', $extPath.'mod1/');
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM2', 'bottom', $extPath.'mod2/');
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM3', 'bottom', $extPath.'mod3/');
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM4', 'bottom', $extPath.'mod4/');
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM5', 'bottom', $extPath.'mod5/');
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule('txdirectmailM1', 'txdirectmailM6', 'bottom', $extPath.'mod6/');
}

\TYPO3\CMS\Backend\Sprite\SpriteManager::addTcaTypeIcon(
	'pages',
	'contains-dmail',
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'res/gfx/ext_icon_dmail_folder.gif'
);

if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getExtensionVersion('tt_address')) <= VersionNumberUtility::convertVersionNumberToInteger('2.3.5')) {
	include_once(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY)."Configuration/TCA/Overrides/tt_address.php");
}

?>
