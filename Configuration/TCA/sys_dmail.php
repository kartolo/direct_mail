<?php
defined('TYPO3_MODE') or die();
return [
    'ctrl' => [
        'label' => 'subject',
        'default_sortby' => 'ORDER BY tstamp DESC',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:lang/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail',
        'delete' => 'deleted',
        'iconfile' => 'EXT:direct_mail/Resources/Public/Icons/mail.gif',
        'type' => 'type',
        'useColumnsForDefaultValues' => 'from_email,from_name,replyto_email,replyto_name,organisation,priority,encoding,charset,sendOptions,type',
        'dividers2tabs' => true,
        'languageField' => 'sys_language_uid'
    ],
    'interface' => [
        'showRecordFieldList' => 'sys_language_uid,type,plainParams,HTMLParams,subject,from_name,from_email,replyto_name,replyto_email,return_path,organisation,attachment,priority,encoding,charset,sendOptions,includeMedia,flowedFormat,issent,renderedsize,use_domain,use_rdct,long_link_mode,authcode_fieldList'
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => [
                'default' => '0',
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    ['LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:lang/locallang_general.xlf:LGL.default_value', 0]
                ],
            ],
        ],
        'subject' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.subject',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'eval' => 'trim,required'
            ]
        ],
        'page' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.page',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '1',
                'maxitems' => 1,
                'minitems' => 0,
                'wizards' => [
                    'suggest' => [
                        'type' => 'suggest',
                    ],
                ],
            ]
        ],
        'from_email' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_email',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '80',
                'eval' => 'trim,required'
            ]
        ],
        'from_name' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_name',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            ]
        ],
        'replyto_email' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_email',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            ]
        ],
        'replyto_name' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_name',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            ]
        ],
        'return_path' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.return_path',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            ]
        ],
        'organisation' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.organisation',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            ]
        ],
        'encoding' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.transfer_encoding',
            'config' => [
                'type' => 'select',
                'items' => [
                    ['quoted-printable', 'quoted-printable'],
                    ['base64', 'base64'],
                    ['8bit', '8bit'],
                ],
                'default' => 'quoted-printable'
            ]
        ],
        'charset' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.charset',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '20',
                'eval' => 'trim',
                'default' => 'iso-8859-1'
            ]
        ],
        'priority' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority',
            'config' => [
                'type' => 'select',
                'items' => [
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.0', '5'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.1', '3'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.2', '1']
                ],
                'default' => '3'
            ]
        ],
        'sendOptions' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.0', ''],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.1', '']
                ],
                'cols' => '2',
                'default' => '3'
            ]
        ],
        'includeMedia' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.includeMedia',
            'config' => [
                'type' => 'check',
                'default' => '0'
            ]
        ],
        'flowedFormat' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.flowedFormat',
            'config' => [
                'type' => 'check',
                'default' => '0'
            ]
        ],
        'HTMLParams' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => ''
            ]
        ],
        'plainParams' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '&type=99'
            ]
        ],
        'issent' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.issent',
            'exclude' => '1',
            'config' => [
                'type' => 'none',
                'size' => 2,
            ]
        ],
        'scheduled' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled',
            'exclude' => '1',
            'config' => [
                'type' => 'none',
                'cols' => '30',
                'format' => 'datetime',
                'default' => 0
            ]
        ],
        'scheduled_begin' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_begin',
            'config' => [
                'type' => 'none',
                'cols' => '15',
                'format' => 'datetime',
                'default' => 0
            ]
        ],
        'scheduled_end' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_end',
            'config' => [
                'type' => 'none',
                'cols' => '15',
                'format' => 'datetime',
                'default' => 0
            ]
        ],
        'use_domain' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.use_domain',
            'config' => [
                'type' => 'select',
                'foreign_table' => 'sys_domain',
                'items' => [
                    ['', 0]
                ],
                'size' => '1',
                'maxitems' => 1,
                'minitems' => 0
            ]
        ],
        'use_rdct' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.use_rdct',
            'config' => [
                'type' => 'check',
                'default' => '0'
            ]
        ],
        'long_link_rdct_url' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_rdct_url',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => ''
            ]
        ],
        'long_link_mode' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_mode',
            'config' => [
                'type' => 'check'
            ]
        ],
        'authcode_fieldList' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.authcode_fieldList',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
                'default' => 'uid,name,email,password'
            ]
        ],
        'renderedsize' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.renderedsize',
            'exclude' => '1',
            'config' => [
                'type' => 'none'
            ]
        ],
        'attachment' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.attachment',
            'config' => [
                'type' => 'group',
                'internal_type' => 'file',
                'allowed' => '', // Must be empty for disallowed to work.
                'disallowed' => 'php,php3',
                'max_size' => '10000',
                'uploadfolder' => 'uploads/tx_directmail',
                'show_thumbs' => '0',
                'size' => '3',
                'maxitems' => '5',
                'minitems' => '0'
            ]
        ],
        'type' => [
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.type',
            'config' => [
                'type' => 'select',
                'items' => [
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.0', '0'],
                    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.1', '1'],
                    ['Draft of internal page', '2'],
                    ['Draft of external URL', '3']
                ],
                'default' => '0'
            ]
        ]
    ],
    'types' => [
        '0' => ['showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type,sys_language_uid, page, plainParams, HTMLParams, use_domain, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		'],
        '1' => ['showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type, page, plainParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		'],
        '2' => ['showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type,sys_language_uid, page, plainParams, HTMLParams, use_domain, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		'],
        '3' => ['showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type, page, plainParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		']
    ],
    'palettes' => [
        '1' => ['showitem' => 'scheduled_begin, scheduled_end, issent'],
        'from' => ['showitem' => 'from_email, from_name'],
        'reply' => ['showitem' => 'replyto_email, replyto_name'],
    ]
];
