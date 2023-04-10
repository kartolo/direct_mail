<?php

return [
    'ctrl' => [
        'label' => 'subject',
        'default_sortby' => 'ORDER BY tstamp DESC',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail',
        'delete' => 'deleted',
        'iconfile' => 'EXT:direct_mail/Resources/Public/Icons/mail.png',
        'type' => 'type',
        'useColumnsForDefaultValues' => 'from_email,from_name,replyto_email,replyto_name,organisation,priority,encoding,charset,sendOptions,type',
        'languageField' => 'sys_language_uid',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language',
            ],
        ],
        'subject' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.subject',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:subject.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'page' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.page',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:page.details',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => '1',
                'maxitems' => 1,
                'minitems' => 0,
            ],
        ],
        'from_email' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_email',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:from_email.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'max' => '80',
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'from_name' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_name',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:from_name.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'replyto_email' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_email',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:replyto_email.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'replyto_name' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_name',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:replyto_name.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'return_path' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.return_path',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:return_path.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'organisation' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.organisation',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:organisation.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
            ],
        ],
        'encoding' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.transfer_encoding',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:encoding.details',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'quoted-printable', 'value' => 'quoted-printable'],
                    ['label' => 'base64', 'value' => 'base64'],
                    ['label' => '8bit', 'value' => '8bit'],
                ],
                'default' => 'quoted-printable',
            ],
        ],
        'charset' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.charset',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:charset.details',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '20',
                'eval' => 'trim',
                'default' => 'iso-8859-1',
            ],
        ],
        'priority' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:priority.details',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.0', 'value' => '5'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.1', 'value' => '3'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.2', 'value' => '1'],
                ],
                'default' => '3',
            ],
        ],
        'sendOptions' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:sendOptions.details',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.0', 'value' => ''],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.1', 'value' => ''],
                ],
                'cols' => '2',
                'default' => '3',
            ],
        ],
        'includeMedia' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.includeMedia',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:includeMedia.details',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'flowedFormat' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.flowedFormat',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:flowedFormat.details',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'HTMLParams' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:HTMLParams.details',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'plainParams' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:plainParams.details',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '&type=99',
            ],
        ],
        'issent' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.issent',
            'exclude' => true,
            'config' => [
                'type' => 'none',
                'size' => 2,
            ],
        ],
        'scheduled' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled',
            'exclude' => true,
            'config' => [
                'type' => 'none',
                'size' => '30',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'scheduled_begin' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_begin',
            'config' => [
                'type' => 'none',
                'size' => '15',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'scheduled_end' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_end',
            'config' => [
                'type' => 'none',
                'size' => '15',
                'format' => 'datetime',
                'default' => 0,
            ],
        ],
        'use_rdct' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.use_rdct',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:use_rdct.details',
            'config' => [
                'type' => 'check',
                'default' => '0',
            ],
        ],
        'long_link_rdct_url' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_rdct_url',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:long_link_rdct_url.details',
            'config' => [
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'long_link_mode' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_mode',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:long_link_mode.details',
            'config' => [
                'type' => 'check',
            ],
        ],
        'authcode_fieldList' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.authcode_fieldList',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:authcode_fieldList.details',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
                'default' => 'uid,name,email,password',
            ],
        ],
        'renderedsize' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.renderedsize',
            'exclude' => true,
            'config' => [
                'type' => 'none',
            ],
        ],
        'attachment' => [
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.attachment',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:attachment.details',
            'config' => [
                'type' => 'file',
                'maxitems' => 5,
                'allowed' => 'common-image-types',
                'appearance' => [
                    'fileByUrlAllowed' => false,
                ]
            ]
        ],
        'type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'description' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf:type.details',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.0', 'value' => '0'],
                    ['label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.1', 'value' => '1'],
                    ['label' => 'Draft of internal page', 'value' => '2'],
                    ['label' => 'Draft of external URL', 'value' => '3'],
                ],
                'default' => '0',
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type,sys_language_uid, page, plainParams, HTMLParams, attachment,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		'],
        '1' => ['showitem' => '
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type, page, plainParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		'],
        '2' => ['showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type,sys_language_uid, page, plainParams, HTMLParams, attachment,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		'],
        '3' => ['showitem' => '
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type, page, plainParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
            --div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled
		'],
    ],
    'palettes' => [
        '1' => ['showitem' => 'scheduled_begin, scheduled_end, issent'],
        'from' => ['showitem' => 'from_email, from_name'],
        'reply' => ['showitem' => 'replyto_email, replyto_name'],
    ],
];
