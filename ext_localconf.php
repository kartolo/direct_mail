<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

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
 * Additional DB fields of the recipient:
 */
$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields'] = $_EXTCONF['addRecipFields'];

?>