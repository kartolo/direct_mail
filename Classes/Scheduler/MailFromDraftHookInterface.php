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

/**
 * Interface for classes which hook into tx_directmail_Scheduler_MailFromDraft
 *
 * The mentioned class is responsible for automatically sending direct mail
 * newsletters via a scheduler task.
 *
 * @author		Bernhard Kraft <kraft@webconsulting.at>
 *
 * @package		TYPO3
 * @subpackage		tx_directmail
 */
interface MailFromDraftHookInterface
{

    /**
     * Gets called before a copy of the direct mail draft record gets inserted into the database.
     *
     * The draft record is passed via reference by the key 'draftRecord' in the $hookParams argument.
     *
     * @param	array $hookParams Parameters to the hook. All passed by reference
     * @param	object $parentObject A reference to the calling object instance
     *
     * @return	void
     */
    public function postInsertClone(array $hookParams, &$parentObject);

    /**
     * Gets called when the content of the mail has already been fetched and the direct mail record is ready to get sent by the direct mail engine upon next invocation.
     *
     * The values 'scheduled' and 'issent' in the hook parameter key 'updateData' are
     * responsible for marking the direct mail as "to be sent".
     *
     * @param	array		$hookParams Parameters to the hook. All passed by reference
     * @param	object		$parentObject A reference to the calling object instance
     *
     * @return	void
     */
    public function enqueueClonedDmail(array $hookParams, &$parentObject);
}
