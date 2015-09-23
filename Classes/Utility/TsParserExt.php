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
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class that renders fields for the extensionmanager configuration
 *
 * @author  Ivan Kartolo <ivan at kartolo dot de>
 * @package TYPO3
 * @subpackage direct_mail
 */
class TsParserExt {

	/**
	 * Displaying a message to click the update in the extension config
	 *
	 * @return string    $out: the html message
	 */
	static public function displayMessage() {

		$parameters = array(
			'tx_extensionmanager_tools_extensionmanagerextensionmanager[extensionKey]' => 'direct_mail',
			'tx_extensionmanager_tools_extensionmanagerextensionmanager[action]' => 'show',
			'tx_extensionmanager_tools_extensionmanagerextensionmanager[controller]' => 'UpdateScript',
		);
		$link = BackendUtility::getModuleUrl('tools_ExtensionmanagerExtensionmanager', $parameters);

		$out = "
		<div style=\"position:absolute;top:10px;right:10px; width:300px;\">
			<div class=\"typo3-message message-information\">
					<div class=\"message-header\">" . $GLOBALS['LANG']->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xml:update_optionHeader') . "</div>
					<div class=\"message-body\">
						" . $GLOBALS['LANG']->sL("LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xml:update_optionMsg") . "<br />
						<a style=\"text-decoration:underline;\" href=\"" . $link . "\">
						" . $GLOBALS['LANG']->sL("LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xml:update_optionLink") . "</a>
					</div>
				</div>
			</div>
			";

		return $out;
	}
}

?>