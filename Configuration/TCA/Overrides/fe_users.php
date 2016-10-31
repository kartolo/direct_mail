<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

// fe_users modified
$feUsersCols = array(
    'module_sys_dmail_newsletter' => array(
        'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.newsletter',
        'exclude' => '1',
        'config' => array(
            'type' => 'check',
        ),
    ),
    'module_sys_dmail_category' => array(
        'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.category',
        'exclude' => '1',
        'config' => array(
            'type' => 'select',
            'foreign_table' => 'sys_dmail_category',
            'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.sorting',
            'itemsProcFunc' => 'DirectMailTeam\\DirectMail\\SelectCategories->get_localized_categories',
            'itemsProcFunc_config' => array(
                'table' => 'sys_dmail_category',
                'indexField' => 'uid',
            ),
            'size' => 5,
            'minitems' => 0,
            'maxitems' => 60,
            'renderMode' => 'checkbox',
            'MM' => 'sys_dmail_feuser_category_mm',
        ),
    ),
    'module_sys_dmail_html' => array(
        'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:module_sys_dmail_group.htmlemail',
        'exclude' => '1',
        'config' => array(
            'type' => 'check',
        ),
    ),
);

TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $feUsersCols);
$GLOBALS['TCA']['fe_users']['feInterface']['fe_admin_fieldList'] .= ',module_sys_dmail_newsletter,module_sys_dmail_category,module_sys_dmail_html';
TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCATypes('fe_users', '--div--;Direct mail,module_sys_dmail_newsletter;;;;1-1-1,module_sys_dmail_category,module_sys_dmail_html');
