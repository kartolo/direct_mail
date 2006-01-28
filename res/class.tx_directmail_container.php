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
 * @author	Kasper Skårhøj <kasperYYYY>@typo3.com>
 * @author	Thorsten Kahler <thorsten.kahler@dkd.de>
 * @version  $Id$
 */
 
 
 
 /**
  * Container class for auxilliary functions of tx_directmail
  */
class tx_directmail_container	{
	
	var $boundaryStartWrap = '<!--DMAILER_SECTION_BOUNDARY_ | _START-->';
	var $boundaryEnd = '<!--DMAILER_SECTION_BOUNDARY_ END-->';
	
	var $cObj;
	
	/**
	 * In
	 * 
	 * Utilisation of hook [tslib/class.tslib_content.php][CONTENT-cObjValue-proc]
	 * and hook [tslib/class.tslib_content.php][RECORDS-cObjValue-proc]
	 * 
	 * @param	object tslib_cObj	$pObj: back reference to the calling object 
	 * @param	string	$value: the incoming string
	 * @param	object tslib_cObj	$cObj: the (local) tslib_cObj that generated the content
	 * @return string processed string with surrounding boundaries
	 */
	function insert_dMailer_boundaries ( $params, $pObj)	{
		$value = $params['value'];
		$cObj = &$params['cObj'];
		
		if( $GLOBALS['TSFE']->config['config']['insertDmailerBoundaries'] )	{
			if ( $value != '' )	{
				$categoryList = '';		// setting the default
				if ( intval( $cObj->data['module_sys_dmail_category'] ) >= 1 )	{
						// get categories of tt_content element
					$foreign_table = 'sys_dmail_category';
					$select = "$foreign_table.uid";
					$local_table_uidlist = intval( $cObj->data['uid'] );
					$local_table = 'tt_content';
					$mm_table = 'sys_dmail_ttcontent_category_mm';
					$whereClause = '';
					$orderBy = '';
					$res = $cObj->exec_mm_query_uidList( $select, $local_table_uidlist, $mm_table, $foreign_table, $whereClause, '', $orderBy);
					if ( $GLOBALS['TYPO3_DB']->sql_num_rows($res) )	{
						while( $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res) )	{
							$categoryList .= $row['uid'] . ',';
						}
						$categoryList = t3lib_div::rm_endComma($categoryList);
					}
				}
	
					// wrap boundaries around content
				$value = $pObj->wrap( $categoryList, $this->boundaryStartWrap ) . $value . $this->boundaryEnd;			
			}
		}
		return $value;
	}


	function insert_dMailer_boundaries_userFunc ( $content, $conf )	{

			// this check could probably be moved to TS
		if( $GLOBALS['TSFE']->config['config']['insertDmailerBoundaries'] )	{
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
					$local_table = 'tt_content';
					$mm_table = 'sys_dmail_ttcontent_category_mm';
					$whereClause = '';
					$orderBy = '';
					$res = $this->cObj->exec_mm_query_uidList( $select, $local_table_uidlist, $mm_table, $foreign_table, $whereClause, '', $orderBy);
					if ( $GLOBALS['TYPO3_DB']->sql_num_rows($res) )	{
						while( $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res) )	{
							$categoryList .= $row['uid'] . ',';
						}
						$categoryList = t3lib_div::rm_endComma($categoryList);
					}
				}
	
					// wrap boundaries around content
				$content = $this->cObj->wrap( $categoryList, $this->boundaryStartWrap ) . $content . $this->boundaryEnd;			
			}
		}
		return $content;
	}


	function stripInnerBoundaries($content)	{
			// only dummy code at the moment
		return $content;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/class.tx_directmail_container.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/class.tx_directmail_container.php']);
}

?>
