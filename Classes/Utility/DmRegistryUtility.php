<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DmRegistryUtility
{
    /**
     * Create an access token and save it in the Registry
     *
     * @return string
     */
    public function createAndGetAccessToken(): string
    {
        /* @var \TYPO3\CMS\Core\Registry $registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $accessToken = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(32);
        $registry->set('tx_directmail', 'accessToken', $accessToken);

        return $accessToken;
    }

    /**
     * Create an access token and save it in the Registry
     *
     * @param string $accessToken The access token to validate
     *
     * @return bool
     */
    public function validateAndRemoveAccessToken(string $accessToken): bool
    {
        /* @var \TYPO3\CMS\Core\Registry $registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $registeredAccessToken = $registry->get('tx_directmail', 'accessToken');
        if (!empty($registeredAccessToken) && $registeredAccessToken === $accessToken) {
            $registry->remove('tx_directmail', 'accessToken');
            return true;
        }

        $registry->remove('tx_directmail', 'accessToken');
        return false;
    }
}
