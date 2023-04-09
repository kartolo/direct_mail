<?php

defined('TYPO3') || die();

// pages modified
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] = [
    'label' => 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:pages.module.I.5',
    'value' => 'dmail',
    'icon' => 'directmail-folder',
];

if (!is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'] = [];
}

$GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-dmail'] = 'directmail-folder';
