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

/**
 * Setting up the direct mail module
 */

 	// tt_content modified
t3lib_div::loadTCA('tt_content');
$tt_content_cols = Array(
	'module_sys_dmail_category' => Array(
		'label' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:sys_dmail_category.category',
		'exclude' => '1',
		'config' => Array (
			'type' => 'select',
			'foreign_table' => 'sys_dmail_category',
			'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
			'size' => 5,
			'minitems' => 0,
			'maxitems' => 30,
			'MM' => 'sys_dmail_ttcontent_category_mm',
		)
	),
);
t3lib_extMgm::addTCAcolumns('tt_content',$tt_content_cols);
t3lib_extMgm::addToAllTCATypes('tt_content','module_sys_dmail_category;;;;1-1-1');

	// tt_address modified
$tempCols = Array(
	'module_sys_dmail_category' => Array(
		'label' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:module_sys_dmail_group.category',
		'exclude' => '1',
		'config' => Array (
			'type' => 'select',
			'foreign_table' => 'sys_dmail_category',
			'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
			'size' => 5,
			'minitems' => 0,
			'maxitems' => 30,
			'MM' => 'sys_dmail_ttaddress_category_mm',
		)
	),
	'module_sys_dmail_html' => Array(
		'label'=>'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:module_sys_dmail_group.htmlemail',
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
		'label' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:module_sys_dmail_group.category',
		'exclude' => '1',
		'config' => Array (
			'type' => 'select',
			'foreign_table' => 'sys_dmail_category',
			'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.uid',
			'size' => 5,
			'minitems' => 0,
			'maxitems' => 30,
			'MM' => 'sys_dmail_feuser_category_mm',
		)
	),
	'module_sys_dmail_html' => Array(
		'label'=>'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:module_sys_dmail_group.htmlemail',
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
		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.php:LGL.prependAtCopy',
		'title' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:sys_dmail',
		'delete' => 'deleted',
		'iconfile' => 'mail.gif',
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
		'title' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:sys_dmail_category',
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
		'iconfile' => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_directmail_category.gif',
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
		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.php:LGL.prependAtCopy',
		'title' => 'LLL:EXT:'.$_EXTKEY.'/locallang_tca.php:sys_dmail_group',
		'delete' => 'deleted',
		'iconfile' => 'mailgroup.gif',
		'type' => 'type',
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
	)
);

t3lib_extMgm::addLLrefForTCAdescr('sys_dmail','EXT:'.$_EXTKEY.'/locallang_csh_sysdmail.php');
t3lib_extMgm::addLLrefForTCAdescr('sys_dmail_group','EXT:'.$_EXTKEY.'/locallang_csh_sysdmailg.php');

if (TYPO3_MODE=='BE')   {
  t3lib_extMgm::addModule('web','txdirectmailM1','',t3lib_extMgm::extPath($_EXTKEY).'mod/');
}

?>
