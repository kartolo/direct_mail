<?php
/**
 *
 * @package TYPO3
 * @subpackage tx_directmail
 * @version $Id: tca.php 34990 2010-06-28 12:23:27Z ivankartolo $
 */

if (!defined ('TYPO3_MODE'))     die ('Access denied.');

// ******************************************************************
// sys_dmail
// ******************************************************************
$TCA['sys_dmail'] = array(
	'ctrl' => $TCA['sys_dmail']['ctrl'],
	'interface' => array(
		'showRecordFieldList' => 'type,plainParams,HTMLParams,subject,from_name,from_email,replyto_name,replyto_email,return_path,organisation,attachment,priority,encoding,charset,sendOptions,includeMedia,flowedFormat,issent,renderedsize,use_domain,use_rdct,long_link_mode,authcode_fieldList'
	),
	'columns' => array(
		'subject' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.subject',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'max' => '120',
				'eval' => 'trim,required'
			)
		),
		'page' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.page',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => '1',
				'maxitems' => 1,
				'minitems' => 0
			)
		),
		'from_email' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.from_email',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'max' => '80',
				'eval' => 'trim,required'
			)
		),
		'from_name' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.from_name',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'replyto_email' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.replyto_email',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'replyto_name' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.replyto_name',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'return_path' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.return_path',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'organisation' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.organisation',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'encoding' => array(
			'label' =>  'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.transfer_encoding',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('quoted-printable','quoted-printable'),
					array('base64','base64'),
					array('8bit','8bit'),
					),
				'default' => 'quoted-printable'
			)
		),
		'charset' => array(
			'label' =>  'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.charset',
			'config' => array(
				'type' => 'input',
				'size' => '15',
				'max' => '20',
				'eval' => 'trim',
				'default' => 'iso-8859-1'
			)
		),
		'priority' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.priority',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.priority.I.0', '5'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.priority.I.1', '3'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.priority.I.2', '1')
				),
				'default' => '3'
			)
		),
		'sendOptions' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.sendOptions',
			'config' => array(
				'type' => 'check',
				'items' => array(
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.sendOptions.I.0', ''),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.sendOptions.I.1', '')
				),
				'cols' => '2',
				'default' => '3'
			)
		),
		'includeMedia' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.includeMedia',
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'flowedFormat' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.flowedFormat',
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'HTMLParams' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.HTMLParams',
			'config' => array(
				'type' => 'input',
				'size' => '15',
				'max' => '80',
				'eval' => 'trim',
				'default' => ''
			)
		),
		'plainParams' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.plainParams',
			'config' => array(
				'type' => 'input',
				'size' => '15',
				'max' => '80',
				'eval' => 'trim',
				'default' => '&type=99'
			)
		),
		'issent' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.issent',
			'exclude' => '1',
			'config' => array(
				'type' => 'none',
				'size' => 2,
			)
		),
		'scheduled' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.scheduled',
			'exclude' => '1',
			'config' => array(
				'type' => 'none',
				'cols' => '30',
				'format' => 'datetime',
				'default' => 0
			)
		),
		'scheduled_begin' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.scheduled_begin',
			'config' => array(
				'type' => 'none',
				'cols' => '15',
				'format' => 'datetime',
				'default' => 0
			)
		),
		'scheduled_end' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.scheduled_end',
			'config' => array(
				'type' => 'none',
				'cols' => '15',
				'format' => 'datetime',
				'default' => 0
			)
		),
		'use_domain' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.use_domain',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_domain',
				'items' => array(
					array('', 0)
				),
				'size' => '1',
				'maxitems' => 1,
				'minitems' => 0
			)
		),
		'use_rdct' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.use_rdct',
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'long_link_rdct_url' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.long_link_rdct_url',
			'config' => array(
				'type' => 'input',
				'size' => '15',
				'max' => '80',
				'eval' => 'trim',
				'default' => ''
			)
		),
		'long_link_mode' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.long_link_mode',
			'config' => array(
				'type' => 'check'
			)
		),
		'authcode_fieldList' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.authcode_fieldList',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'eval' => 'trim',
				'max' => '80',
				'default' => 'uid,name,email,password'
			)
		),
		'renderedsize' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.renderedsize',
			'exclude' => '1',
			'config' => array(
				'type' => 'none'
			)
		),
		'attachment' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.attachment',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'file',
				'allowed' => '',	// Must be empty for disallowed to work.
				'disallowed' => 'php,php3',
				'max_size' => '10000',
				'uploadfolder' => 'uploads/tx_directmail',
				'show_thumbs' => '0',
				'size' => '3',
				'maxitems' => '5',
				'minitems' => '0'
			)
		),
		'type' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.type',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.type.I.0', '0'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.type.I.1', '1'),
					array('Draft of internal page', '2'),
					array('Draft of external URL', '3')
				),
				'default' => '0'
			)
		)
	),
	'types' => array(
		'0' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab1, type;;;;1-1-1, page, plainParams, HTMLParams, use_domain, attachment,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		'),
		'1' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab1, type;;;;1-1-1, page, plainParams;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.HTMLParams.ALT.1, attachment,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		'),
		'2' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab1, type;;;;1-1-1, page, plainParams, HTMLParams, use_domain, attachment,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		'),
		'3' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab1, type;;;;1-1-1, page, plainParams;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.HTMLParams.ALT.1, attachment,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		')
	),
	'palettes' => array(
		'1'     => array('showitem' => 'scheduled_begin, scheduled_end, issent'),
		'from'  => array('showitem' => 'from_email, from_name'),
		'reply' => array('showitem' => 'replyto_email, replyto_name'),
	)
);

// ******************************************************************
// Categories
// ******************************************************************
$TCA['sys_dmail_category'] = array(
	'ctrl' => $TCA['sys_dmail_category']['ctrl'],
	'interface' => array(
        	'showRecordFieldList' => 'hidden,category'
	),
	'feInterface' => $TCA['sys_dmail_category']['feInterface'],
	'columns' => array(
		'sys_language_uid' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.xml:LGL.allLanguages',-1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.default_value',0)
				)
			)
	    	),
		'l18n_parent' => array(
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.l18n_parent',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('', 0),
				),
				'foreign_table' => 'sys_dmail_category',
				'foreign_table_where' => 'AND sys_dmail_category.pid=###CURRENT_PID### AND sys_dmail_category.sys_language_uid IN (-1,0)',
			)
		),
		'l18n_diffsource' => array(
			'config' => array(
	    			'type' => 'passthrough'
			)
	    	),
		'hidden' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config' => array(
				'type' => 'check',
				'default' => '0'
			)
		),
		'category' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_category.category',
			'config' => array(
				'type' => 'input',
				'size' => '30',
			)
		),
		'old_cat_number' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_category.old_cat_number',
			'l10n_mode' => 'exclude',
			'config' => array(
				'type' => 'input',
				'size' => '2',
				'eval' => 'trim',
				'max' => '2',
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'sys_language_uid;;;;1-1-1, l18n_parent, l18n_diffsource,hidden;;1;;1-1-1, category')
	),
	'palettes' => array(
		'1' => array('showitem' => '')
	)
);

// ******************************************************************
// sys_dmail_group
// ******************************************************************
$TCA['sys_dmail_group'] = array(
	'ctrl' => $TCA['sys_dmail_group']['ctrl'],
	'interface' => array(
		'showRecordFieldList' => 'type,title,description'
	),
	'columns' => array(
		'title' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.title',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'max' => '120',
				'eval' => 'trim,required'
			)
		),
		'description' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.description',
			'config' => array(
				'type' => 'text',
				'cols' => '40',
				'rows' => '3'
			)
		),
		'type' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.type',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.type.I.0', '0'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.type.I.1', '1'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.type.I.2', '2'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.type.I.3', '3'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.type.I.4', '4')
				),
				'default' => '0'
			)
		),
		'static_list' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.static_list',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'tt_address,fe_users,fe_groups',
				'MM' => 'sys_dmail_group_mm',
				'size' => '20',
				'maxitems' => '100000',
				'minitems' => '0',
				'show_thumbs' => '1'
			)
		),
		'pages' => array(
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.startingpoint',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
					'allowed' => 'pages',
				'size' => '3',
				'maxitems' => '22',
				'minitems' => '0',
				'show_thumbs' => '1'
			)
		),
		'mail_groups' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.mail_groups',
			'config' => array(
				'type' => 'group',
				'internal_type' => 'db',
					'allowed' => 'sys_dmail_group',
				'size' => '3',
				'maxitems' => '22',
				'minitems' => '0',
				'show_thumbs' => '1'
			)
		),
		'recursive' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.recursive',
			'config' => array(
				'type' => 'check'
			)
		),
		'whichtables' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.whichtables',
			'config' => array(
				'type' => 'check',
				'items' => array(
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.whichtables.I.0', ''),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.whichtables.I.1', ''),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.whichtables.I.2', ''),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.whichtables.I.3', ''),
				),
				'cols' => 2,
				'default' => 1
			)
		),
		'list' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.list',
			'config' => array(
				'type' => 'text',
				'cols' => '48',
				'rows' => '10'
			)
		),
		'csv' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.csv',
			'config' => array(
				'type' => 'select',
				'items' => array(
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.csv.I.0', '0'),
					array('LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.csv.I.1', '1')
				),
				'default' => '0'
			)
		),
		'select_categories' => array(
			'label' => 'LLL:EXT:direct_mail/locallang_tca.xml:sys_dmail_group.select_categories',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_dmail_category',
				'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.sorting',
				'itemsProcFunc' => 'tx_directmail_select_categories->get_localized_categories',
				'itemsProcFunc_config' => array(
					'table' => 'sys_dmail_category',
					'indexField' => 'uid',
				),
				'size' => 5,
				'minitems' => 0,
				'maxitems' => 60,
				'renderMode' => 'checkbox',
				'MM' => 'sys_dmail_group_category_mm',
			)
		)

	),
	'types' => array(
		'0' => array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,pages;;;;5-5-5,recursive,whichtables,select_categories'),
		'1' => array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,list;;;;5-5-5,csv'),
		'2' => array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,static_list;;;;5-5-5'),
		'3' => array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description'),
		'4' => array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,mail_groups;;;;5-5-5')
	)
);

?>