<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

// pages modified
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] =
    array('LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:pages.module.I.5', 'dmail');

if (is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-dmail'] = 'tcarecords-pages-contains-dmail';
}
