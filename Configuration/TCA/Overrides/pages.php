<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

// pages modified
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] =
	array('LLL:EXT:direct_mail/locallang_tca.xml:pages.module.I.5', 'dmail');


