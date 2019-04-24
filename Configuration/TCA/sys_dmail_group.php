<?php

return [
    'ctrl' => [
        'label' => 'title',
        'default_sortby' => 'ORDER BY title',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group',
        'delete' => 'deleted',
        'iconfile' => 'EXT:direct_mail/Resources/Public/Icons/mailgroup.gif',
        'type' => 'type',
    ],
    'interface' => [
        'showRecordFieldList' => 'sys_language_uidtype,title,description',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    ['LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.default_value', 0],
                ],
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.title',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'eval' => 'trim,required',
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.description',
            'config' => [
                'type' => 'text',
                'cols' => '40',
                'rows' => '3',
            ],
        ],
        'type' => [
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'config' => [
                'type' => 'select',
                'items' => [
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.0', '0'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.1', '1'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.2', '2'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.3', '3'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.4', '4'],
                ],
                'default' => '0',
            ],
        ],
        'static_list' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.static_list',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tt_address,fe_users,fe_groups',
                'MM' => 'sys_dmail_group_mm',
                'size' => '20',
                'maxitems' => '100000',
                'minitems' => '0',
                'show_thumbs' => '1',
                'wizards' => [
                    'suggest' => [
                        'type' => 'suggest',
                    ],
                ],
            ],
        ],
        'pages' => [
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.startingpoint',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '3',
                'maxitems' => '22',
                'minitems' => '0',
                'show_thumbs' => '1',
                'wizards' => [
                    'suggest' => [
                        'type' => 'suggest',
                    ],
                ],
            ],
        ],
        'mail_groups' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.mail_groups',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'sys_dmail_group',
                'size' => '3',
                'maxitems' => '22',
                'minitems' => '0',
                'show_thumbs' => '1',
                'wizards' => [
                    'suggest' => [
                        'type' => 'suggest',
                    ],
                ],
            ],
        ],
        'recursive' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.recursive',
            'config' => [
                'type' => 'check',
            ],
        ],
        'whichtables' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.0', ''],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.1', ''],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.2', ''],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.3', ''],
                ],
                'cols' => 2,
                'default' => 1,
            ],
        ],
        'list' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.list',
            'config' => [
                'type' => 'text',
                'cols' => '48',
                'rows' => '10',
            ],
        ],
        'csv' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv',
            'config' => [
                'type' => 'select',
                'items' => [
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.0', '0'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.1', '1'],
                ],
                'default' => '0',
            ],
        ],
        'select_categories' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.select_categories',
            'config' => [
                'type' => 'select',
                'foreign_table' => 'sys_dmail_category',
                'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.sorting',
                'itemsProcFunc' => 'DirectMailTeam\\DirectMail\\SelectCategories->get_localized_categories',
                'itemsProcFunc_config' => [
                    'table' => 'sys_dmail_category',
                    'indexField' => 'uid',
                ],
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 60,
                'renderMode' => 'checkbox',
                'MM' => 'sys_dmail_group_category_mm',
            ],
        ],
        'query' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.query',
            'config' => [
                'type' => 'text',
                'cols' => '48',
                'rows' => '10',
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,pages,recursive,whichtables,select_categories'],
        '1' => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,list,csv'],
        '2' => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,static_list'],
        '3' => ['showitem' => 'type, sys_language_uid, title, description'],
        '4' => ['showitem' => 'type, sys_language_uid, title, description, --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.advanced,mail_groups'],
    ],
];
