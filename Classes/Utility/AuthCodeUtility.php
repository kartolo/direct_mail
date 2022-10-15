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
        if (!empty($submittedAuthCode)) {
            $hmac = self::getHmac($recipientRecord, $authcodeFieldList);
            if($submittedAuthCode === $hmac) {
                return true;
            }
            /**
             * @TODO remove in v12
             * for old e-mails
             */
            $authCodeToMatch = self::getAuthCode($recipientRecord, $authcodeFieldList);
            if($submittedAuthCode === $authCodeToMatch) {
                return true;
            }
        }
        return false;
    }

    /**
     * @TODO remove in v12
     * https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.3/Deprecation-94309-DeprecatedGeneralUtilitystdAuthCode.html
     */
    public static function getAuthCode(array $recipientRecord, string $authcodeFieldList): string 
    {
        return GeneralUtility::stdAuthCode($recipientRecord, $authcodeFieldList);
    }

    public static function getHmac(array $recipientRecord, string $authcodeFieldList): string
    {
        $recCopy_temp = [];
        if ($authcodeFieldList) {
            $fieldArr = GeneralUtility::trimExplode(',', $authcodeFieldList, true);
            foreach ($fieldArr as $k => $v) {
                $recCopy_temp[$k] = $recipientRecord[$v];
            }
        } 
        else {
            $recCopy_temp = $recipientRecord;
        }
        $preKey = implode('|', $recCopy_temp);

        return GeneralUtility::hmac($preKey);
    }
}