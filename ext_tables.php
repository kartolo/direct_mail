<?php

declare(strict_types=1);

defined('TYPO3') || die();

// https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/BestPractises/ConfigurationFiles.html
(function () {
    // Category field disabled by default in backend forms.
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('
    	TCEFORM.tt_content.module_sys_dmail_category.disabled = 1
    	TCEFORM.tt_address.module_sys_dmail_category.disabled = 1
    	TCEFORM.fe_users.module_sys_dmail_category.disabled = 1
    	TCEFORM.sys_dmail_group.select_categories.disabled = 1
    ');

    /**
     * Setting up the direct mail module
     * https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Deprecation-97312-DeprecateCSH-relatedMethods.html
     */
/**
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_group', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailg.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('sys_dmail_category', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmailcat.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_DirectMail', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_DirectMail.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_RecipientList', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_RecipientList.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Statistics', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_Statistics.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_MailerEngine', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_MailerEngine.xlf');
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_DirectMailNavFrame_Configuration', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_Configuration.xlf');
    //old
    TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('_MOD_web_DirectMailNavFrame', 'EXT:direct_mail/Resources/Private/Language/locallang_csh_web_txdirectmail.xlf');
*/
    if (TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getExtensionVersion('tt_address')) <= TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger('2.3.5')) {
        include_once(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('direct_mail') . 'Configuration/TCA/Overrides/tt_address.php');
    }
})();
