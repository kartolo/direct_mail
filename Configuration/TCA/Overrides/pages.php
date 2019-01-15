<?php
defined('TYPO3_MODE') or die();

// pages modified
$GLOBALS['TCA']['pages']['columns']['module']['config']['items'][] =
    ['LLL:EXT:direct_mail/Resources/Private/Language/locallang_tca.xlf:pages.module.I.5', 'dmail'];

if (is_array($GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'])) {
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes']['contains-dmail'] = 'tcarecords-pages-contains-dmail';
}
