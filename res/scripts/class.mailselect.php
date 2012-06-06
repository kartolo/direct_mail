<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
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
 * Used to generate queries for selecting users in the database
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version 	$Id: class.mailselect.php 6012 2007-07-23 12:54:25Z ivankartolo $
 */

require_once(PATH_t3lib.'class.t3lib_querygenerator.php');

/**
 * mailSelect extension class to t3lib_queryGenerator
 *
 */
class mailSelect extends t3lib_queryGenerator	{

	var $allowedTables = array('tt_address','fe_users');

	/**
	 * build a dropdown box. override function from parent class. Limit only to 2 tables.
	 *
	 * @param	string		$name: name of the select-field
	 * @param	string		$cur: table name, which is currently selected
	 * @return	string		HTML select-field
	 * @see t3lib_queryGenerator::mkTableSelect()
	 */
	function mkTableSelect($name, $cur) {
		$out = '<select name="'.$name.'" onChange="submit();">';
		$out .= '<option value=""></option>';
		reset($GLOBALS["TCA"]);
		foreach ($GLOBALS["TCA"] as $tN) {
			if ($GLOBALS["BE_USER"]->check('tables_select',$tN) && in_array($tN, $this->allowedTables))	{
				$out.='<option value="'.$tN.'"'.($tN == $cur ? ' selected':'').'>'.$GLOBALS["LANG"]->sl($GLOBALS["TCA"][$tN]['ctrl']['title']).'</option>';
			}
		}
		$out.='</select>';
		return $out;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.mailselect.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.mailselect.php']);
}

?>