<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

/**
 * Setting up the direct mail module
 */
 
 	// tt_content modified
t3lib_div::loadTCA('tt_content');
t3lib_extMgm::addTCAcolumns('tt_content',array(
		'module_sys_dmail_category' => Array('config'=>array('type'=>'passthrough'))
));

	// tt_address modified
t3lib_div::loadTCA('tt_address');
t3lib_extMgm::addTCAcolumns('tt_address',array(
 		'module_sys_dmail_category' => Array('config'=>array('type'=>'passthrough')),
		'module_sys_dmail_html' => Array('config'=>array('type'=>'passthrough'))
));
$TCA['tt_address']['feInterface']['fe_admin_fieldList'].=',module_sys_dmail_category,module_sys_dmail_html';

	// fe_users modified
t3lib_div::loadTCA('fe_users');
t3lib_extMgm::addTCAcolumns('fe_users',array(
		'module_sys_dmail_category' => Array('config'=>array('type'=>'passthrough')),
		'module_sys_dmail_html' => Array('config'=>array('type'=>'passthrough'))
));
$TCA['fe_users']['feInterface']['fe_admin_fieldList'].=',module_sys_dmail_category,module_sys_dmail_html';

	
// ******************************************************************
// sys_dmail
// ******************************************************************
$TCA['sys_dmail'] = Array (
	'ctrl' => Array (
		'label' => 'subject',
		'default_sortby' => 'ORDER BY tstamp DESC',
		'tstamp' => 'tstamp',
		'prependAtCopy' => 'LLL:EXT:lang/locallang_general.php:LGL.prependAtCopy',
		'title' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail',
		'iconfile' => 'mail.gif',
		'type' => 'type',
		'useColumnsForDefaultValues' => 'from_email,from_name,replyto_email,replyto_name,organisation,priority,sendOptions,type'
	),
	'interface' => Array (
		'showRecordFieldList' => 'type,plainParams,HTMLParams,subject,from_name,from_email,replyto_name,replyto_email,organisation,attachment,priority,sendOptions,issent,renderedsize'
	),
	'columns' => Array (	
		'subject' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.subject',
			'config' => Array (
				'type' => 'input',
				'size' => '30',
				'max' => '120',
				'eval' => 'trim,required'
			)
		),
		'page' => Array (	
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.page',
			'config' => Array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => 'pages',
				'size' => '3',
				'maxitems' => 1,
				'minitems' => 0
			)
		),
		'from_email' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.from_email',
			'config' => Array (
				'type' => 'input',	
				'size' => '30',
				'max' => '80',
				'eval' => 'trim,required'
			)
		),
		'from_name' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.from_name',
			'config' => Array (
				'type' => 'input',	
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'replyto_email' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.replyto_email',
			'config' => Array (
				'type' => 'input',	
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'replyto_name' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.replyto_name',
			'config' => Array (
				'type' => 'input',	
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'return_path' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.return_path',
			'config' => Array (
				'type' => 'input',	
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'organisation' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.organisation',
			'config' => Array (
				'type' => 'input',	
				'size' => '30',
				'eval' => 'trim',
				'max' => '80'
			)
		),
		'priority' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.priority',
			'config' => Array (
				'type' => 'select',		
				'items' => Array (
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.priority.I.0', '5'),
					Array('LLL:EXT:lang/locallang_general.php:LGL.normal', '3'),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.priority.I.2', '1')
				),
				'default' => '3'
			)
		),
		'sendOptions' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.sendOptions',
			'config' => Array (
				'type' => 'check',
				'items' => Array (
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.sendOptions.I.0', ''),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.sendOptions.I.1', '')
				),
				'default' => '3'
			)
		),
		'HTMLParams' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.HTMLParams',
			'config' => Array (
				'type' => 'input',	
				'size' => '15',
				'max' => '80',
				'eval' => 'trim',
				'default' => ''
			)
		),
		'plainParams' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.plainParams',
			'config' => Array (
				'type' => 'input',	
				'size' => '15',
				'max' => '80',
				'eval' => 'trim',
				'default' => '&type=99'
			)
		),
		'issent' => Array (	
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.issent',
			'config' => Array (
				'type' => 'none'
			)
		),
		'long_link_rdct_url' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.long_link_rdct_url',
			'config' => Array (
				'type' => 'input',	
				'size' => '15',
				'max' => '80',
				'eval' => 'trim',
				'default' => ''
			)
		),
		'long_link_mode' => Array (	
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.long_link_mode',
			'config' => Array (
				'type' => 'check'
			)
		),
		'renderedsize' => Array (	
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.renderedsize',
			'config' => Array (
				'type' => 'none'
			)
		),
		'attachment' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.attachment',
			'config' => Array (
				'type' => 'group',
				'internal_type' => 'file',
				'allowed' => '',	// Must be empty for disallowed to work.
				'disallowed' => 'php,php3',
				'max_size' => '500',
				'uploadfolder' => 'uploads/dmail_att',
				'show_thumbs' => '0',
				'size' => '3',
				'maxitems' => '5',
				'minitems' => '0'
			)
		),
		'type' => Array (
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.type',
			'config' => Array (
				'type' => 'select',	
				'items' => Array (	
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.type.I.0', '0'),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.type.I.1', '1')
				),
				'default' => '0'
			)
		)
	),
	'types' => Array (	
		'0' => Array('showitem' => 'type;;;;1-1-1, page, plainParams, HTMLParams, --div--, subject;;;;3-3-3, from_email, from_name, replyto_email, replyto_name, return_path, organisation, attachment;;;;4-4-4, priority;;;;5-5-5, sendOptions, issent, renderedsize, long_link_rdct_url, long_link_mode'),
		'1' => Array('showitem' => 'type;;;;1-1-1, 
			plainParams;LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.plainParams.ALT.1,
			HTMLParams;LLL:EXT:direct_mail/locallang_tca.php:sys_dmail.HTMLParams.ALT.1,
			--div--, subject;;;;3-3-3, from_email, from_name, replyto_email, replyto_name, return_path, organisation, attachment;;;;4-4-4, priority;;;;5-5-5, sendOptions, issent, renderedsize, long_link_rdct_url, long_link_mode')
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
		'title' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group',
		'delete' => 'deleted',
		'iconfile' => 'mailgroup.gif',
		'type' => 'type'
	),
	'interface' => Array (
		'showRecordFieldList' => 'type,title,description'
	),
	'columns' => Array (	
		'title' => Array (
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.title',
			'config' => Array (
				'type' => 'input',
				'size' => '30',
				'max' => '120',
				'eval' => 'trim,required'
			)
		),
		'description' => Array (
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.description',
			'config' => Array (
				'type' => 'text',
				'cols' => '40',	
				'rows' => '3'
			)
		),
		'type' => Array (
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.type',
			'config' => Array (
				'type' => 'select',	
				'items' => Array (	
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.type.I.0', '0'),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.type.I.1', '1'),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.type.I.2', '2'),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.type.I.3', '3'),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.type.I.4', '4')
				),
				'default' => '0'
			)
		),
		'static_list' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.static_list',
			'config' => Array (
				'type' => 'group',
				'internal_type' => 'db',
					'allowed' => 'tt_address,fe_users',
					'MM' => 'sys_dmail_group_mm',
				'size' => '20',
				'maxitems' => '100000',
				'minitems' => '0',
				'show_thumbs' => '1'
			)
		),
		'pages' => Array (
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.startingpoint',
			'config' => Array (
				'type' => 'group',
				'internal_type' => 'db',
					'allowed' => 'pages',
				'size' => '3',
				'maxitems' => '22',
				'minitems' => '0',
				'show_thumbs' => '1'
			)
		),
		'mail_groups' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.mail_groups',
			'config' => Array (
				'type' => 'group',
				'internal_type' => 'db',
					'allowed' => 'sys_dmail_group',
				'size' => '3',
				'maxitems' => '22',
				'minitems' => '0',
				'show_thumbs' => '1'
			)
		),
		'recursive' => Array (
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.recursive',
			'config' => Array (
				'type' => 'check'
			)
		),
		'whichtables' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.whichtables',
			'config' => Array (
				'type' => 'check',
				'items' => Array (	
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.whichtables.I.0', ''),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.whichtables.I.1', ''),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.whichtables.I.2', ''),
				),
				'cols' => 3,
				'default' => 1
			)
		),
		'list' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.list',
			'config' => Array (
				'type' => 'text',
				'cols' => '48',	
				'rows' => '10'
			)
		),
		'csv' => Array (
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.type',
			'config' => Array (
				'type' => 'select',	
				'items' => Array (
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.csv.I.0', '0'),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.csv.I.1', '1')
				),
				'default' => '0'
			)
		),
		'select_categories' => Array (
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories',
			'config' => Array (
				'type' => 'check',
				'items' => Array (	
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.0',0),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.1',1),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.2',2),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.3',3),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.4',4),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.5',5),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.6',6),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.7',7),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.8',8),
					Array('LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_group.select_categories.I.9',9)
				),
				'cols' => 5
			)
		)
	),
	'types' => Array (	
		'0' => Array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,pages;;;;5-5-5,recursive,whichtables,select_categories'),
		'1' => Array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,list;;;;5-5-5,csv'),
		'2' => Array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,static_list;;;;5-5-5'),
		'3' => Array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description'),
		'4' => Array('showitem' => 'type;;;;1-1-1, title;;;;3-3-3, description, --div--,mail_groups;;;;5-5-5')
	)
);


t3lib_extMgm::addLLrefForTCAdescr('sys_dmail','EXT:direct_mail/locallang_csh_sysdmail.php');
t3lib_extMgm::addLLrefForTCAdescr('sys_dmail_group','EXT:direct_mail/locallang_csh_sysdmailg.php');

?>