<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkDataSubmission'][]='EXT:direct_mail/res/class.tx_directmail_checkjumpurl.php:&tx_directmail_checkjumpurl';

$_EXTCONF = unserialize($_EXTCONF);    // unserializing the configuration so we can use it here:

/**
 * Language of the cron task:
 */
$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cron_language'] = $_EXTCONF['cron_language'] ? $_EXTCONF['cron_language'] : 'en';

/**
 * Number of messages sent per cycle of the cron task:
 */
$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['sendPerCycle'] = $_EXTCONF['sendPerCycle'] ? $_EXTCONF['sendPerCycle'] : 50;

/**
 * Default recipient field list:
 */
$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['defaultRecipFields'] = 'uid,name,title,email,phone,www,address,company,city,zip,country,fax,firstname';

/**
 * Additional DB fields of the recipient:
 */
$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields'] = $_EXTCONF['addRecipFields'];

/**
 * Enable the use of sendmail defer mode:
 */
$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['useDeferMode'] = $_EXTCONF['useDeferMode'] ? $_EXTCONF['useDeferMode'] : 0;

?>