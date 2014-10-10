<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
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
 * @author	Kasper Sk�rh�j <kasper@typo3.com>
 * @author  Jan-Erik Revsbech <jer@moccompany.com>
 * @author	Ivan-Dharma Kartolo <ivan.kartolo@dkd.de>
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;

// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require ('conf.php');
require ($BACK_PATH.'init.php');

$LANG->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xml');
$LANG->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xml');
$BE_USER->modAccess($MCONF,1);    // This checks permissions and exits if the users has no permission for entry.

// Make instance:
$SOBE = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Module\\Configuration');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();

?>
