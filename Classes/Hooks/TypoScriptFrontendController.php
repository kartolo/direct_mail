<?php

namespace DirectMailTeam\DirectMail\Hooks;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use DirectMailTeam\DirectMail\DirectMailUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hooks which is called while FE rendering.
 *
 * Class TypoScriptFrontendController
 */
class TypoScriptFrontendController
{
    /**
     * If a backend user is logged in and
     * a frontend usergroup is specified in the GET parameters, use this
     * group to simulate access to an access protected page with content to be sent.
     *
     * @param $parameters
     * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $typoScriptFrontendController
     */
    public function simulateUsergroup($parameters, \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $typoScriptFrontendController)
    {
        $directMailFeGroup = (int) GeneralUtility::_GET('dmail_fe_group');
        $accessToken = GeneralUtility::_GET('access_token');
        if ($directMailFeGroup > 0 && DirectMailUtility::validateAndRemoveAccessToken($accessToken)) {
            if ($typoScriptFrontendController->fe_user->user) {
                $typoScriptFrontendController->fe_user->user[$typoScriptFrontendController->usergroup_column] = $directMailFeGroup;
            } else {
                $typoScriptFrontendController->fe_user->user = array(
                    $typoScriptFrontendController->fe_user->usergroup_column => $directMailFeGroup,
                );
            }
        }
    }
}
