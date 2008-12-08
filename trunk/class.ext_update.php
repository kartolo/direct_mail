<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
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
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   48: class ext_update
 *   55:     function main()
 *   80:     function access()
 *
 * TOTAL FUNCTIONS: 2
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * Class for updating Direct Mail to version 2.0.0
 *
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class ext_update  {

	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string		HTML
	 */
	function main()	{
		global $LANG, $BE_USER;

		$LANG->includeLLFile('EXT:direct_mail/locallang/locallang_mod2-6.xml');
		require_once('mod6/class.tx_directmail_configuration.php');
		$dmail = t3lib_div::makeInstance('tx_directmail_configuration');
		$dmail->init();

		if (!t3lib_div::GPvar('do_update'))	{
			$onClick = "document.location='".t3lib_div::linkThisScript(array('do_update'=>1))."'; return false;";
			return htmlspecialchars($LANG->getLL('update_convert_now')).'
				<br /><br />
				<form action=""><input type="submit" value="'.htmlspecialchars($LANG->getLL('update_convert_do_it_now')).'" onclick="'.htmlspecialchars($onClick).'"></form>
			';
		} else {
			$dmail->main();
			return $dmail->cmd_convertCategories();
		}
	}

	/**
	 * Checks how many rows are found and returns true if there are any
	 *
	 * @return	boolean		true if user have access, otherwise false
	 */
	function access() {
		global $TYPO3_DB;
			// We cannot update before the extension is installed: required tables are not yet in TCA
		if (t3lib_extMgm::isLoaded('direct_mail')) {
			$res = $TYPO3_DB->exec_SELECTquery(
				'count(*)',
				'sys_dmail_category',
				'1=1'
				);
				// If we already have categories, do not try to update now.
			if ($TYPO3_DB->sql_error() || $TYPO3_DB->sql_num_rows($res)) {
				return FALSE;
			} else {
					// If we do not find any Direct mail folder, do not try to update now.
				require_once('mod6/class.tx_directmail_configuration.php');
				$dmail = t3lib_div::makeInstance('tx_directmail_configuration');
				$dmail->init();
				if (!is_array($dmail->modList['rows'])) {
					return FALSE;
				}
			}
			return TRUE;
		} else {
			return FALSE;
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.ext_update.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/class.ext_update.php']);
}

?>