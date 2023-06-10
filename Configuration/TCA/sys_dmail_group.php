<?php

return [
    'ctrl' => [
        'label' => 'title',
        'default_sortby' => 'ORDER BY title',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group',
        'delete' => 'deleted',
        'iconfile' => 'EXT:direct_mail/Resources/Public/Icons/mailgroup.png',
        'type' => 'type',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language'
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.title',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:title.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'description' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.description',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:description.details',
            'config' => [
                'type' => 'text',
                'cols' => '40',
                'rows' => '3',
            ],
        ],
        'type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:type.details',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.0', 'value' => '0'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.1', 'value' => '1'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.2', 'value' => '2'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.3', 'value' => '3'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.type.I.4', 'value' => '4'],
                ],
                'default' => '0',
            ],
        ],
        'static_list' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.static_list',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:static_list.details',
            'config' => [
                'type' => 'group',
                'allowed' => 'tt_address,fe_users,fe_groups',
                'MM' => 'sys_dmail_group_mm',
                'size' => '20',
                'maxitems' => '100000',
                'minitems' => '0',
            ],
        ],
        'pages' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.startingpoint',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:pages.details',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => '3',
                'maxitems' => '22',
                'minitems' => '0',
            ],
        ],
        'mail_groups' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.mail_groups',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:mail_groups.details',
            'config' => [
                'type' => 'group',
                'allowed' => 'sys_dmail_group',
                'size' => '3',
                'maxitems' => '22',
                'minitems' => '0',
            ],
        ],
        'recursive' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.recursive',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:recursive.details',
            'config' => [
                'type' => 'check',
            ],
        ],
        'whichtables' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:whichtables.details',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.0', 'value' => ''],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.1', 'value' => ''],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.2', 'value' => ''],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.whichtables.I.3', 'value' => ''],
                ],
                'cols' => 2,
                'default' => 1,
            ],
        ],
        'list' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.list',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:list.details',
            'config' => [
                'type' => 'text',
                'cols' => '48',
                'rows' => '10',
            ],
        ],
        'csv' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:csv.details',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.0', 'value' => '0'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.csv.I.1', 'value' => '1'],
                ],
                'default' => '0',
            ],
        ],
        'select_categories' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.select_categories',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:select_categories.details',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'renderMode' => 'checkbox',
                'foreign_table' => 'sys_dmail_category',
                // TCEFORM.sys_dmail_group.select_categories.PAGE_TSCONFIG_IDLIST = ids
                'foreign_table_where' => 'AND sys_dmail_category.l18n_parent=0 AND sys_dmail_category.pid IN (###PAGE_TSCONFIG_IDLIST###) ORDER BY sys_dmail_category.sorting',
                'itemsProcFunc' => DirectMailTeam\DirectMail\SelectCategories::class . '->getLocalizedCategories',
                'itemsProcFunc_config' => [
                    'table' => 'sys_dmail_category',
                    'indexField' => 'uid',
                ],
                'size' => 5,
                'minitems' => 0,
                'maxitems' => 60,
                'MM' => 'sys_dmail_group_category_mm',
            ],
        ],
        'query' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.query',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:query.details',
            'config' => [
                'type' => 'text',
                'cols' => '48',
                'rows' => '10',
            ],
        ],
        'queryLimit' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.queryLimit',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:queryLimit.details',
            'config' => [
                'type' => 'input',
            ]
        ],
        'queryLimitDisabled' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail_group.queryLimitDisabled',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf:queryLimitDisabled.details',
            'config' => [
                'type' => 'check',
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
