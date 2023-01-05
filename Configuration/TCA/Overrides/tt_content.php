<?php

defined('TYPO3') || die();

// tt_content modified
$ttContentCols = [
    'module_sys_dmail_category' => [
        'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_category.category',
        'exclude' => '1',
        'l10n_mode' => 'exclude',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'renderMode' => 'checkbox',
            'foreign_table' => 'sys_dmail_category',
            'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.sorting',
            'itemsProcFunc' => DirectMailTeam\DirectMail\SelectCategories::class . '->get_localized_categories',
            'itemsProcFunc_config' => [
                'table' => 'sys_dmail_category',
                'indexField' => 'uid',
            ],
            'size' => 5,
            'minitems' => 0,
            'maxitems' => 60,
            'MM' => 'sys_dmail_ttcontent_category_mm',
        ],
    ],
];
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', $ttContentCols);
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('tt_content', 'module_sys_dmail_category');
