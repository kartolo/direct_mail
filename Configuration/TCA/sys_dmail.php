<?php
return [
    'ctrl' => [
        'label' => 'subject',
        'default_sortby' => 'ORDER BY tstamp DESC',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail',
        'delete' => 'deleted',
        'iconfile' => 'EXT:direct_mail/Resources/Public/Icons/mail.gif',
        'type' => 'type',
        'useColumnsForDefaultValues' => 'from_email,from_name,replyto_email,replyto_name,organisation,priority,encoding,charset,sendOptions,type',
        'languageField' => 'sys_language_uid'
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'language'
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
                'renderType' => 'selectSingle',
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
                'renderType' => 'selectSingle',
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
            'config' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig(
                'attachment',
                [
                    'maxitems' => 5,
                    'appearance' => [
                        'createNewRelationLinkTitle' => 'LLL:EXT:frontend/locallang_ttc.xlf:images.addFileReference'
                    ],
                    // custom configuration for displaying fields in the overlay/reference table
                    // to use the image overlay palette instead of the basic overlay palette
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                    --palette--;;filePalette'
                            ],
                            \TYPO3\CMS\Core\Resource\File::FILETYPE_TEXT => [
                                'showitem' => '
                                    --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                    --palette--;;filePalette'
                            ],
                        ],
                    ],
                ],
                $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
            ),
        ],
        'type' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
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
		']
    ],
    'palettes' => [
        '1' => ['showitem' => 'scheduled_begin, scheduled_end, issent'],
        'from' => ['showitem' => 'from_email, from_name'],
        'reply' => ['showitem' => 'replyto_email, replyto_name'],
    ]
];
