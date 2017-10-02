<?php
namespace DirectMailTeam\DirectMail\Utility;

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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Static class
 * Functions in this class are used by more than one module
 *
 * @author		Daniel Lorenz	<daniel.lorenz@tritum.de>
 *
 * @package		TYPO3
 * @subpackage	tx_directmail
 */
class FlashMessageRenderer
{
    /**
     * @param FlashMessage $flashMessage
     *
     * @return string
     */
    public function render(FlashMessage $flashMessage) {
        if (version_compare(TYPO3_branch, '8.6', '>=')) {
            return GeneralUtility::makeInstance(FlashMessageRendererResolver::class)
                ->resolve()
                ->render([$flashMessage]);
        }
        if (version_compare(TYPO3_branch, '8.0', '>=')) {
            return $flashMessage->getMessageAsMarkup();
        }
        if (version_compare(TYPO3_branch, '7.6', '>=')) {
            return $flashMessage->render();
        }
        return '';
    }
}
