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
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version		$Id: class.tx_directmail_select_categories.php 6012 2007-07-23 12:54:25Z ivankartolo $
 */

require_once (PATH_t3lib.'class.t3lib_page.php');
require_once (t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_static.php');

/**
 * Localize categories for backend forms
 *
 */
class tx_directmail_select_categories {
	var $sys_language_uid = 0;
	var $collate_locale = 'C';

	/**
	 * Get the localization of the select field items (right-hand part of form)
	 * Referenced by TCA
	 *
	 * @param	array		$params: array of searched translation
	 * @return	void		...
	 */
	function get_localized_categories($params)	{
		global $LANG;

/*
		$params['items'] = &$items;
		$params['config'] = $config;
		$params['TSconfig'] = $iArray;
		$params['table'] = $table;
		$params['row'] = $row;
		$params['field'] = $field;
*/
		$config = $params['config'];
		$table = $config['itemsProcFunc_config']['table'];

			// initialize backend user language
		if ($LANG->lang && t3lib_extMgm::isLoaded('static_info_tables')) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				//'sys_language.uid,static_languages.lg_collate_locale',
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode = static_languages.uid',
				'static_languages.lg_typo3 = '.$GLOBALS['TYPO3_DB']->fullQuoteStr($LANG->lang,'static_languages').
					t3lib_pageSelect::enableFields('sys_language').
					t3lib_pageSelect::enableFields('static_languages')
				);
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
				$this->collate_locale = $row['lg_collate_locale'];
			}
		}

		if (is_array($params['items']) && !empty($params['items'])) {
			foreach ($params['items'] as $k => $item ) {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'*',
					$table,
						'uid='.intval($item[1])
				);
				while($rowCat = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					if($localizedRowCat = tx_directmail_static::getRecordOverlay($table,$rowCat,$this->sys_language_uid,'')) {
						$params['items'][$k][0] = $localizedRowCat['category'];
					}
				}
			}
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_select_categories.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_select_categories.php']);
}
?>
