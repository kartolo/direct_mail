<?php

return array(
    'ctrl' => array(
        'label' => 'subject',
        'default_sortby' => 'ORDER BY tstamp DESC',
        'tstamp' => 'tstamp',
        'prependAtCopy' => 'LLL:EXT:lang/locallang_general.xlf:LGL.prependAtCopy',
        'title' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail',
        'delete' => 'deleted',
        'iconfile' => TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('direct_mail') . 'res/gfx/mail.gif',
        'type' => 'type',
        'useColumnsForDefaultValues' => 'from_email,from_name,replyto_email,replyto_name,organisation,priority,encoding,charset,sendOptions,type',
        'dividers2tabs' => true,
    ),
    'interface' => array(
        'showRecordFieldList' => 'sys_language_uid,type,plainParams,HTMLParams,subject,from_name,from_email,replyto_name,replyto_email,return_path,organisation,attachment,priority,encoding,charset,sendOptions,includeMedia,flowedFormat,issent,renderedsize,use_domain,use_rdct,long_link_mode,authcode_fieldList'
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
        'subject' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.subject',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'max' => '120',
                'eval' => 'trim,required'
            )
        ),
        'page' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.page',
            'config' => array(
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'pages',
                'size' => '1',
                'maxitems' => 1,
                'minitems' => 0,
                'wizards' => array(
                    'suggest' => array(
                        'type' => 'suggest',
                    ),
                ),
            )
        ),
        'from_email' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_email',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'max' => '80',
                'eval' => 'trim,required'
            )
        ),
        'from_name' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.from_name',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            )
        ),
        'replyto_email' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_email',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            )
        ),
        'replyto_name' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.replyto_name',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            )
        ),
        'return_path' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.return_path',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            )
        ),
        'organisation' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.organisation',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80'
            )
        ),
        'encoding' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.transfer_encoding',
            'config' => array(
                'type' => 'select',
                'items' => array(
                    array('quoted-printable', 'quoted-printable'),
                    array('base64', 'base64'),
                    array('8bit', '8bit'),
                ),
                'default' => 'quoted-printable'
            )
        ),
        'charset' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.charset',
            'config' => array(
                'type' => 'input',
                'size' => '15',
                'max' => '20',
                'eval' => 'trim',
                'default' => 'iso-8859-1'
            )
        ),
        'priority' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority',
            'config' => array(
                'type' => 'select',
                'items' => array(
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.0', '5'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.1', '3'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.priority.I.2', '1')
                ),
                'default' => '3'
            )
        ),
        'sendOptions' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions',
            'config' => array(
                'type' => 'check',
                'items' => array(
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.0', ''),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.sendOptions.I.1', '')
                ),
                'cols' => '2',
                'default' => '3'
            )
        ),
        'includeMedia' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.includeMedia',
            'config' => array(
                'type' => 'check',
                'default' => '0'
            )
        ),
        'flowedFormat' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.flowedFormat',
            'config' => array(
                'type' => 'check',
                'default' => '0'
            )
        ),
        'HTMLParams' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams',
            'config' => array(
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => ''
            )
        ),
        'plainParams' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams',
            'config' => array(
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => '&type=99'
            )
        ),
        'issent' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.issent',
            'exclude' => '1',
            'config' => array(
                'type' => 'none',
                'size' => 2,
            )
        ),
        'scheduled' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled',
            'exclude' => '1',
            'config' => array(
                'type' => 'none',
                'cols' => '30',
                'format' => 'datetime',
                'default' => 0
            )
        ),
        'scheduled_begin' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_begin',
            'config' => array(
                'type' => 'none',
                'cols' => '15',
                'format' => 'datetime',
                'default' => 0
            )
        ),
        'scheduled_end' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.scheduled_end',
            'config' => array(
                'type' => 'none',
                'cols' => '15',
                'format' => 'datetime',
                'default' => 0
            )
        ),
        'use_domain' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.use_domain',
            'config' => array(
                'type' => 'select',
                'foreign_table' => 'sys_domain',
                'items' => array(
                    array('', 0)
                ),
                'size' => '1',
                'maxitems' => 1,
                'minitems' => 0
            )
        ),
        'use_rdct' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.use_rdct',
            'config' => array(
                'type' => 'check',
                'default' => '0'
            )
        ),
        'long_link_rdct_url' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_rdct_url',
            'config' => array(
                'type' => 'input',
                'size' => '15',
                'max' => '80',
                'eval' => 'trim',
                'default' => ''
            )
        ),
        'long_link_mode' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.long_link_mode',
            'config' => array(
                'type' => 'check'
            )
        ),
        'authcode_fieldList' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.authcode_fieldList',
            'config' => array(
                'type' => 'input',
                'size' => '30',
                'eval' => 'trim',
                'max' => '80',
                'default' => 'uid,name,email,password'
            )
        ),
        'renderedsize' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.renderedsize',
            'exclude' => '1',
            'config' => array(
                'type' => 'none'
            )
        ),
        'attachment' => array(
            'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.attachment',
            'config' => array(
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
            )
        ),
        'type' => array(
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.type',
            'config' => array(
                'type' => 'select',
                'items' => array(
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.0', '0'),
                    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.type.I.1', '1'),
                    array('Draft of internal page', '2'),
                    array('Draft of external URL', '3')
                ),
                'default' => '0'
            )
        )
    ),
    'types' => array(
        '0' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type;;;;1-1-1,sys_language_uid, page, plainParams, HTMLParams, use_domain, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		'),
        '1' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type;;;;1-1-1, page, plainParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		'),
        '2' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type;;;;1-1-1,sys_language_uid, page, plainParams, HTMLParams, use_domain, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		'),
        '3' => array('showitem' => '
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab1, type;;;;1-1-1, page, plainParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.plainParams.ALT.1, HTMLParams;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.HTMLParams.ALT.1, attachment,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab2, subject, --palette--;;from;;;;1-1-1, --palette--;Reply-to;reply, return_path, organisation, priority, encoding,
			--div--;LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:sys_dmail.tab3, sendOptions, includeMedia, flowedFormat, use_rdct, long_link_mode, authcode_fieldList, scheduled;;1
		')
    ),
    'palettes' => array(
        '1' => array('showitem' => 'scheduled_begin, scheduled_end, issent'),
        'from' => array('showitem' => 'from_email, from_name'),
        'reply' => array('showitem' => 'replyto_email, replyto_name'),
    )
);
