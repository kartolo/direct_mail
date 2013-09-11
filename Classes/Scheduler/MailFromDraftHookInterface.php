<?php
namespace DirectMailTeam\DirectMail\Scheduler;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2014 Bernhard Kraft <kraft@webconsulting.at>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


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
interface MailFromDraftHookInterface {

	/**
	 * Gets called before a copy of the direct mail draft record gets inserted into the database.
	 *
	 * The draft record is passed via reference by the key 'draftRecord' in the $hookParams argument.
	 *
	 * @param	array		Parameters to the hook. All passed by reference
	 * @param	object		A reference to the calling object instance
	 * @return	void
	 */
	public function postInsertClone($hookParams, &$parentObject);

	/**
	 * Gets called when the content of the mail has already been fetched and the direct mail record is ready to get sent by the direct mail engine upon next invocation.
	 *
	 * The values 'scheduled' and 'issent' in the hook parameter key 'updateData' are
	 * responsible for marking the direct mail as "to be sent".
	 *
	 * @param	array		Parameters to the hook. All passed by reference
	 * @param	object		A reference to the calling object instance
	 * @return	void
	 */
	public function enqueueClonedDmail($hookParams, &$parentObject);

}

