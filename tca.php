<?
if (!defined ('TYPO3_MODE'))     die ('Access denied.');

$TCA['sys_dmail_category'] = Array (
	'ctrl' => $TCA['sys_dmail_category']['ctrl'],
	'interface' => Array (
        	'showRecordFieldList' => 'hidden,category'
	),
	'feInterface' => $TCA['sys_dmail_category']['feInterface'],
	'columns' => Array (
		'hidden' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.php:LGL.hidden',
			'config' => Array (
				'type' => 'check',
				'default' => '0'
			)
		),
		'category' => Array (
			'exclude' => 1,
			'label' => 'LLL:EXT:direct_mail/locallang_tca.php:sys_dmail_category.category',
			'config' => Array (
				'type' => 'input',
				'size' => '30',
			)
		),
	),
	'types' => Array (
		'0' => Array('showitem' => 'hidden;;1;;1-1-1, category')
	),
	'palettes' => Array (
		'1' => Array('showitem' => '')
		)
	);
?>
