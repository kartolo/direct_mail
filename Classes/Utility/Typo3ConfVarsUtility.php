<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class Typo3ConfVarsUtility
{
    public static function getDMConfig(): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail'];
    }

    public static function getDMConfigDefaultRecipFields(): string
    {
        $dmConfig = self::getDMConfig();
        return $dmConfig['defaultRecipFields'] ?? '';
    }

    public static function getDMConfigAddRecipFields(): string
    {
        $dmConfig = self::getDMConfig();
        return $dmConfig['addRecipFields'] ?? '';
    }

    public static function getDMConfigCronLanguage(): string
    {
        $dmConfig = self::getDMConfig();
        return $dmConfig['cronLanguage'] ?? '';
    }

    public static function getDMConfigMergedFields(): array
    {
        $rowFieldsArray = GeneralUtility::trimExplode(',', self::getDMConfigDefaultRecipFields());
        if ($dmConfigAddRecipFields = self::getDMConfigAddRecipFields()) {
            $rowFieldsArray = array_merge($rowFieldsArray, GeneralUtility::trimExplode(',', $dmConfigAddRecipFields));
        }
        return $rowFieldsArray;
    }

    public static function getDMConfigSendPerCycle(): int
    {
        $dmConfig = self::getDMConfig();
        if (trim($dmConfig['sendPerCycle'])) {
            return (int)(trim($dmConfig['sendPerCycle']));
        }

        return 50;
    }

    public static function getDMConfigNotificationJob(): bool
    {
        $dmConfig = self::getDMConfig();
        return (bool)$dmConfig['notificationJob'];
    }

    public static function getDMConfigSSLVerifyPeer(): bool
    {
        $dmConfig = self::getDMConfig();
        return (bool)$dmConfig['SSLVerifyPeer'];
    }

    public static function getDMConfigSSLVerifyPeerName(): bool
    {
        $dmConfig = self::getDMConfig();
        return (bool)$dmConfig['SSLVerifyPeerName'];
    }

    public static function getDMConfigUseHttpToFetch(): bool
    {
        $dmConfig = self::getDMConfig();
        return (bool)$dmConfig['UseHttpToFetch'];
    }

    public static function getDMConfigCronInt(): int
    {
        $dmConfig = self::getDMConfig();
        return (int)$dmConfig['cronInt'];
    }
}
