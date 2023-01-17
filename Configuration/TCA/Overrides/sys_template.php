<?php

defined('TYPO3') || die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('direct_mail', 'Configuration/TypoScript/boundaries/', 'Direct Mail Content Boundaries');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('direct_mail', 'Configuration/TypoScript/plaintext/', 'Direct Mail Plain text');
