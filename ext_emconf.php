<?php

########################################################################
# Extension Manager/Repository config file for ext: "direct_mail"
#
# Auto generated 07-04-2006 22:35
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
	'version' => '2.0.1',	// Don't modify this! Managed automatically during upload to repository.
	'_md5_values_when_last_written' => 'a:96:{s:20:".#ext_tables.php.1.6";s:4:"7ada";s:20:".#ext_tables.sql.1.8";s:4:"daa4";s:9:"ChangeLog";s:4:"9c60";s:17:"ChangeLog.~1.15.~";s:4:"7f18";s:20:"class.ext_update.php";s:4:"2575";s:21:"ext_conf_template.txt";s:4:"1a86";s:12:"ext_icon.gif";s:4:"a143";s:17:"ext_localconf.php";s:4:"72b3";s:14:"ext_tables.php";s:4:"fe8c";s:14:"ext_tables.sql";s:4:"8e3c";s:26:"locallang_csh_sysdmail.xml";s:4:"3da0";s:29:"locallang_csh_sysdmailcat.xml";s:4:"8089";s:27:"locallang_csh_sysdmailg.xml";s:4:"3c14";s:17:"locallang_tca.xml";s:4:"5200";s:7:"tca.php";s:4:"1374";s:11:"CVS/Entries";s:4:"6fe8";s:14:"CVS/Repository";s:4:"1b61";s:8:"CVS/Root";s:4:"b3f0";s:14:"doc/manual.sxw";s:4:"3985";s:15:"doc/CVS/Entries";s:4:"668d";s:18:"doc/CVS/Repository";s:4:"7aaa";s:12:"doc/CVS/Root";s:4:"b3f0";s:23:"mod/#class.dmailer.php#";s:4:"b8ee";s:27:"mod/.#class.dmailer.php.1.5";s:4:"8b06";s:30:"mod/.#class.mailselect.php.1.3";s:4:"6c73";s:34:"mod/.#class.mod_web_dmail.php.1.11";s:4:"e029";s:34:"mod/.#class.mod_web_dmail.php.1.15";s:4:"9551";s:26:"mod/.#dmailerd.phpcron.1.6";s:4:"d234";s:26:"mod/.#dmailerd.phpcron.1.7";s:4:"5113";s:21:"mod/class.dmailer.php";s:4:"49bb";s:24:"mod/class.mailselect.php";s:4:"d2f5";s:27:"mod/class.mod_web_dmail.php";s:4:"7f3d";s:35:"mod/class.mod_web_dmail.php.~1.15.~";s:4:"fa81";s:35:"mod/class.mod_web_dmail.phpMODIFIED";s:4:"d927";s:22:"mod/class.readmail.php";s:4:"ffad";s:12:"mod/conf.php";s:4:"10f3";s:20:"mod/dmailerd.phpcron";s:4:"23eb";s:27:"mod/dmailerd.phpcron.~1.6.~";s:4:"00b1";s:25:"mod/dmailerd.phpsh.~1.1.~";s:4:"b51c";s:13:"mod/index.php";s:4:"2d85";s:20:"mod/index.php.~1.3.~";s:4:"9f48";s:40:"mod/locallang_csh_web_txdirectmailM1.xml";s:4:"97ed";s:40:"mod/locallang_mod_web_txdirectmailM1.xml";s:4:"f986";s:16:"mod/mod_icon.gif";s:4:"a143";s:20:"mod/returnmail.phpsh";s:4:"c5ca";s:15:"mod/CVS/Entries";s:4:"a772";s:18:"mod/CVS/Repository";s:4:"9442";s:12:"mod/CVS/Root";s:4:"b3f0";s:16:"mod2/CVS/Entries";s:4:"57b8";s:19:"mod2/CVS/Repository";s:4:"616d";s:13:"mod2/CVS/Root";s:4:"b3f0";s:16:"mod3/CVS/Entries";s:4:"57b8";s:19:"mod3/CVS/Repository";s:4:"368d";s:13:"mod3/CVS/Root";s:4:"b3f0";s:15:"res/CVS/Entries";s:4:"d48c";s:18:"res/CVS/Repository";s:4:"f0dc";s:12:"res/CVS/Root";s:4:"b3f0";s:18:"res/gfx/attach.gif";s:4:"5559";s:17:"res/gfx/dmail.gif";s:4:"4d4f";s:22:"res/gfx/dmail_list.gif";s:4:"8d58";s:23:"res/gfx/dmailerping.gif";s:4:"cc11";s:39:"res/gfx/icon_tx_directmail_category.gif";s:4:"9398";s:16:"res/gfx/mail.gif";s:4:"4174";s:21:"res/gfx/mailgroup.gif";s:4:"1cc5";s:25:"res/gfx/modules_dmail.gif";s:4:"a143";s:28:"res/gfx/modules_dmail__h.gif";s:4:"040c";s:19:"res/gfx/newmail.gif";s:4:"ffa9";s:19:"res/gfx/CVS/Entries";s:4:"a450";s:22:"res/gfx/CVS/Repository";s:4:"70bc";s:16:"res/gfx/CVS/Root";s:4:"b3f0";s:48:"res/scripts/class.tx_directmail_checkjumpurl.php";s:4:"fab2";s:45:"res/scripts/class.tx_directmail_container.php";s:4:"615f";s:53:"res/scripts/class.tx_directmail_select_categories.php";s:4:"03aa";s:52:"res/scripts/class.tx_directmail_ttnews_plaintext.php";s:4:"4148";s:23:"res/scripts/CVS/Entries";s:4:"1942";s:26:"res/scripts/CVS/Repository";s:4:"428c";s:20:"res/scripts/CVS/Root";s:4:"b3f0";s:18:"static/CVS/Entries";s:4:"c12b";s:21:"static/CVS/Repository";s:4:"d515";s:15:"static/CVS/Root";s:4:"b3f0";s:27:"static/boundaries/setup.txt";s:4:"f9f1";s:29:"static/boundaries/CVS/Entries";s:4:"ab90";s:32:"static/boundaries/CVS/Repository";s:4:"3915";s:26:"static/boundaries/CVS/Root";s:4:"b3f0";s:30:"static/plaintext/constants.txt";s:4:"004d";s:26:"static/plaintext/setup.txt";s:4:"b026";s:28:"static/plaintext/CVS/Entries";s:4:"ce87";s:31:"static/plaintext/CVS/Repository";s:4:"37a1";s:25:"static/plaintext/CVS/Root";s:4:"b3f0";s:31:"pi1/class.tx_directmail_pi1.php";s:4:"fe5a";s:17:"pi1/locallang.php";s:4:"1203";s:17:"pi1/locallang.xml";s:4:"d6ff";s:36:"pi1/tx_directmail_pi1_plaintext.tmpl";s:4:"2027";s:15:"pi1/CVS/Entries";s:4:"20dd";s:18:"pi1/CVS/Repository";s:4:"5647";s:12:"pi1/CVS/Root";s:4:"b3f0";}',
	'constraints' => 'Array',
);

?>