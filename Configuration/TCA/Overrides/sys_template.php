<?php
defined('TYPO3_MODE') || die('Access denied.');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('direct_mail', 'Configuration/TypoScript/boundaries/', 'Direct Mail Content Boundaries');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('direct_mail', 'Configuration/TypoScript/plaintext/', 'Direct Mail Plain text');
