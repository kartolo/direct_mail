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

use DirectMailTeam\DirectMail\Utility\DmRegistryUtility;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Hooks which is called while FE rendering
 *
 * Class TypoScriptFrontendController
 */
class TypoScriptFrontendController
{
    /**
     * If a backend user is logged in and
     * a frontend usergroup is specified in the GET parameters, use this
     * group to simulate access to an access protected page with content to be sent
     */
    public function simulateUsergroup($parameters, \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $typoScriptFrontendController)
    {
        $directMailFeGroup = (int)GeneralUtility::_GET('dmail_fe_group');
        $accessToken = (string)GeneralUtility::_GET('access_token');
        if ($directMailFeGroup > 0 && GeneralUtility::makeInstance(DmRegistryUtility::class)->validateAndRemoveAccessToken($accessToken)) {
            /** @var UserAspect $userAspect */
            $userAspect = $typoScriptFrontendController->getContext()->getAspect('frontend.user');

            // we reset the content if required
            if (!in_array($directMailFeGroup, $userAspect->getGroupIds(), true)) {
                // code was refactor, using a different hook!
                $typoScriptFrontendController->getContext()->setAspect(
                    'frontend.user',
                    new UserAspect($typoScriptFrontendController->fe_user, [$directMailFeGroup])
                );
            }
        }
    }
}
