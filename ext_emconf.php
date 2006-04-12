<?php

########################################################################
# Extension Manager/Repository config file for ext: "direct_mail"
#
# Auto generated 12-04-2006 23:30
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Direct Mail',
	'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
	'category' => 'module',
	'shy' => 0,
	'dependencies' => 'cms,tt_address',
	'conflicts' => 'sr_direct_mail_ext,it_dmail_fix,plugin_mgm',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'mod',
	'state' => 'stable',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => 'tt_content,tt_address,fe_users',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author' => 'Kasper Sk�rh�j',
	'author_email' => 'kasper@typo3.com',
	'author_company' => 'Curby Soft Multimedia',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '2.1.0',
	'_md5_values_when_last_written' => 'a:46:{s:9:"ChangeLog";s:4:"e7f4";s:20:"class.ext_update.php";s:4:"2575";s:21:"ext_conf_template.txt";s:4:"1a86";s:12:"ext_icon.gif";s:4:"a143";s:17:"ext_localconf.php";s:4:"72b3";s:14:"ext_tables.php";s:4:"fe8c";s:14:"ext_tables.sql";s:4:"8e3c";s:26:"locallang_csh_sysdmail.xml";s:4:"3da0";s:29:"locallang_csh_sysdmailcat.xml";s:4:"8089";s:27:"locallang_csh_sysdmailg.xml";s:4:"3c14";s:17:"locallang_tca.xml";s:4:"5200";s:7:"tca.php";s:4:"1374";s:14:"doc/manual.sxw";s:4:"a509";s:21:"mod/class.dmailer.php";s:4:"ddd4";s:24:"mod/class.mailselect.php";s:4:"d2f5";s:27:"mod/class.mod_web_dmail.php";s:4:"b7f8";s:22:"mod/class.readmail.php";s:4:"ffad";s:12:"mod/conf.php";s:4:"10f3";s:20:"mod/dmailerd.phpcron";s:4:"2fc4";s:13:"mod/index.php";s:4:"2d85";s:40:"mod/locallang_csh_web_txdirectmailM1.xml";s:4:"97ed";s:40:"mod/locallang_mod_web_txdirectmailM1.xml";s:4:"f986";s:16:"mod/mod_icon.gif";s:4:"a143";s:20:"mod/returnmail.phpsh";s:4:"5a67";s:31:"pi1/class.tx_directmail_pi1.php";s:4:"fe5a";s:17:"pi1/locallang.php";s:4:"1203";s:17:"pi1/locallang.xml";s:4:"d6ff";s:36:"pi1/tx_directmail_pi1_plaintext.tmpl";s:4:"2027";s:18:"res/gfx/attach.gif";s:4:"5559";s:17:"res/gfx/dmail.gif";s:4:"4d4f";s:22:"res/gfx/dmail_list.gif";s:4:"8d58";s:23:"res/gfx/dmailerping.gif";s:4:"cc11";s:39:"res/gfx/icon_tx_directmail_category.gif";s:4:"9398";s:16:"res/gfx/mail.gif";s:4:"4174";s:21:"res/gfx/mailgroup.gif";s:4:"1cc5";s:25:"res/gfx/modules_dmail.gif";s:4:"a143";s:28:"res/gfx/modules_dmail__h.gif";s:4:"040c";s:19:"res/gfx/newmail.gif";s:4:"ffa9";s:48:"res/scripts/class.tx_directmail_checkjumpurl.php";s:4:"fab2";s:45:"res/scripts/class.tx_directmail_container.php";s:4:"615f";s:53:"res/scripts/class.tx_directmail_select_categories.php";s:4:"03aa";s:52:"res/scripts/class.tx_directmail_ttnews_plaintext.php";s:4:"4148";s:27:"static/boundaries/setup.txt";s:4:"f9f1";s:30:"static/plaintext/constants.txt";s:4:"004d";s:26:"static/plaintext/setup.txt";s:4:"b026";s:34:"static/tt_news_plaintext/setup.txt";s:4:"672a";}',
	'constraints' => array(
		'depends' => array(
			'cms' => '',
			'tt_address' => '',
			'php' => '4.1.0-',
			'typo3' => '4.0.0-',
		),
		'conflicts' => array(
			'sr_direct_mail_ext' => '',
			'it_dmail_fix' => '',
			'plugin_mgm' => '',
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
);

?>