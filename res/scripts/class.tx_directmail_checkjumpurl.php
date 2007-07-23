<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2004 Kasper Skaarhoj (kasperYYYY@typo3.com)
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
 * @author		Kasper Skårhøj <kasperYYYY>@typo3.com>
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version		$Id$
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   55: class tx_directmail_checkjumpurl
 *   63:     function checkDataSubmission (&$feObj)
 *
 * TOTAL FUNCTIONS: 1
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * JumpUrl processing hook on class.tslib_fe.php
 *
 */
class tx_directmail_checkjumpurl	{

	/**
	 * Get the url to jump to as set by Direct Mail
	 *
	 * @param	object		&$feObj: reference to invoking instance
	 * @return	void		...
	 */
	function checkDataSubmission (&$feObj)	{
		global $TCA, $TYPO3_DB, $TYPO3_CONF_VARS;

		$JUMPURL_VARS = t3lib_div::_GET();

		$mid = $JUMPURL_VARS['mid'];
		$rid = $JUMPURL_VARS['rid'];
		$aC = $JUMPURL_VARS['aC'];

		$jumpurl = $feObj->jumpurl;

		if ($mid && is_array($TCA['sys_dmail']))	{
			$temp_recip=explode('_',$rid);
			$url_id=0;
			if (t3lib_div::testInt($jumpurl))	{
				$temp_res = $TYPO3_DB->exec_SELECTquery(
					'mailContent',
					'sys_dmail',
					'uid='.intval($mid)
					);
				if ($row = $TYPO3_DB->sql_fetch_assoc($temp_res))	{
					$temp_unpackedMail = unserialize($row['mailContent']);
					$url_id = $jumpurl;
					if ($jumpurl>=0)	{
						$responseType=1;	// Link (number)
						$jumpurl = $temp_unpackedMail['html']['hrefs'][$url_id]['absRef'];
					} else {
						$responseType=2;	// Link (number, plaintext)
						$jumpurl = $temp_unpackedMail['plain']['link_ids'][abs($url_id)];
					}

					$jumpurl = t3lib_div::htmlspecialchars_decode($jumpurl);

					switch($temp_recip[0])	{
						case 't':
							$theTable = 'tt_address';
						break;
						case 'f':
							$theTable = 'fe_users';
						break;
						default:
							$theTable='';
						break;
					}
					if ($theTable)	{
						$recipRow = $feObj->sys_page->getRawRecord($theTable,$temp_recip[1]);
						if (is_array($recipRow))	{
							$authCode = t3lib_div::stdAuthCode($recipRow,($row['authcode_fieldList'])?$row['authcode_fieldList']:'uid');
							$rowFieldsArray = explode(',', $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['defaultRecipFields']);
							if ($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields'])	{
								$rowFieldsArray = array_merge($rowFieldsArray, explode(',',$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields']));
							}
							reset($rowFieldsArray);
							while(list(,$substField)=each($rowFieldsArray))	{
								$jumpurl = str_replace('###USER_'.$substField.'###', $recipRow[$substField], $jumpurl);
							}
							$jumpurl = str_replace('###SYS_TABLE_NAME###', $theTable, $jumpurl);	// Put in the tablename of the userinformation
							$jumpurl = str_replace('###SYS_MAIL_ID###', $mid, $jumpurl);	// Put in the uid of the mail-record
							$jumpurl = str_replace('###SYS_AUTHCODE###', ($aC)?$aC:$authCode, $jumpurl);	// If authCode is provided, keep it.
						}
					}
				}

				$TYPO3_DB->sql_free_result($temp_res);

				if (!$jumpurl)	die('Error: No further link. Please report error to the mail sender.');
			} else {
				$responseType=-1;	// received (url, dmailerping)
			}
			if ($responseType!=0)	{
				$insertFields = array(
					'mid' => intval($mid),
					'rtbl' => $temp_recip[0],
					'rid' => intval($temp_recip[1]),
					'tstamp' => time(),
					'url' => $jumpurl,
					'response_type' => intval($responseType),
					'url_id' => intval($url_id)
				);

				$res = $TYPO3_DB->exec_INSERTquery(
					'sys_dmail_maillog',
					$insertFields
					);
			}
		}

		$feObj->jumpurl = $jumpurl;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_checkjumpurl.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_checkjumpurl.php']);
}

?>
