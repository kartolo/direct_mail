<?php

namespace DirectMailTeam\DirectMail\Scheduler;

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

use DirectMailTeam\DirectMail\Dmailer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
* Class tx_directmail_scheduler
*
* @author	Ivan Kartolo <ivan.kartolo@dkd.de>
* @deprecated will be removed in TYPO3 v12.0. Use DirectmailCommand instead.
*/
class DirectmailScheduler extends \TYPO3\CMS\Scheduler\Task\AbstractTask
{
    /**
     * Function executed from scheduler.
     * Send the newsletter
     *
     * @return	bool
     */
    public function execute()
    {
        trigger_error(
            'will be removed in TYPO3 v12.0. Use DirectmailCommand instead.',
            E_USER_DEPRECATED
        );
        /* @var $htmlmail \DirectMailTeam\DirectMail\Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->start();
        $htmlmail->runcron();
        return true;
    }
}
