<?php

return array(
    'ctrl' => array(
        'label' => 'title',
        'default_sortby' => 'ORDER BY title',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:lang/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group',
        'delete' => 'deleted',
        'iconfile' => TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('direct_mail') . 'Resources/Public/Icons/mailgroup.gif',
        'type' => 'type',
    ),
    'interface' => array(
        'showRecordFieldList' => 'sys_language_uidtype,title,description'
    ),
    'columns' => array(
        'sys_language_uid' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => array(
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => array(
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages', -1),
                    array('LLL:EXT:lang/locallang_general.xlf:LGL.default_value', 0)
                ),
            ),
        ),
        'title' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.title',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'eval' => 'trim,required'
            )
        ),
        'description' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.description',
            'config' => array(
                'type' => 'text',
                'cols' => '40',
                'rows' => '3'
            )
        ),
        'type' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.type',
            'config' => array(
                'type' => 'select',
                'items' => array(
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.0', '0'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.1', '1'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.2', '2'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.3', '3'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.4', '4')
                ),
                'default' => '0'
            )
        ),
        'static_list' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.static_list',
            'config' => array(
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tt_address,fe_users,fe_groups',
                'MM' => 'sys_dmail_group_mm',
                'size' => '20',
                'maxitems' => '100000',
                'minitems' => '0',
                'show_thumbs' => '1',
                'wizards' => array(
                    'suggest' => array(
                        'type' => 'suggest',
                    ),
                ),
            )
        ),
        'pages' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.startingpoint',
            'config' => array(
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '3',
                'maxitems' => '22',
                'minitems' => '0',
                'show_thumbs' => '1',
                'wizards' => array(
                    'suggest' => array(
                        'type' => 'suggest',
                    ),
                ),
            )
        ),
        'mail_groups' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.mail_groups',
            'config' => array(
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'sys_dmail_group',
                'size' => '3',
                'maxitems' => '22',
                'minitems' => '0',
                'show_thumbs' => '1',
                'wizards' => array(
                    'suggest' => array(
                        'type' => 'suggest',
                    ),
                ),
            )
        ),
        'recursive' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.recursive',
            'config' => array(
                'type' => 'check'
            )
        ),
        'whichtables' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables',
            'config' => array(
                'type' => 'check',
                'items' => array(
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.0', ''),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.1', ''),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.2', ''),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.3', ''),
                ),
                'cols' => 2,
                'default' => 1
            )
        ),
        'list' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.list',
            'config' => array(
                'type' => 'text',
                'cols' => '48',
                'rows' => '10'
            )
        ),
        'csv' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv',
            'config' => array(
                'type' => 'select',
                'items' => array(
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.0', '0'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.1', '1')
                ),
                'default' => '0'
            )
        ),
        'select_categories' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.select_categories',
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
                'MM' => 'sys_dmail_group_category_mm',
            )
        ),
        'query' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.query',
            'config' => array(
                'type' => 'text',
                'cols' => '48',
                'rows' => '10'
            )
        )
    ),
    'types' => array(
        '0' => array('showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,pages,recursive,whichtables,select_categories'),
        '1' => array('showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,list,csv'),
        '2' => array('showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,static_list'),
        '3' => array('showitem' => 'type, sys_language_uid, title, description'),
        '4' => array('showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,mail_groups')
    )
);
