<?php
/**
 *
 * @package TYPO3
 * @subpackage tx_directmail
 * @version $Id$
 */

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

t3lib_extMgm::addStaticFile($_EXTKEY,'static/boundaries/','Direct Mail Content Boundaries');
t3lib_extMgm::addStaticFile($_EXTKEY,'static/plaintext/', 'Direct Mail Plain text');
t3lib_extMgm::addStaticFile($_EXTKEY,'static/tt_news_plaintext/', 'Direct Mail News Plain text');

	// Category field disabled by default in backend forms.
t3lib_extMgm::addPageTSConfig('TCEFORM.tt_content.module_sys_dmail_category.disabled = 1
TCEFORM.tt_address.module_sys_dmail_category.disabled = 1
TCEFORM.fe_users.module_sys_dmail_category.disabled = 1
TCEFORM.sys_dmail_group.select_categories.disabled = 1');

require_once(t3lib_extMgm::extPath($_EXTKEY).'/res/scripts/class.tx_directmail_select_categories.php');

/**
 * Setting up the direct mail module
 */

 	// pages modified
t3lib_div::loadTCA('pages');
$TCA['pages']['columns']['module']['config']['items'][] = Array('LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:pages.module.I.5', 'dmail');

 	// tt_content modified
t3lib_div::loadTCA('tt_content');
$tt_content_cols = Array(
	'module_sys_dmail_category' => Array(
		'label' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:sys_dmail_category.category',
		'exclude' => '1',
		'l10n_mode' => 'exclude',
		'config' => Array (
			'type' => 'select',
			'foreign_table' => 'sys_dmail_category',
			'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
			'itemsProcFunc' => 'tx_directmail_select_categories->get_localized_categories',
			'itemsProcFunc_config' => array (
				'table' => 'sys_dmail_category',
				'indexField' => 'uid',
			),
			'size' => 5,
			'minitems' => 0,
			'maxitems' => 60,
			'renderMode' => 'checkbox',
			'MM' => 'sys_dmail_ttcontent_category_mm',
		)
	),
);
t3lib_extMgm::addTCAcolumns('tt_content',$tt_content_cols);
t3lib_extMgm::addToAllTCATypes('tt_content','module_sys_dmail_category;;;;1-1-1');

	// tt_address modified
$tempCols = Array(
	'module_sys_dmail_category' => Array(
		'label' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:module_sys_dmail_group.category',
		'exclude' => '1',
		'config' => Array (
			'type' => 'select',
			'foreign_table' => 'sys_dmail_category',
			'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
			'itemsProcFunc' => 'tx_directmail_select_categories->get_localized_categories',
			'itemsProcFunc_config' => array (
				'table' => 'sys_dmail_category',
				'indexField' => 'uid',
			),
			'size' => 5,
			'minitems' => 0,
			'maxitems' => 60,
			'renderMode' => 'checkbox',
			'MM' => 'sys_dmail_ttaddress_category_mm',
		)
	),
	'module_sys_dmail_html' => Array(
		'label'=>'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:module_sys_dmail_group.htmlemail',
		'exclude' => '1',
		'config'=>Array(
			'type'=>'check'
			)
		)
	);

t3lib_div::loadTCA('tt_address');
t3lib_extMgm::addTCAcolumns('tt_address',$tempCols);
t3lib_extMgm::addToAllTCATypes('tt_address','--div--;Direct mail,module_sys_dmail_category;;;;1-1-1,module_sys_dmail_html');
$TCA['tt_address']['feInterface']['fe_admin_fieldList'].=',module_sys_dmail_category,module_sys_dmail_html';

	// fe_users modified
$tempCols = Array(
	'module_sys_dmail_category' => Array(
		'label' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:module_sys_dmail_group.category',
		'exclude' => '1',
		'config' => Array (
			'type' => 'select',
			'foreign_table' => 'sys_dmail_category',
			'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
			'itemsProcFunc' => 'tx_directmail_select_categories->get_localized_categories',
			'itemsProcFunc_config' => array (
				'table' => 'sys_dmail_category',
				'indexField' => 'uid',
			),
			'size' => 5,
			'minitems' => 0,
			'maxitems' => 60,
			'renderMode' => 'checkbox',
			'MM' => 'sys_dmail_feuser_category_mm',
		)
	),
	'module_sys_dmail_html' => Array(
		'label'=>'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:module_sys_dmail_group.htmlemail',
		'exclude' => '1',
		'config'=>Array(
			'type'=>'check'
		)
	)
);

t3lib_div::loadTCA('fe_users');
t3lib_extMgm::addTCAcolumns('fe_users',$tempCols);
$TCA['fe_users']['feInterface']['fe_admin_fieldList'].=',module_sys_dmail_category,module_sys_dmail_html';
t3lib_extMgm::addToAllTCATypes('fe_users','--div--;Direct mail,module_sys_dmail_category;;;;1-1-1,module_sys_dmail_html');

// ******************************************************************
// sys_dmail
// ******************************************************************
$TCA['sys_dmail'] = Array (
	'ctrl' => Array (
		'label' => 'subject',
		'default_sortby' => 'ORDER BY tstamp DESC',
		'tstamp' => 'tstamp',
		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.xml:LGL.prependAtCopy',
		'title' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:sys_dmail',
		'delete' => 'deleted',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'res/gfx/mail.gif',
		'type' => 'type',
		'useColumnsForDefaultValues' => 'from_email,from_name,replyto_email,replyto_name,organisation,priority,encoding,charset,sendOptions,type',
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
	)
);

// ******************************************************************
// Categories
// ******************************************************************
$TCA['sys_dmail_category'] = Array (
	'ctrl' => Array (
		'title' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:sys_dmail_category',
		'label' => 'category',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'languageField' => 'sys_language_uid',
		'transOrigPointerField' => 'l18n_parent',
		'transOrigDiffSourceField' => 'l18n_diffsource',
		'sortby' => 'sorting',
		'delete' => 'deleted',
		'enablecolumns' => Array (
			'disabled' => 'hidden',
			),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'res/gfx/icon_tx_directmail_category.gif',
		)
);

// ******************************************************************
// sys_dmail_group
// ******************************************************************
$TCA['sys_dmail_group'] = Array (
	'ctrl' => Array (
		'label' => 'title',
		'default_sortby' => 'ORDER BY title',
		'tstamp' => 'tstamp',
		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.xml:LGL.prependAtCopy',
		'title' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.xml:sys_dmail_group',
		'delete' => 'deleted',
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'res/gfx/mailgroup.gif',
		'type' => 'type',
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
	)
);

t3lib_extMgm::addLLrefForTCAdescr('sys_dmail','EXT:'.$_EXTKEY.'/locallang/locallang_csh_sysdmail.xml');
t3lib_extMgm::addLLrefForTCAdescr('sys_dmail_group','EXT:'.$_EXTKEY.'/locallang/locallang_csh_sysdmailg.xml');
t3lib_extMgm::addLLrefForTCAdescr('sys_dmail_category','EXT:'.$_EXTKEY.'/locallang/locallang_csh_sysdmailcat.xml');
t3lib_extMgm::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM2','EXT:'.$_EXTKEY.'/locallang/locallang_csh_txdirectmailM2.xml');
t3lib_extMgm::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM3','EXT:'.$_EXTKEY.'/locallang/locallang_csh_txdirectmailM3.xml');
t3lib_extMgm::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM4','EXT:'.$_EXTKEY.'/locallang/locallang_csh_txdirectmailM4.xml');
t3lib_extMgm::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM5','EXT:'.$_EXTKEY.'/locallang/locallang_csh_txdirectmailM5.xml');
t3lib_extMgm::addLLrefForTCAdescr('_MOD_txdirectmailM1_txdirectmailM6','EXT:'.$_EXTKEY.'/locallang/locallang_csh_txdirectmailM6.xml');
//old
t3lib_extMgm::addLLrefForTCAdescr('_MOD_web_txdirectmailM','EXT:'.$_EXTKEY.'/locallang/locallang_csh_web_txdirectmail.xml');


if (TYPO3_MODE=='BE')   {
	$extPath = t3lib_extMgm::extPath($_EXTKEY);
	
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
	t3lib_extMgm::addModule('txdirectmailM1', '', '', $extPath.'mod1/');
	t3lib_extMgm::addModule('txdirectmailM1', 'txdirectmailM2', 'bottom', $extPath.'mod2/');
	t3lib_extMgm::addModule('txdirectmailM1', 'txdirectmailM3', 'bottom', $extPath.'mod3/');
	t3lib_extMgm::addModule('txdirectmailM1', 'txdirectmailM4', 'bottom', $extPath.'mod4/');
	t3lib_extMgm::addModule('txdirectmailM1', 'txdirectmailM5', 'bottom', $extPath.'mod5/');
	t3lib_extMgm::addModule('txdirectmailM1', 'txdirectmailM6', 'bottom', $extPath.'mod6/');
	
	//t3lib_extMgm::addModule('web','txdirectmailM2','',t3lib_extMgm::extPath($_EXTKEY).'mod1/');
}

?>
