<?php
defined('TYPO3') || die();

// pages modified
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
    'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:pages.module.I.5',
    'dmail',
    'directmail-folder',
];

if (!is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'] = [];
}

$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-dmail'] = 'apps-pagetree-folder-contains-dmail';
