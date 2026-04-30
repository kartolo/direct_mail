<?php

declare(strict_types=1);

defined('TYPO3') || die();

// https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/BestPractises/ConfigurationFiles.html
(function () {
    if (TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getExtensionVersion('tt_address')) <= TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger('2.3.5')) {
        include_once(TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('direct_mail') . 'Configuration/TCA/Overrides/tt_address.php');
    }
})();
