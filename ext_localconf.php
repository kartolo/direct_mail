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
 * Admin email for sending the cronjob error message
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['adminEmail'] = $_EXTCONF['adminEmail'];

/**
 * Direct Mail send a notification every time a job starts or ends
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['notificationJob'] = $_EXTCONF['notificationJob'];

/**
 * Interval of the cronjob
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['cronInt'] = $_EXTCONF['cronInt'];

/**
 * SMTP options
 */
$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY]['smtp'] = array(
	'enabled'  => ($_EXTCONF['SmtpEnabled'] == 1),
	'host'     => $_EXTCONF['SmtpHost'],
	'port'     => $_EXTCONF['SmtpPort'],
	'auth'     => ($_EXTCONF['SmtpAuth'] == 1),
	'username' => $_EXTCONF['SmtpUser'],
	'password' => $_EXTCONF['SmtpPassword'],
	'persist'  => ($_EXTCONF['smtpPersist'] == 1)
);


/**
 * Enable the use of News plain text rendering hook:
 */
if ($_EXTCONF['enablePlainTextNews']) {
		// Register tt_news plain text processing hook
	$TYPO3_CONF_VARS['EXTCONF']['tt_news']['extraCodesHook'][] = 'EXT:'.$_EXTKEY.'/res/scripts/class.tx_directmail_ttnews_plaintext.php:&tx_directmail_ttnews_plaintext';
}

?>
