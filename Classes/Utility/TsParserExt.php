<?php

namespace DirectMailTeam\DirectMail\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Ivan Kartolo <ivan at kartolo dot de>
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
 * Class that renders fields for the extensionmanager configuration
 *
 * $Id:$
 *
 * @author  Ivan Kartolo <ivan at kartolo dot de>
 * @package TYPO3
 * @subpackage direct_mail
 */
class TsParserExt {

	/**
	 * displaying a message to click the update in the extension config
	 *
	 * @return string    $out: the html message
	 */
	static public function displayMessage() {

		$parameters = array(
			'tx_extensionmanager_tools_extensionmanagerextensionmanager[extensionKey]' => 'direct_mail',
			'tx_extensionmanager_tools_extensionmanagerextensionmanager[action]' => 'show',
			'tx_extensionmanager_tools_extensionmanagerextensionmanager[controller]' => 'UpdateScript',
		);
		$link = \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleUrl('tools_ExtensionmanagerExtensionmanager', $parameters);

		$out = "
		<div style=\"position:absolute;top:10px;right:10px; width:300px;\">
			<div class=\"typo3-message message-information\">
					<div class=\"message-header\">" . $GLOBALS['LANG']->sL('LLL:EXT:direct_mail/locallang/locallang_mod2-6.xml:update_optionHeader') . "</div>
					<div class=\"message-body\">
						" . $GLOBALS['LANG']->sL("LLL:EXT:direct_mail/locallang/locallang_mod2-6.xml:update_optionMsg") . "<br />
						<a style=\"text-decoration:underline;\" href=\"" . $link . "\">
						" . $GLOBALS['LANG']->sL("LLL:EXT:direct_mail/locallang/locallang_mod2-6.xml:update_optionLink") . "</a>
					</div>
				</div>
			</div>
			";

		return $out;
	}
}

?>