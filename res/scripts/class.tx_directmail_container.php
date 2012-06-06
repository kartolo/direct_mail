<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2004 Kasper Skaarhoj (kasperYYYY@typo3.com)
 *  (c) 2006 Thorsten Kahler <thorsten.kahler@dkd.de>
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
 * @author		Kasper Sk�rh�j <kasperYYYY>@typo3.com>
 * @author		Thorsten Kahler <thorsten.kahler@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version 	$Id: class.tx_directmail_container.php 30332 2010-02-22 22:28:37Z ivankartolo $
 */

/**
 * Container class for auxilliary functions of tx_directmail
 *
 */
class tx_directmail_container	{

	var $boundaryStartWrap = '<!--DMAILER_SECTION_BOUNDARY_ | -->';
	var $boundaryEnd = '<!--DMAILER_SECTION_BOUNDARY_END-->';

	/**
	 * @var tslib_cObj
	 */
	var $cObj;

	/**
	 * This function wraps HTML comments around the content.
	 * The comments contain the uids of assigned direct mail categories.
	 * It is called as "USER_FUNC" from TS.
	 *
	 * @param	string		$content: incoming HTML code which will be wrapped
	 * @param	array		$conf: pointer to the conf array (TS)
	 * @return	string		content of the email with dmail boundaries
	 */
	function insert_dMailer_boundaries ($content,$conf) {
		if (isset( $conf['useParentCObj']) && $conf['useParentCObj']) {
			$this->cObj = $conf['parentObj']->cObj;
		}

			// this check could probably be moved to TS
		if ($GLOBALS['TSFE']->config['config']['insertDmailerBoundaries']) {
			if ( $content != '' )	{
				$categoryList = '';		// setting the default
				if ( intval( $this->cObj->data['module_sys_dmail_category'] ) >= 1 )	{
						// if content type "RECORDS" we have to strip off
						// boundaries from indcluded records
					if ( $this->cObj->data['CType'] == 'shortcut' )	{
						$content = $this->stripInnerBoundaries($content);
					}

						// get categories of tt_content element
					$foreign_table = 'sys_dmail_category';
					$select = "$foreign_table.uid";
					$local_table_uidlist = intval( $this->cObj->data['uid'] );
					$mm_table = 'sys_dmail_ttcontent_category_mm';
					$whereClause = '';
					$orderBy = $foreign_table . '.uid';
					$res = $this->cObj->exec_mm_query_uidList(
						$select,
						$local_table_uidlist,
						$mm_table,
						$foreign_table,
						$whereClause,
						'',
						$orderBy);
					if ( $GLOBALS['TYPO3_DB']->sql_num_rows($res) )	{
						while( $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res) )	{
							$categoryList .= $row['uid'] . ',';
						}
						$categoryList = rtrim($categoryList, ",");
					}
				}
					// wrap boundaries around content
				$content = $this->cObj->wrap( $categoryList, $this->boundaryStartWrap ) . $content . $this->boundaryEnd;
			}
		}
		return $content;
	}

	/**
	 * remove boundaries from TYPO3 content
	 *
	 * @param	string		$content: the content with boundaries in comment
	 * @return	string		the content without boundaries
	 */
	function stripInnerBoundaries($content)	{
			// only dummy code at the moment
		$searchString = $this->cObj->wrap( '[\d,]*', $this->boundaryStartWrap );
		$content = preg_replace( '/'.$searchString.'/', '', $content );
		$content = preg_replace( '/'.$this->boundaryEnd.'/', '', $content );
		return $content;
	}

	/**
	 * Breaking lines into fixed length lines, using t3lib_div::breakLinesForEmail()
	 *
	 * @param	string	$content: The string to break
	 * @param	array	$conf: configuration options: linebreak, charWidth; stdWrap enabled	 * @return	string		Processed string
	 * @return string
	 * @see t3lib_div::breakLinesForEmail()
	 */
	function breakLines( $content, $conf )	{
		$linebreak = $GLOBALS['TSFE']->cObj->stdWrap( ( $conf['linebreak'] ? $conf['linebreak'] : chr(32).chr(10) ), $conf['linebreak.'] );
		$charWidth = $GLOBALS['TSFE']->cObj->stdWrap( ( $conf['charWidth'] ? intval( $conf['charWidth'] ) : 76 ), $conf['charWidth.'] );
		
		if (t3lib_div::compat_version('4.6')) {
			return t3lib_utility_Mail::breakLinesForEmail($content, $linebreak, $charWidth);
		} else {
			return t3lib_div::breakTextForEmail( $content, $linebreak, $charWidth );
		}
	}
	
	/**
	 * inserting boundaries for each sitemap point.
	 * @param string $content: the content string
	 * @param array $conf: the TS conf
	 * @return string $content: the string wrapped with boundaries
	 */
	public function insertSitemapBoundaries($content, $conf) {
		$uid = $this->cObj->data['uid'];
		$content = '';
	
		$categories = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'sys_dmail_ttcontent_category_mm', 'uid_local=' . (int)$uid, '', 'sorting');
		if (count($categories) > 0) {
			$categoryList = array();
			foreach($categories as $category) {
				$categoryList[] = $category['uid_foreign'];
			}
			$content = '<!--DMAILER_SECTION_BOUNDARY_' . implode(',', $categoryList) . '-->|<!--DMAILER_SECTION_BOUNDARY_END-->';
		}
	
		return $content;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_container.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_container.php']);
}

?>
