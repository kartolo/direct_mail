<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

	// Register jumpurl processing hook
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkDataSubmission'][]='EXT:'.$_EXTKEY.'/res/scripts/class.tx_directmail_checkjumpurl.php:&tx_directmail_checkjumpurl';

	// unserializing the configuration so we can use it here:
$_EXTCONF = unserialize($_EXTCONF);

/**
 * Language of the cron task:
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['cron_language'] = $_EXTCONF['cron_language'] ? $_EXTCONF['cron_language'] : 'en';

/**
 * Number of messages sent per cycle of the cron task:
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['sendPerCycle'] = $_EXTCONF['sendPerCycle'] ? $_EXTCONF['sendPerCycle'] : 50;

/**
 * Default recipient field list:
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['defaultRecipFields'] = 'uid,name,title,email,phone,www,address,company,city,zip,country,fax,firstname';

/**
 * Additional DB fields of the recipient:
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['addRecipFields'] = $_EXTCONF['addRecipFields'];

/**
 * Enable the use of sendmail defer mode:
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['useDeferMode'] = $_EXTCONF['useDeferMode'] ? $_EXTCONF['useDeferMode'] : 0;

/**
 * Enable the use of News plain text rendering hook:
 */
if ($_EXTCONF['enablePlainTextNews']) {
		// Register tt_news plain text processing hook
	$TYPO3_CONF_VARS['EXTCONF']['tt_news']['extraCodesHook'][] = 'EXT:'.$_EXTKEY.'/res/scripts/class.tx_directmail_ttnews_plaintext.php:&tx_directmail_ttnews_plaintext';
}

?>