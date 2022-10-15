<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class AuthCodeUtility 
{
    /**
     * check if the supplied auth code is identical with the counted authCode
     *
     * @param string $submittedAuthCode
     * @param array $recipientRecord
     * @param string $authcodeFieldList
     * @return bool
     */
    public static function validateAuthCode(string $submittedAuthCode, array $recipientRecord, string $authcodeFieldList = 'uid'): bool
    {
        $authCodeToMatch = self::getAuthCode($recipientRecord, $authcodeFieldList);

        if (!empty($submittedAuthCode) && $submittedAuthCode !== $authCodeToMatch) {
            return false;
        }
        return true;
    }

    public static function getAuthCode(array $recipRow, string $authcodeFieldList): string 
    {
        // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.3/Deprecation-94309-DeprecatedGeneralUtilitystdAuthCode.html
        return GeneralUtility::stdAuthCode($recipRow, $authcodeFieldList); //@TODO
    }
}