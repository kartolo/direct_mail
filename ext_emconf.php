<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "direct_mail".
 *
 * Auto generated 29-07-2013 16:04
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Direct Mail',
    'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
    'category' => 'module',
    'shy' => 0,
    'version' => '6.0.0-dev',
    'dependencies' => 'cms,tt_address',
    'conflicts' => 'sr_direct_mail_ext,it_dmail_fix,plugin_mgm,direct_mail_123',
    'priority' => '',
    'loadOrder' => '',
    'module' => 'mod1,mod2,mod3,mod4,mod5,mod6',
    'state' => 'stable',
    'uploadfolder' => 1,
    'createDirs' => '',
    'modify_tables' => 'tt_content,tt_address,fe_users',
    'clearcacheonload' => 0,
    'lockType' => '',
    'author' => 'Ivan Kartolo',
    'author_email' => 'ivan.kartolo@dkd.de',
    'author_company' => 'd.k.d Internet Service GmbH',
    'CGLcompliance' => '',
    'CGLcompliance_note' => '',
    'constraints' => [
        'depends' => [
            'tt_address' => '4.0.0-',
            'php' => '7.2.0',
            'typo3' => '9.5.0-9.5.99',
            'jumpurl' => '7.7.0-7.8.99',
            'rdct' => '1.0.0'
        ],
        'conflicts' => [
            'sr_direct_mail_ext' => '',
            'it_dmail_fix' => '',
            'plugin_mgm' => '',
            'direct_mail_123' => '',
        ],
        'suggests' => [
        ],
    ],
    '_md5_values_when_last_written' => 'a:99:{s:9:"ChangeLog";s:4:"c147";s:20:"class.ext_update.php";s:4:"0ab1";s:31:"class.tx_directmail_gabriel.php";s:4:"6de4";s:33:"class.tx_directmail_scheduler.php";s:4:"1a0e";s:16:"ext_autoload.php";s:4:"2e3e";s:21:"ext_conf_template.txt";s:4:"7c49";s:12:"ext_icon.gif";s:4:"a143";s:17:"ext_localconf.php";s:4:"33b2";s:14:"ext_tables.php";s:4:"bc0a";s:14:"ext_tables.sql";s:4:"2388";s:17:"locallang_tca.xml";s:4:"6e8b";s:35:"Classes/Scheduler/MailFromDraft.php";s:4:"4f4c";s:52:"Classes/Scheduler/MailFromDraft_AdditionalFields.php";s:4:"eaa9";s:21:"Configuration/tca.php";s:4:"0000";s:42:"Interfaces/Scheduler/MailFromDraftHook.php";s:4:"938b";s:40:"Resources/Public/StyleSheets/modules.css";s:4:"bf1f";s:23:"cli/cli_direct_mail.php";s:4:"a2b3";s:14:"doc/manual.sxw";s:4:"811a";s:36:"locallang/locallang_csh_sysdmail.xml";s:4:"f4a1";s:39:"locallang/locallang_csh_sysdmailcat.xml";s:4:"a2b1";s:37:"locallang/locallang_csh_sysdmailg.xml";s:4:"0ecd";s:42:"locallang/locallang_csh_txdirectmailM2.xml";s:4:"2fa2";s:42:"locallang/locallang_csh_txdirectmailM3.xml";s:4:"3846";s:42:"locallang/locallang_csh_txdirectmailM4.xml";s:4:"761f";s:42:"locallang/locallang_csh_txdirectmailM5.xml";s:4:"e511";s:42:"locallang/locallang_csh_txdirectmailM6.xml";s:4:"3f6d";s:44:"locallang/locallang_csh_web_txdirectmail.xml";s:4:"1764";s:30:"locallang/locallang_mod2-6.xml";s:4:"d14a";s:14:"mod1/clear.gif";s:4:"cc11";s:13:"mod1/conf.php";s:4:"f25a";s:14:"mod1/index.php";s:4:"4f8b";s:22:"mod1/locallang_mod.xml";s:4:"9b3c";s:17:"mod1/mod_icon.gif";s:4:"a143";s:22:"mod1/mod_template.html";s:4:"65bd";s:34:"mod2/class.tx_directmail_dmail.php";s:4:"5beb";s:13:"mod2/conf.php";s:4:"f24c";s:14:"mod2/index.php";s:4:"6421";s:22:"mod2/locallang_mod.xml";s:4:"6088";s:17:"mod2/mod_icon.gif";s:4:"a143";s:22:"mod2/mod_template.html";s:4:"f729";s:43:"mod3/class.tx_directmail_recipient_list.php";s:4:"7ad3";s:14:"mod3/clear.gif";s:4:"cc11";s:13:"mod3/conf.php";s:4:"ba64";s:14:"mod3/index.php";s:4:"e742";s:22:"mod3/locallang_mod.xml";s:4:"c2ce";s:17:"mod3/mod_icon.gif";s:4:"a143";s:22:"mod3/mod_template.html";s:4:"2581";s:39:"mod4/class.tx_directmail_statistics.php";s:4:"95da";s:14:"mod4/clear.gif";s:4:"cc11";s:13:"mod4/conf.php";s:4:"2c51";s:14:"mod4/index.php";s:4:"e2b7";s:22:"mod4/locallang_mod.xml";s:4:"fc77";s:17:"mod4/mod_icon.gif";s:4:"a143";s:22:"mod4/mod_template.html";s:4:"2581";s:42:"mod5/class.tx_directmail_mailer_engine.php";s:4:"8129";s:14:"mod5/clear.gif";s:4:"cc11";s:13:"mod5/conf.php";s:4:"4ad5";s:14:"mod5/index.php";s:4:"2077";s:22:"mod5/locallang_mod.xml";s:4:"a0d7";s:17:"mod5/mod_icon.gif";s:4:"a143";s:22:"mod5/mod_template.html";s:4:"2581";s:42:"mod6/class.tx_directmail_configuration.php";s:4:"9037";s:14:"mod6/clear.gif";s:4:"cc11";s:13:"mod6/conf.php";s:4:"2862";s:14:"mod6/index.php";s:4:"c58e";s:22:"mod6/locallang_mod.xml";s:4:"87d6";s:17:"mod6/mod_icon.gif";s:4:"a143";s:31:"pi1/class.tx_directmail_pi1.php";s:4:"ef59";s:17:"pi1/locallang.php";s:4:"ff9e";s:17:"pi1/locallang.xml";s:4:"2d6b";s:36:"pi1/tx_directmail_pi1_plaintext.tmpl";s:4:"2027";s:33:"Resources/Public/Icons/attach.gif";s:4:"5559";s:32:"Resources/Public/Icons/dmail.gif";s:4:"4d4f";s:37:"Resources/Public/Icons/dmail_list.gif";s:4:"8d58";s:38:"Resources/Public/Icons/dmailerping.gif";s:4:"cc11";s:48:"Resources/Public/Icons/ext_icon_dmail_folder.gif";s:4:"a143";s:54:"Resources/Public/Icons/icon_tx_directmail_category.gif";s:4:"9398";s:31:"Resources/Public/Icons/mail.gif";s:4:"4174";s:36:"Resources/Public/Icons/mailgroup.gif";s:4:"1cc5";s:40:"Resources/Public/Icons/modules_dmail.gif";s:4:"a143";s:43:"Resources/Public/Icons/modules_dmail__h.gif";s:4:"040c";s:34:"Resources/Public/Icons/newmail.gif";s:4:"ffa9";s:39:"Resources/Public/Icons/preview_html.gif";s:4:"1e65";s:38:"Resources/Public/Icons/preview_txt.gif";s:4:"4d9a";s:29:"res/scripts/class.dmailer.php";s:4:"4089";s:32:"res/scripts/class.mailselect.php";s:4:"43dd";s:30:"res/scripts/class.readmail.php";s:4:"c526";s:48:"res/scripts/class.tx_directmail_checkjumpurl.php";s:4:"6bf9";s:45:"res/scripts/class.tx_directmail_container.php";s:4:"b13c";s:44:"res/scripts/class.tx_directmail_importer.php";s:4:"6f7e";s:53:"res/scripts/class.tx_directmail_select_categories.php";s:4:"0c1f";s:42:"res/scripts/class.tx_directmail_static.php";s:4:"171f";s:47:"res/scripts/class.tx_directmail_tsparserext.php";s:4:"a5fe";s:52:"res/scripts/class.tx_directmail_ttnews_plaintext.php";s:4:"c28d";s:28:"res/scripts/returnmail.phpsh";s:4:"c0be";s:27:"static/boundaries/setup.txt";s:4:"9409";s:30:"static/plaintext/constants.txt";s:4:"59ce";s:26:"static/plaintext/setup.txt";s:4:"ee48";s:34:"static/tt_news_plaintext/setup.txt";s:4:"1a31";}',
    'suggests' => [
    ],
    'autoload' => [
        'psr-4' => [
            'DirectMailTeam\\DirectMail\\' => 'Classes/',
            'Fetch\\' => 'Resources/Private/Php/Fetch/src/Fetch/'
        ]
    ],
];
