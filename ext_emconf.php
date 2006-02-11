<?php

########################################################################
# Extension Manager/Repository config file for ext: "direct_mail"
# 
# Auto generated 11-02-2006 10:24
# 
# Manual updates:
# Only the data in the array - anything else is removed by next write
########################################################################

$EM_CONF[$_EXTKEY] = Array (
	'title' => 'Direct Mail',
	'description' => 'Advanced Direct Mail/Newsletter mailer system with sophisticated options for personalization of emails including response statistics.',
	'category' => 'module',
	'shy' => 0,
	'dependencies' => 'cms,tt_address',
	'conflicts' => 'sr_direct_mail_ext,it_dmail_fix',
	'priority' => '',
	'loadOrder' => '',
	'TYPO3_version' => '3.7.0-',
	'PHP_version' => '4.1.0-',
	'module' => 'mod',
	'state' => 'stable',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => 'tt_content,tt_address,fe_users',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author' => 'Kasper Skårhøj',
	'author_email' => 'kasper@typo3.com',
	'author_company' => 'Curby Soft Multimedia',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'private' => 0,
	'download_password' => '',
	'version' => '2.0.0',	// Don't modify this! Managed automatically during upload to repository.
	'_md5_values_when_last_written' => 'a:30:{s:9:"ChangeLog";s:4:"ef29";s:21:"ext_conf_template.txt";s:4:"46a2";s:12:"ext_icon.gif";s:4:"a143";s:17:"ext_localconf.php";s:4:"5314";s:14:"ext_tables.php";s:4:"de28";s:14:"ext_tables.sql";s:4:"aa79";s:31:"icon_tx_directmail_category.gif";s:4:"9398";s:26:"locallang_csh_sysdmail.php";s:4:"3dcf";s:27:"locallang_csh_sysdmailg.php";s:4:"4096";s:17:"locallang_tca.php";s:4:"3d3d";s:7:"tca.php";s:4:"2970";s:12:"doc/TODO.txt";s:4:"2114";s:14:"doc/manual.sxw";s:4:"c70f";s:14:"mod/attach.gif";s:4:"5559";s:21:"mod/class.dmailer.php";s:4:"2e55";s:24:"mod/class.mailselect.php";s:4:"e593";s:27:"mod/class.mod_web_dmail.php";s:4:"1cfe";s:22:"mod/class.readmail.php";s:4:"7dd4";s:12:"mod/conf.php";s:4:"cc71";s:20:"mod/dmailerd.phpcron";s:4:"f2ec";s:13:"mod/error_log";s:4:"0e99";s:13:"mod/index.php";s:4:"9f48";s:17:"mod/locallang.php";s:4:"079c";s:21:"mod/locallang_mod.php";s:4:"a46b";s:12:"mod/mail.gif";s:4:"34a5";s:16:"mod/mod_icon.gif";s:4:"a143";s:20:"mod/returnmail.phpsh";s:4:"cd75";s:40:"res/class.tx_directmail_checkjumpurl.php";s:4:"4941";s:37:"res/class.tx_directmail_container.php";s:4:"553a";s:16:"static/setup.txt";s:4:"d11c";}',
);

?>