<?php

return array(
    'ctrl' => array(
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_category',
        'label' => 'category',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l18n_parent',
        'transOrigDiffSourceField' => 'l18n_diffsource',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden',
        ),
        'iconfile' => TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('direct_mail') . 'Resources/Public/Icons/icon_tx_directmail_category.gif',
    ),
    'interface' => array(
        'showRecordFieldList' => 'hidden,category'
    ),
    'feInterface' => $GLOBALS['TCA']['sys_dmail_category']['feInterface'],
    'columns' => array(
        'sys_language_uid' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => array(
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => array(
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages', -1),
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.default_value', 0)
                )
            )
        ),
        'l18n_parent' => array(
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
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
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => array(
                'type' => 'check',
                'default' => '0'
            )
        ),
        'category' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_category.category',
            'config' => array(
                'type' => 'input',
                'size' => '30',
            )
        ),
        'old_cat_number' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_category.old_cat_number',
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
