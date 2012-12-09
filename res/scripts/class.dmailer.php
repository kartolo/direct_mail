<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2004 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  (c) 2004-2006 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
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
 * @author		Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author      Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version		$Id: class.dmailer.php 30973 2010-03-10 17:41:28Z ivankartolo $
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   97: class dmailer extends t3lib_htmlmail
 *  114:     function dmailer_prepare($row)
 *  195:     function removeHTMLComments($content)
 *  208:     function dmailer_sendAdvanced($recipRow,$tableNameChar)
 *  296:     function dmailer_sendSimple($addressList)
 *  326:     function dmailer_getBoundaryParts($cArray,$userCategories)
 *  370:     function getListOfRecipentCategories($table,$uid)
 *  443:     function dmailer_masssend_list($query_info,$mid)
 *  523:     function shipOfMail($mid,$recipRow,$tKey)
 *  562:     function convertFields($recipRow)
 *  577:     function dmailer_setBeginEnd($mid,$key)
 *  614:     function dmailer_howManySendMails($mid,$rtbl='')
 *  636:     function dmailer_isSend($mid,$rid,$rtbl)
 *  657:     function dmailer_getSentMails($mid,$rtbl)
 *  685:     function dmailer_addToMailLog($mid,$rid,$size,$parsetime,$html,$email)
 *  714:     function runcron()
 *  768:     function start($user_dmailer_sendPerCycle=50,$user_dmailer_lang='en')
 *  787:     function dmailer_log($writeMode,$logMsg)
 *  824:     function useBase64()
 *  836:     function use8Bit()
 *  848:     function sendTheMail ()
 *  918:     function addHTML($file)
 *  941:     function getHTMLContentType()
 *  951:     function constructHTML ($boundary)
 *  975:     function constructHTML_media($boundary)
 *  999:     function getMimeType($url)
 *
 * TOTAL FUNCTIONS: 26
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */
/**
 *
 * SETTING UP a cron job on a UNIX box for distribution:
 *
 * Write at the shell:
 *
 * crontab -e
 *
 *
 * Then enter this line follow by a line-break:
 *
 * * * * * /www/[path-to-your-typo3-site]/typo3/mod/web/dmail/dmailerd.phpcron
 *
 * Every minute the cronjob checks if there are mails in the queue.
 * If there are mails, 100 is sent at a time per job.
 */
// TODO: remove htmlmail
//require_once(PATH_t3lib.'class.t3lib_htmlmail.php');
require_once(PATH_t3lib.'class.t3lib_befunc.php');

/**
 * Class, doing the sending of Direct-mails, eg. through a cron-job
 *
 */
class dmailer {

	/**
	 * @var int amount of mail sent in one batch
	 */
	var $sendPerCycle = 50;

	/**
	 * @var int
	 * TODO: do we still need this?
	 */
	var $dontEncodeHeader = 1;
	var $logArray = array();
	var $massend_id_lists = array();
	var $mailHasContent;
	var $flag_html = 0;
	var $flag_plain = 0;
	var $includeMedia = 0;
	var $flowedFormat = 0;
	var $user_dmailerLang = 'en';
	var $mailObject = NULL;
	var $testmail = false;

	/**
	 * @var string
	 * Todo: need this in swift?
	 */
	var $charset = '';

	/**
	 * @var string
	 * Todo: need this in swift?
	 */
	var $encoding = '';

	/**
	 * @var array the mail parts (HTML and Plain, incl. href and link to media)
	 */
	var $theParts = array();

	/**
	 * @var string the mail message ID
	 * todo: do we still need this
	 */
	var $messageid = '';

	/**
	 * @var string the subject of the mail
	 */
	var $subject = '';

	/**
	 * @var string the sender mail
	 */
	var $from_email = '';

	/**
	 * @var string the sender's name
	 */
	var $from_name = '';

	/**
	 * @var string organisation of the mail
	 */
	var $organisation = '';

	/**
	 * special header to identify returned mail
	 * @var string
	 */
	var $TYPO3MID;

	var $replyto_email = '';
	var $replyto_name = '';
	var $priority = 0;
	var $mailer;
	var $authCode_fieldList;
	var $dmailer;
	var $mediaList;

	var $tempFileList = array();

	/**
	 * Preparing the Email. Headers are set in global variables
	 *
	 * @param	array		$row: Record from the sys_dmail table
	 * @return	void
	 */
	function dmailer_prepare($row)	{
		global $LANG;

		$sys_dmail_uid = $row['uid'];
		if ($row['flowedFormat']) {
			$this->flowedFormat = 1;
		}
		if ($row['charset']) {
			if ($row['type'] == 0) {
				$this->charset = "utf-8";
			} else {
				$this->charset = $row['charset'];
			}
		}

		$this->encoding = $row['encoding'];

		$this->theParts  = unserialize(base64_decode($row['mailContent']));
		$this->messageid = $this->theParts['messageid'];

		$this->subject = $LANG->csConvObj->conv($row['subject'], $LANG->charSet, $this->charset);

		$this->from_email = $row['from_email'];
		$this->from_name = ($row['from_name'] ? $LANG->csConvObj->conv($row['from_name'], $LANG->charSet, $this->charset) : '');

		$this->replyto_email = ($row['replyto_email'] ? $row['replyto_email'] : '');
		$this->replyto_name  = ($row['replyto_name'] ? $LANG->csConvObj->conv($row['replyto_name'], $LANG->charSet, $this->charset) : '');

		$this->organisation  = ($row['organisation']  ? $LANG->csConvObj->conv($row['organisation'], $LANG->charSet, $this->charset) : '');

		$this->priority      = tx_directmail_static::intInRangeWrapper($row['priority'], 1, 5);
		$this->mailer        = 'TYPO3 Direct Mail module';
		$this->authCode_fieldList = ($row['authcode_fieldList'] ? $row['authcode_fieldList'] : 'uid');

		$this->dmailer['sectionBoundary'] = '<!--DMAILER_SECTION_BOUNDARY';
		$this->dmailer['html_content']    =  $this->theParts['html']['content'];
		$this->dmailer['plain_content']   = $this->theParts['plain']['content'];
		$this->dmailer['messageID']       = $this->messageid;
		$this->dmailer['sys_dmail_uid']   = $sys_dmail_uid;
		$this->dmailer['sys_dmail_rec']   = $row;

		$this->dmailer['boundaryParts_html'] = explode($this->dmailer['sectionBoundary'], '_END-->'.$this->dmailer['html_content']);
		foreach ($this->dmailer['boundaryParts_html'] as $bKey => $bContent) {
			$this->dmailer['boundaryParts_html'][$bKey] = explode('-->', $bContent, 2);

				// Remove useless HTML comments
			if (substr($this->dmailer['boundaryParts_html'][$bKey][0],1) == 'END') {
				$this->dmailer['boundaryParts_html'][$bKey][1] = $this->removeHTMLComments($this->dmailer['boundaryParts_html'][$bKey][1]);
			}

				// Now, analyzing which media files are used in this part of the mail:
			$mediaParts = explode('cid:part', $this->dmailer['boundaryParts_html'][$bKey][1]);
			reset($mediaParts);
			next($mediaParts);
			while(list(,$part) = each($mediaParts)) {
				$this->dmailer['boundaryParts_html'][$bKey]['mediaList'] .= ',' . strtok($part, '.');
			}
		}
		$this->dmailer['boundaryParts_plain'] = explode($this->dmailer['sectionBoundary'], '_END-->'.$this->dmailer['plain_content']);
		foreach ($this->dmailer['boundaryParts_plain'] as $bKey => $bContent) {
			$this->dmailer['boundaryParts_plain'][$bKey] = explode('-->', $bContent, 2);
		}

		$this->flag_html    = ($this->theParts['html']['content']  ? 1 : 0);
		$this->flag_plain   = ($this->theParts['plain']['content'] ? 1 : 0);
		$this->includeMedia = $row['includeMedia'];
	}

	/**
	 * Removes html comments when outside script and style pairs
	 *
	 * @param	string		$content: The email content
	 * @return	string		HTML content without comments
	 */
	function removeHTMLComments($content) {
		$content = preg_replace('/\/\*<!\[CDATA\[\*\/[\t\v\n\r\f]*<!--/', '/*<![CDATA[*/', $content);
		$content = preg_replace('/[\t\v\n\r\f]*<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
		return preg_replace('/\/\*<!\[CDATA\[\*\//', '/*<![CDATA[*/<!--', $content);
	}


	/**
	 * Replace the marker with recipient data and then send it
	 *
	 * @param	string		$content: the HTML or plaintext part
	 * @param	array		$recipRow: Recipient's data array
	 * @param	array		$markers: existing markers that are mail-specific, not user-specific
	 * @return	integer		which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
	 */
	function replaceMailMarkers($content, $recipRow, $markers) {
		$rowFieldsArray = t3lib_div::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['defaultRecipFields']);
		if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']) {
			$rowFieldsArray = array_merge($rowFieldsArray, t3lib_div::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']));
		}

		foreach ($rowFieldsArray as $substField) {
			$subst = $GLOBALS['LANG']->csConvObj->conv($recipRow[$substField], $GLOBALS['LANG']->charSet, $this->charset);
			$markers['###USER_' . $substField . '###'] = $subst;
		}

			// uppercase fields with uppercased values
		$uppercaseFieldsArray = array('name', 'firstname');
		foreach ($uppercaseFieldsArray as $substField) {
			$subst = $GLOBALS['LANG']->csConvObj->conv($recipRow[$substField], $GLOBALS['LANG']->charSet, $this->charset);
			$markers['###USER_' . strtoupper($substField) . '###'] = strtoupper($subst);
		}

			// Hook allows to manipulate the markers to add salutation etc.
		if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'])) {
			$mailMarkersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'];
			if (is_array($mailMarkersHook)) {
				$hookParameters = array(
					'row'     => &$recipRow,
					'markers' => &$markers,
				);
				$hookReference = &$this;
				foreach ($mailMarkersHook as $hookFunction)	{
					t3lib_div::callUserFunction($hookFunction, $hookParameters, $hookReference);
				}
			}
		}

		if (t3lib_div::compat_version('4.2.0')) {
			//function exists in 4.2.x
			return t3lib_parsehtml::substituteMarkerArray($content, $markers);
		} else {
			return tx_directmail_static::substituteMarkerArray($content, $markers);
		}
	}


	/**
	 * Replace the marker with recipient data and then send it
	 *
	 * @param	array		$recipRow: Recipient's data array
	 * @param	string		$tableNameChar: Tablename, from which the recipient come from
	 * @return	integer		which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
	 */
	function dmailer_sendAdvanced($recipRow, $tableNameChar) {
		$returnCode = 0;
		$tempRow = array();

		//check recipRow for HTML
		foreach($recipRow as $k => $v) {
			$tempRow[$k] = htmlspecialchars($v);
		}
		unset($recipRow);
		$recipRow = $tempRow;

		if ($recipRow['email'])	{
			$midRidId  = 'MID' . $this->dmailer['sys_dmail_uid'] . '_' . $tableNameChar . $recipRow['uid'];
			$uniqMsgId = md5(microtime()) . '_' . $midRidId;
			$authCode = t3lib_div::stdAuthCode($recipRow, $this->authCode_fieldList);

			$additionalMarkers = array(
					// Put in the tablename of the userinformation
				'###SYS_TABLE_NAME###'      => $tableNameChar,
					// Put in the uid of the mail-record
				'###SYS_MAIL_ID###'         => $this->dmailer['sys_dmail_uid'],
				'###SYS_AUTHCODE###'        => $authCode,
					// Put in the unique message id in HTML-code
				$this->dmailer['messageID'] => $uniqMsgId,
			);

			$this->mediaList = '';
			$this->theParts['html']['content'] = '';
			if ($this->flag_html && ($recipRow['module_sys_dmail_html'] || $tableNameChar == 'P')) {
				$tempContent_HTML = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'], $recipRow['sys_dmail_categories_list']);
				if ($this->mailHasContent) {
					$tempContent_HTML = $this->replaceMailMarkers($tempContent_HTML, $recipRow, $additionalMarkers);
					$this->theParts['html']['content'] = $this->encodeMsg($tempContent_HTML);
					$returnCode|=1;
				}
			}

				// Plain
			$this->theParts['plain']['content'] = '';
			if ($this->flag_plain) {
				$tempContent_Plain = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'], $recipRow['sys_dmail_categories_list']);
				if ($this->mailHasContent) {
					$tempContent_Plain = $this->replaceMailMarkers($tempContent_Plain, $recipRow, $additionalMarkers);
					if (trim($this->dmailer['sys_dmail_rec']['use_rdct']) || trim($this->dmailer['sys_dmail_rec']['long_link_mode'])) {
						$tempContent_Plain = t3lib_div::substUrlsInPlainText($tempContent_Plain, $this->dmailer['sys_dmail_rec']['long_link_mode']?'all':'76', $this->dmailer['sys_dmail_rec']['long_link_rdct_url']);
					}
					$this->theParts['plain']['content'] = $this->encodeMsg($tempContent_Plain);
					$returnCode|=2;
				}
			}

			$this->TYPO3MID = $midRidId . '-' . md5($midRidId);

			// recipient swiftmailer style
			// check if the email valids
			$recipient = array();
			if (t3lib_div::validEmail($recipRow['email'])) {
				if (!empty($recipRow['name'])) {
					// if there's a name
					$recipient = array(
						$recipRow['email'] => $GLOBALS['LANG']->csConvObj->conv($recipRow['name'], $GLOBALS['LANG']->charSet, $this->charset),
					);
				} else {
					// if only email is given
					$recipient = array(
						$recipRow['email'],
					);
				}
			}


			if ($returnCode && !empty($recipient)) {
				$this->sendTheMail($recipient);
			}
		}
		return $returnCode;
	}

	/**
	 * Send a simple email (without personalizing)
	 *
	 * @param	string		$addressList: list of recipient address, comma list of emails
	 * @return	boolean		...
	 */
	function dmailer_sendSimple($addressList) {

		if ($this->theParts['html']['content']) {
			$this->theParts['html']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'],-1));
		} else {
			$this->theParts['html']['content'] = '';
		}
		if ($this->theParts['plain']['content']) {
			$this->theParts['plain']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'],-1));
		} else {
			$this->theParts['plain']['content'] = '';
		}

		$recipients = explode(",",$addressList);
		foreach ($recipients as $recipient) {
			$this->sendTheMail($recipient);
		}

		return true;
	}

	/**
	 * This function checks which content elements are suppsed to be sent to the recipient.
	 * tslib_content inserts dmail boudary markers in the content specifying which elements are intended for which categories,
	 * this functions check if the recipeient is subscribing to any of these categories and
	 * filters out the elements that are inteded for categories not subscribed to.
	 *
	 * @param	array		$cArray: array of content split by dmail boundary
	 * @param	string		$userCategories: The list of categories the user is subscribing to.
	 * @return	string		Content of the email, which the recipient subscribed
	 */
	function dmailer_getBoundaryParts($cArray,$userCategories)	{
		$returnVal='';
		$this->mailHasContent = FALSE;
		$boundaryMax = count($cArray)-1;
		foreach ($cArray as $bKey => $cP) {
			$key = substr($cP[0],1);
			$isSubscribed = FALSE;
			if (!$key || (intval($userCategories) == -1)) {
				$returnVal .= $cP[1];
				$this->mediaList .= $cP['mediaList'];
				if ($cP[1]) {
					$this->mailHasContent = TRUE;
				}
			} elseif ($key == 'END') {
				$returnVal .= $cP[1];
				$this->mediaList .= $cP['mediaList'];
					// There is content and it is not just the header and footer content, or it is the only content because we have no direct mail boundaries.
				if (($cP[1] && !($bKey == 0 || $bKey == $boundaryMax)) || count($cArray) == 1) {
					$this->mailHasContent = TRUE;
				}
			} else {
				foreach(explode(',',$key) as $group) {
					if(t3lib_div::inList($userCategories,$group)) {
						$isSubscribed = TRUE;
					}
				}
				if ($isSubscribed) {
					$returnVal .= $cP[1];
					$this->mediaList .= $cP['mediaList'];
					$this->mailHasContent = TRUE;
				}
			}
		}
		return $returnVal;
	}

	/**
	 * Get the list of categories ids subscribed to by recipient $uid from table $table
	 *
	 * @param	string		$table:	Tablename of the recipient
	 * @param	integer		$uid: uid of the recipient
	 * @return	string		list of categories
	 */
	function getListOfRecipentCategories($table, $uid) {
		if ($table == 'PLAINLIST') {
			return '';
		}

		t3lib_div::loadTCA($table);
		$mm_table = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid_foreign',
			$mm_table.' LEFT JOIN '.$table.' ON '.$mm_table.'.uid_local = '.$table.'.uid',
			$mm_table.'.uid_local='.intval($uid).
				t3lib_BEfunc::deleteClause($table)
			);
		$list = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$list[] = $row['uid_foreign'];
		}
		return implode(',', $list);
	}

	/**
	 * Mass send to recipient in the list
	 *
	 * @param	array		$query_info: List of recipients' ID in the sys_dmail table
	 * @param	integer		$mid: directmail ID. UID of the sys_dmail table
	 * @return	boolean		...
	 */
	function dmailer_masssend_list($query_info, $mid) {
		/** @var $LANG language */
		global $LANG;

		$enableFields['tt_address'] = 'tt_address.deleted=0 AND tt_address.hidden=0';
		$enableFields['fe_users']   = 'fe_users.deleted=0 AND fe_users.disable=0';

		$c = 0;
		$returnVal = true;
		if (is_array($query_info['id_lists'])) {
			foreach ($query_info['id_lists'] as $table => $listArr) {
				if (is_array($listArr))	{
					$ct = 0;
						// Find tKey
					if ($table=='tt_address' || $table=='fe_users')	{
						$tKey = substr($table, 0, 1);
					} elseif ($table=='PLAINLIST')	{
						$tKey='P';
					} else {
						$tKey='u';
					}

						// Send mails
					$sendIds = $this->dmailer_getSentMails($mid,$tKey);
					if ($table == 'PLAINLIST') {
						$sendIdsArr = explode(',', $sendIds);
						foreach ($listArr as $kval => $recipRow) {
							$kval++;
							if (!in_array($kval, $sendIdsArr)) {
								if ($c >= $this->sendPerCycle) {
									$returnVal = false;
									break;
								}
								$recipRow['uid'] = $kval;
								$this->shipOfMail($mid, $recipRow, $tKey);
								$ct++;
								$c++;
							}
						}
					} else {
						$idList = implode(',', $listArr);
						if ($idList)	{
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
								$table.'.*',
								$table,
								'uid IN (' . $idList . ')' .
									' AND uid NOT IN (' . ($sendIds ? $sendIds : 0) . ')' .
									($enableFields[$table] ? (' AND ' . $enableFields[$table]) : ''),
								'',
								'',
								$this->sendPerCycle+1
							);
							if ($GLOBALS['TYPO3_DB']->sql_error()) {
								die ($GLOBALS['TYPO3_DB']->sql_error());
							}
							while ($recipRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
								$recipRow['sys_dmail_categories_list'] = $this->getListOfRecipentCategories($table, $recipRow['uid']);
								if ($c >= $this->sendPerCycle) {
									$returnVal = false;
									break;
								}
									// We are NOT finished!
								$this->shipOfMail($mid, $recipRow, $tKey);
								$ct++;
								$c++;
							}
						}
					}
					if (TYPO3_DLOG) {
						t3lib_div::devLog($LANG->getLL('dmailer_sending').' '.$ct.' '.$LANG->getLL('dmailer_sending_to_table').' '.$table, 'direct_mail');
					}
					$this->logArray[] = $LANG->getLL('dmailer_sending').' '.$ct.' '.$LANG->getLL('dmailer_sending_to_table').' '.$table;
				}
			}
		}
		return $returnVal;
	}

	/**
	 * sending the email and write to log.
	 *
	 * @param	integer		$mid: newsletter ID. UID of the sys_dmail table
	 * @param	array		$recipRow: Recipient's data array
	 * @param $tableKey
	 * @internal param string $tKey : table of the recipient
	 * @return	void		...
	 */
	function shipOfMail($mid, $recipRow, $tableKey) {
		if (!$this->dmailer_isSend($mid, $recipRow['uid'], $tableKey)) {
			$pt = t3lib_div::milliseconds();
			$recipRow = self::convertFields($recipRow);

			// write to dmail_maillog table. if it can be written, continue with sending.
			// if not, stop the script and report error
			$rC = 0;
			$ok = $this->dmailer_addToMailLog($mid, $tableKey.'_' . $recipRow['uid'], strlen($this->message) ,t3lib_div::milliseconds() - $pt, $rC, $recipRow['email']);
			if ($ok) {
				$logUid = $GLOBALS['TYPO3_DB']->sql_insert_id();
				$rC     = $this->dmailer_sendAdvanced($recipRow, $tableKey);
				$parsetime = t3lib_div::milliseconds() - $pt;
				// Update the log with real values
				$updateFields = array(
					'tstamp'    => time(),
					'size'      => strlen($this->message),
					'parsetime' => $parsetime,
					'html_sent' => intval($rC)
				);
				$ok = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_dmail_maillog', 'uid=' . $logUid, $updateFields);
				if(!$ok) {
					if (TYPO3_DLOG) t3lib_div::devLog('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid='.$mid.')', 'direct_mail', 3);
					die('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid='.$mid.')');
				}
			} else {
				// stop the script if dummy log can't be made
				if (TYPO3_DLOG) t3lib_div::devLog('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid='.$mid.')', 'direct_mail', 3);
				die('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid='.$mid.')');
			}
		}
	}

	/**
	 * converting array key. fe_user and tt_address are using different fieldname for the same information
	 *
	 * @param	array		$recipRow: recipient's data array
	 * @return	array		fixed recipient's data array
	 */
	static function convertFields($recipRow) {

			// Compensation for the fact that fe_users has the field 'telephone' instead of 'phone'
		if ($recipRow['telephone'])	{
			$recipRow['phone'] = $recipRow['telephone'];
		}

			// Firstname must be more that 1 character
		$recipRow['firstname'] = trim(strtok(trim($recipRow['name']), ' '));
		if (strlen($recipRow['firstname']) < 2 || preg_match('|[^[:alnum:]]$|', $recipRow['firstname'])) {
			$recipRow['firstname'] = $recipRow['name'];
		}
		if (!trim($recipRow['firstname'])) {
			$recipRow['firstname'] = $recipRow['email'];
		}
		return $recipRow;
	}

	/**
	 * Set job begin and end time. And send this to admin
	 *
	 * @param	integer		$mid: sys_dmail UID
	 * @param	string		$key: begin or end
	 * @return	void		...
	 */
	function dmailer_setBeginEnd($mid,$key)	{
		$subject = '';
		$message = "";

		$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
			'sys_dmail',
			'uid='.intval($mid),
			array('scheduled_'.$key => time())
		);

		switch($key)	{
			case 'begin':
				$subject = $GLOBALS['LANG']->getLL('dmailer_mid').' '.$mid. ' ' . $GLOBALS['LANG']->getLL('dmailer_job_begin');
				$message = $GLOBALS['LANG']->getLL('dmailer_job_begin') . ': ' .date("d-m-y h:i:s");
			break;
			case 'end':
				$subject = $GLOBALS['LANG']->getLL('dmailer_mid').' '.$mid. ' ' . $GLOBALS['LANG']->getLL('dmailer_job_end');
				$message = $GLOBALS['LANG']->getLL('dmailer_job_end') . ': ' .date("d-m-y h:i:s");
			break;
		}
		if (TYPO3_DLOG) t3lib_div::devLog($subject . ': '.$message, 'direct_mail');
		$this->logArray[] = $subject . ': '.$message;

		$from_name = ($this->from_name) ? $GLOBALS['LANG']->csConvObj->conv($this->from_name, $this->charset, $GLOBALS['LANG']->charSet) : '';

		$headers[] = 'From: "'.$from_name.'" <'.$this->from_email.'>';
		if (!empty($this->replyto_email)) {
			$headers[] = 'Reply-To: '.$this->replyto_email;
		}

		$email = '"'.$from_name.'" <'.$this->from_email.'>';

		if ($this->notificationJob) {
			t3lib_div::plainMailEncoded($email,$subject,$message,implode(chr(10),$headers));
		}
	}

	/**
	 * count how many email have been sent
	 *
	 * @param	integer		$mid: newsletter ID. UID of the sys_dmail record
	 * @param	string		$rtbl: which recipient table
	 * @return	integer		number of sent emails
	 */
	function dmailer_howManySendMails($mid,$rtbl='')	{
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'count(*)',
			'sys_dmail_maillog',
			'mid='.intval($mid).
				' AND response_type=0'.
				($rtbl ? ' AND rtbl='.$GLOBALS['TYPO3_DB']->fullQuoteStr($rtbl, 'sys_dmail_maillog') : '')
		);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		return $row[0];
	}

	/**
	 * find out, if an email has been sent to a recipient
	 *
	 * @param	integer		$mid: newsletter ID. UID of the sys_dmail record
	 * @param	integer		$rid: recipient UID
	 * @param	string		$rtbl: recipient table
	 * @return	integer		number of found records
	 */
	function dmailer_isSend($mid, $rid, $rtbl) {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid',
			'sys_dmail_maillog',
			'rid='.intval($rid).
				' AND rtbl='.$GLOBALS['TYPO3_DB']->fullQuoteStr($rtbl, 'sys_dmail_maillog').
				' AND mid='.intval($mid).
				' AND response_type=0'
		);
		return $GLOBALS['TYPO3_DB']->sql_num_rows($res);
	}

	/**
	 * get IDs of recipient, which has been sent
	 *
	 * @param	integer		$mid: newsletter ID. UID of the sys_dmail record
	 * @param	string		$rtbl: recipient table
	 * @return	string		list of sent recipients
	 */
	function dmailer_getSentMails($mid, $rtbl) {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'rid',
			'sys_dmail_maillog',
			'mid='.intval($mid).
				' AND rtbl='.$GLOBALS['TYPO3_DB']->fullQuoteStr($rtbl,'sys_dmail_maillog').
				' AND response_type=0'
		);
		$list = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$list[] = $row['rid'];
		}
		return implode(',', $list);
	}

	/**
	 * add action to sys_dmail_maillog table
	 *
	 * @param	integer		$mid: newsletter ID
	 * @param	integer		$rid: recipient ID
	 * @param	integer		$size: size of the sent email
	 * @param	integer		$parsetime: parse time of the email
	 * @param	integer		$html: set if HTML email is sent
	 * @param	string		$email: recipient's email
	 * @return	boolean		True on success or False on error
	 */
	function dmailer_addToMailLog($mid, $rid, $size, $parsetime, $html, $email) {
		$temp_recip = explode('_', $rid);
		$insertFields = array(
			'mid'       => intval($mid),
			'rtbl'      => $temp_recip[0],
			'rid'       => intval($temp_recip[1]),
			'email'     => $email,
			'tstamp'    => time(),
			'url'       => '',
			'size'      => $size,
			'parsetime' => $parsetime,
			'html_sent' => intval($html)
		);
		return $GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_dmail_maillog', $insertFields);
	}

	/**
	 * called from the dmailerd script. Look if there is newsletter to be sent and do the sending process. Otherwise quit runtime
	 *
	 * @return	void		...
	 */
	function runcron() {
		$this->sendPerCycle = trim($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['sendPerCycle']) ? intval($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['sendPerCycle']) : 50;
		$this->notificationJob = intval($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['notificationJob']);

		if(!is_object($GLOBALS['LANG']) ) {
			require_once (PATH_typo3.'sysext/lang/lang.php');
			/** @var $LANG language */
			$GLOBALS['LANG']= t3lib_div::makeInstance('language');
			$L = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] : $this->user_dmailerLang;
			$GLOBALS['LANG']->init(trim($L));
		}

		// always include locallang file
		$GLOBALS['LANG']->includeLLFile('EXT:direct_mail/locallang/locallang_mod2-6.xml');

		$pt = t3lib_div::milliseconds();

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'sys_dmail',
			'scheduled!=0' .
			' AND scheduled < ' . time() .
			' AND scheduled_end = 0' .
			' AND type NOT IN (2,3)' .
			t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			'scheduled'
		);
		if (TYPO3_DLOG) {
			t3lib_div::devLog($GLOBALS['LANG']->getLL('dmailer_invoked_at'). ' ' . date('h:i:s d-m-Y'), 'direct_mail');
		}
		$this->logArray[] = $GLOBALS['LANG']->getLL('dmailer_invoked_at'). ' ' . date('h:i:s d-m-Y');

		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			if (TYPO3_DLOG) {
				t3lib_div::devLog($GLOBALS['LANG']->getLL('dmailer_sys_dmail_record') . ' ' . $row['uid']. ', \'' . $row['subject'] . '\'' . $GLOBALS['LANG']->getLL('dmailer_processed'), 'direct_mail');
			}
			$this->logArray[] = $GLOBALS['LANG']->getLL('dmailer_sys_dmail_record') . ' ' . $row['uid']. ', \'' . $row['subject'] . '\'' . $GLOBALS['LANG']->getLL('dmailer_processed');
			$this->dmailer_prepare($row);
			$query_info = unserialize($row['query_info']);

			if (!$row['scheduled_begin'])   {
				$this->dmailer_setBeginEnd($row['uid'],'begin');
			}

			$finished = $this->dmailer_masssend_list($query_info,$row['uid']);

			if ($finished)  {
				$this->dmailer_setBeginEnd($row['uid'],'end');
			}
		} else {
			if (TYPO3_DLOG) {
				t3lib_div::devLog($GLOBALS['LANG']->getLL('dmailer_nothing_to_do'), 'direct_mail');
			}
			$this->logArray[] = $GLOBALS['LANG']->getLL('dmailer_nothing_to_do');
		}

		//closing DB connection
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		$parsetime = t3lib_div::milliseconds()-$pt;
		if (TYPO3_DLOG) t3lib_div::devLog($GLOBALS['LANG']->getLL('dmailer_ending'). ' ' . $parsetime . ' ms', 'direct_mail');
		$this->logArray[] = $GLOBALS['LANG']->getLL('dmailer_ending'). ' ' . $parsetime . ' ms';
	}

	/**
	 * initializing the t3lib_htmlmail class and setting the first global variables. Write to log file if it's a cronjob
	 *
	 * @param	integer		$user_dmailer_sendPerCycle: total of recipient in a cycle
	 * @param	string		$user_dmailer_lang: language of the user
	 * @return	void		...
	 */
	function start($user_dmailer_sendPerCycle=50,$user_dmailer_lang='en') {

		// Sets the message id
		$host = t3lib_div::getHostname();
		if (!$host || $host == '127.0.0.1' || $host == 'localhost' || $host == 'localhost.localdomain') {
			$host = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : 'localhost') . '.TYPO3';
		}

		$idLeft = time() . '.' . uniqid();
		$idRight = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'swift.generated';
		$this->messageid = $idLeft . '@' . $idRight;

		// Default line break for Unix systems.
		$this->linebreak = LF;
		// Line break for Windows. This is needed because PHP on Windows systems
		// send mails via SMTP instead of using sendmail, and thus the linebreak needs to be \r\n.
		if (TYPO3_OS == 'WIN') {
			$this->linebreak = CRLF;
		}

			// Mailer engine parameters
		$this->sendPerCycle = $user_dmailer_sendPerCycle;
		$this->user_dmailerLang = $user_dmailer_lang;
		if (!$this->nonCron) {
			if (TYPO3_DLOG) t3lib_div::devLog('Starting directmail cronjob', 'direct_mail');
			//write this temp file for checking the engine in the status module
			$this->dmailer_log('w','starting directmail cronjob');
		}

		$this->dontEncodeHeader = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['encodeHeader'];
	}

	/**
	 *
	 * set the content from $this->theParts['html'] or $this->theParts['plain'] to the swiftmailer
	 * @var $mailer t3lib_mail_Message
	 */
	function setContent(&$mailer) {
		//todo: css??
		// iterate through the media array and embed them
		if ($this->includeMedia) {
			// extract all media path from the mail message
			$this->extractMediaLinks();
			foreach($this->theParts['html']['media'] as $media) {
				if (($media['tag'] == 'img' || $media['tag'] == 'table' || $media['tag'] == 'tr' || $media['tag'] == 'td') && !$media['use_jumpurl']) {
					// SwiftMailer depends on allow_url_fopen in PHP
					// To work around this, download the files using t3lib::getURL() to a temporary location.
					$fileContent = t3lib_div::getUrl($media['absRef']);
					$tempFile = PATH_site.'uploads/tx_directmail/'.basename($media['absRef']);
					t3lib_div::writeFile($tempFile,$fileContent);

					unset($fileContent);

					$cid = $mailer->embed(Swift_Image::fromPath($tempFile));
					$this->theParts['html']['content'] = str_replace($media['subst_str'], $cid, $this->theParts['html']['content']);

					// Temporary files will be removed again after the mail was sent!
					$this->tempFileList[] = $tempFile;
				}
			}
		}

		// TODO: multiple instance for each NL type? HTML+Plain or Plain only?
		// http://groups.google.com/group/swiftmailer/browse_thread/thread/98041a123223e63d
		//$mailer->attach($entity);

		// set the html content
		if ($this->theParts['html']) {
			$mailer->setBody($this->theParts['html']['content'], 'text/html');
			// set the plain content as alt part
			if ($this->theParts['plain']) {
				$mailer->addPart($this->theParts['plain']['content'], 'text/plain');
			}
		} elseif ($this->theParts['plain']) {
			$mailer->setBody($this->theParts['plain']['content'], 'text/plain');
		}

		// set the attachment from $this->dmailer['sys_dmail_rec']['attachment']
		// comma separated files
		if (!empty($this->dmailer['sys_dmail_rec']['attachment'])) {
			$files = explode(",", $this->dmailer['sys_dmail_rec']['attachment']);
			foreach ($files as $file) {
				$mailer->attach(Swift_Attachment::fromPath(PATH_site."uploads/tx_directmail/".$file));
			}
		}

	}

	/**
	 * Send of the email using php mail function.
	 *
	 * @var	array	$recipient: the recipient array. array($name => $mail)
	 * @return	boolean		true if there is recipient and content, otherwise false
	 */
	function sendTheMail($recipient) {
//		$conf = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail'];

		// init the swiftmailer object
		/** @var $mailer t3lib_mail_Message */
		$mailer = t3lib_div::makeInstance('t3lib_mail_Message');
		$mailer->setFrom(array($this->from_email => $this->from_name));
		$mailer->setSubject($this->subject);
		$mailer->setPriority($this->priority);

		if ($this->replyto_email) {
			$mailer->setReplyTo(array($this->replyto_email => $this->replyto_name));
		} else {
			$mailer->setReplyTo(array($this->from_email => $this->from_name));
		}

		//setting additional header
		// organization and TYPO3MID
		$header = $mailer->getHeaders();
		$header->addTextHeader('X-TYPO3MID', $this->TYPO3MID);

		if ($this->organisation) {
			$header->addTextHeader('Organization', $this->organisation);
		}

		if (t3lib_div::validEmail($this->dmailer['sys_dmail_rec']['return_path'])) {
			$mailer->setReturnPath($this->dmailer['sys_dmail_rec']['return_path']);
		}

		//set the recipient
		$mailer->setTo($recipient);

		// TODO: setContent should set the images (includeMedia) or add attachment
		$this->setContent($mailer);

		//TODO: do we really need the return value?
		$sent = $mailer->send();
		$failed = $mailer->getFailedRecipients();

		//unset the mailer object
		unset($mailer);

		// Delete temporary files
		foreach ($this->tempFileList as $tempFile) {
			unlink($tempFile);
		}
	}


	/**
	 * add HTML to an email
	 *
	 * @param	$file string location of the HTML
	 * @return	mixed		bool: HTML fetch status. string: if HTML is a frameset.
	 */
	function addHTML($file)	{
			// Adds HTML and media, encodes it from a URL or file
		$status = $this->fetchHTML($file);
		if (!$status) {
			return false;
		}
		if ($this->extractFramesInfo())	{
			return "Document was a frameset. Stopped";
		}
		$this->extractHyperLinks();
		$this->substHREFsInHTML();
		$this->setHTML($this->encodeMsg($this->theParts["html"]["content"]));
		return true;
	}

	/**
	* Fetches the HTML-content from either url og local serverfile
	*
	* @param	$url	string		url of the html to fetch
	* @return	boolean		whether the data was fetched or not
	*/
	public function fetchHTML($url) {
		// Fetches the content of the page
		$this->theParts['html']['content'] = t3lib_div::getURL($url);
		if ($this->theParts['html']['content']) {
			$this->theParts['html']['path'] = t3lib_div::getIndpEnv('TYPO3_SITE_URL');
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * if it's a HTML email, which MIME type?
	 *
	 * @return	string		MIME type of the email
	 */
	function getHTMLContentType() {
		if (t3lib_div::int_from_ver(TYPO3_version) < 4002002) {
			return (count($this->theParts['html']['media']) && $this->includeMedia) ? 'multipart/related;' : 'multipart/alternative;';
		} else {
			return (count($this->theParts['html']['media']) && $this->includeMedia) ? 'multipart/related' : 'multipart/alternative';
		}
	}

	/**
	 * This function returns the mime type of the file specified by the url
	 *
	 * @param	string		$url: the url
	 * @return	string		$mimeType: the mime type found in the header
	 */
	function getMimeType($url) {
		$mimeType = '';
		$headers = trim(t3lib_div::getURL($url, 2));
		if ($headers) {
			$matches = array();
			if (preg_match('/(Content-Type:[\s]*)([a-zA-Z_0-9\/\-\+\.]*)([\s]|$)/', $headers, $matches)) {
				$mimeType = trim($matches[2]);
			}
		}
		return $mimeType;
	}

	/**
	 * Rewrite core function, since it has bug. See Bug Tracker 8265. It will be removed if it's fixed.
	 * This function substitutes the hrefs in $this->theParts["html"]["content"]
	 *
	 * @return	void
	 */
	function substHREFsInHTML() {
		if (!is_array($this->theParts['html']['hrefs'])) return;
		foreach ($this->theParts['html']['hrefs'] as $urlId => $val) {
				// Form elements cannot use jumpurl!
			if ($this->jumperURL_prefix && ($val['tag'] != 'form') && ( !strstr( $val['ref'], 'mailto:' ))) {
				if ($this->jumperURL_useId) {
					$substVal = $this->jumperURL_prefix . $urlId;
				} else {
					$substVal = $this->jumperURL_prefix.t3lib_div::rawUrlEncodeFP($val['absRef']);
				}
			} elseif ( strstr( $val['ref'], 'mailto:' ) && $this->jumperURL_useMailto) {
				if ($this->jumperURL_useId) {
					$substVal = $this->jumperURL_prefix . $urlId;
				} else {
					$substVal = $this->jumperURL_prefix.t3lib_div::rawUrlEncodeFP($val['absRef']);
				}
			} else {
				$substVal = $val['absRef'];
			}
			$this->theParts['html']['content'] = str_replace(
				$val['subst_str'],
				$val['quotes'] . $substVal . $val['quotes'],
				$this->theParts['html']['content']);
		}
	}

	/**
	* write to log file and send a notification email to admin if no records in sys_dmail_maillog table can be made
	*
	* @param	string		$writeMode: mode to open a file
	* @param	string		$logMsg: log message
	* @return	void		...
	*/
	function dmailer_log($writeMode,$logMsg){
		global $TYPO3_CONF_VARS;

		$content = time().' => '.$logMsg.chr(10);
		$logfilePath = 'typo3temp/tx_directmail_dmailer_log.txt';

		$fp = fopen(PATH_site.$logfilePath,$writeMode);
		if ($fp) {
			fwrite($fp,$content);
			fclose($fp);
		}
	}

	/**
	* Adds plain-text, replaces the HTTP urls in the plain text and then encodes it
	*
	* @param	string		$content that will be added
	* @return	void
	*/
	public function addPlain($content) {
		$content = $this->substHTTPurlsInPlainText($content);
		$this->setPlain($this->encodeMsg($content));
	}

	/**
	*  This substitutes the http:// urls in plain text with links
	*
	* @param	string		$content: the content to use to substitute
	* @return	string		the changed content
	*/
	public function substHTTPurlsInPlainText($content) {
		if (!$this->jumperURL_prefix) {
			return $content;
		}

		$textpieces = explode("http://", $content);
		$pieces = count($textpieces);
		$textstr = $textpieces[0];
		for ($i = 1; $i < $pieces; $i++) {
			$len = strcspn($textpieces[$i], chr(32) . TAB . CRLF);
			if (trim(substr($textstr, -1)) == '' && $len) {
				$lastChar = substr($textpieces[$i], $len - 1, 1);
				if (!preg_match('/[A-Za-z0-9\/#]/', $lastChar)) {
					$len--;
				}

				$parts = array();
				$parts[0] = "http://" . substr($textpieces[$i], 0, $len);
				$parts[1] = substr($textpieces[$i], $len);

				if ($this->jumperURL_useId) {
					$this->theParts['plain']['link_ids'][$i] = $parts[0];
					$parts[0] = $this->jumperURL_prefix . '-' . $i;
				} else {
					$parts[0] = $this->jumperURL_prefix . t3lib_div::rawUrlEncodeFP($parts[0]);
				}
				$textstr .= $parts[0] . $parts[1];
			} else {
				$textstr .= 'http://' . $textpieces[$i];
			}
		}
		return $textstr;
	}

	/**
	* wrapper function. always quoted_printable
	*
	* @param	string		$content the content that will be encoded
	* @return	string		the encoded content
	*/
	public function encodeMsg($content) {
		return $content;
	}

	/**
	* Returns base64-encoded content, which is broken every 76 character
	*
	* @param	string		$inputstr: the string to encode
	* @return	string		the encoded string
	*/
	public function makeBase64($inputstr) {
		return chunk_split(base64_encode($inputstr));
	}

	/**
	* Sets the plain-text part. No processing done.
	*
	* @param	string		$content: the plain content
	* @return	void
	*/
	public function setPlain($content) {
		$this->theParts['plain']['content'] = $content;
	}

	/**
	* Sets the HTML-part. No processing done.
	*
	* @param	string		$content: the HTML content
	* @return	void
	*/
	public function setHtml($content) {
		$this->theParts['html']['content'] = $content;
	}

	/**
	* extracts all media-links from $this->theParts['html']['content']
	*
	* @return	void
	*/
	public function extractMediaLinks() {
		$this->theParts['html']['media'] = array();

		$html_code = $this->theParts['html']['content'];
		$attribRegex = $this->tag_regex(array('img', 'table', 'td', 'tr', 'body', 'iframe', 'script', 'input', 'embed'));
		$image_fullpath_list = '';

		// split the document by the beginning of the above tags
		$codepieces = preg_split($attribRegex, $html_code);
		$len = strlen($codepieces[0]);
		$pieces = count($codepieces);
		$reg = array();
		for ($i = 1; $i < $pieces; $i++) {
			$tag = strtolower(strtok(substr($html_code, $len + 1, 10), ' '));
			$len += strlen($tag) + strlen($codepieces[$i]) + 2;
			$dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
			$attributes = $this->get_tag_attributes($reg[0]); // Fetches the attributes for the tag
			$imageData = array();

			// Finds the src or background attribute
			$imageData['ref'] = ($attributes['src'] ? $attributes['src'] : $attributes['background']);
			if ($imageData['ref']) {
				// find out if the value had quotes around it
				$imageData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
				// subst_str is the string to look for, when substituting lateron
				$imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
				if ($imageData['ref'] && !strstr($image_fullpath_list, "|" . $imageData["subst_str"] . "|")) {
					$image_fullpath_list .= "|" . $imageData['subst_str'] . "|";
					$imageData['absRef'] = $this->absRef($imageData['ref']);
					$imageData['tag'] = $tag;
					$imageData['use_jumpurl'] = $attributes['dmailerping'] ? 1 : 0;
					$this->theParts['html']['media'][] = $imageData;
				}
			}
		}

		// Extracting stylesheets
		$attribRegex = $this->tag_regex(array('link'));
		// Split the document by the beginning of the above tags
		$codepieces = preg_split($attribRegex, $html_code);
		$pieces = count($codepieces);
		for ($i = 1; $i < $pieces; $i++) {
			$dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
			// fetches the attributes for the tag
			$attributes = $this->get_tag_attributes($reg[0]);
			$imageData = array();
			if (strtolower($attributes['rel']) == 'stylesheet' && $attributes['href']) {
				// Finds the src or background attribute
				$imageData['ref'] = $attributes['href'];
				// Finds out if the value had quotes around it
				$imageData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
				// subst_str is the string to look for, when substituting lateron
				$imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
				if ($imageData['ref'] && !strstr($image_fullpath_list, "|" . $imageData["subst_str"] . "|")) {
					$image_fullpath_list .= "|" . $imageData["subst_str"] . "|";
					$imageData['absRef'] = $this->absRef($imageData["ref"]);
					$this->theParts['html']['media'][] = $imageData;
				}
			}
		}

		// fixes javascript rollovers
		$codepieces = explode('.src', $html_code);
		$pieces = count($codepieces);
		$expr = '/^[^' . quotemeta('"') . quotemeta("'") . ']*/';
		for ($i = 1; $i < $pieces; $i++) {
			$temp = $codepieces[$i];
			$temp = trim(str_replace('=', '', trim($temp)));
			preg_match($expr, substr($temp, 1, strlen($temp)), $reg);
			$imageData['ref'] = $reg[0];
			$imageData['quotes'] = substr($temp, 0, 1);
			// subst_str is the string to look for, when substituting lateron
			$imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
			$theInfo = t3lib_div::split_fileref($imageData['ref']);

			switch ($theInfo['fileext']) {
				case 'gif':
				case 'jpeg':
				case 'jpg':
					if ($imageData['ref'] && !strstr($image_fullpath_list, "|" . $imageData["subst_str"] . "|")) {
						$image_fullpath_list .= "|" . $imageData['subst_str'] . "|";
						$imageData['absRef'] = $this->absRef($imageData['ref']);
						$this->theParts['html']['media'][] = $imageData;
					}
					break;
			}
		}
	}

	/**
	 * extracts all hyper-links from $this->theParts["html"]["content"]
	 *
	 * @return	void
	 */
	public function extractHyperLinks() {
		$href_fullpath_list = '';

		$html_code = $this->theParts['html']['content'];
		$attribRegex = $this->tag_regex(array('a', 'form', 'area'));
		$codepieces = preg_split($attribRegex, $html_code); // Splits the document by the beginning of the above tags
		$len = strlen($codepieces[0]);
		$pieces = count($codepieces);
		for ($i = 1; $i < $pieces; $i++) {
			$tag = strtolower(strtok(substr($html_code, $len + 1, 10), " "));
			$len += strlen($tag) + strlen($codepieces[$i]) + 2;

			$dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
			// Fetches the attributes for the tag
			$attributes = $this->get_tag_attributes($reg[0]);
			$hrefData = array();
			$hrefData['ref'] = ($attributes['href'] ? $attributes['href'] : $hrefData['ref'] = $attributes['action']);
			if ($hrefData['ref']) {
				// Finds out if the value had quotes around it
				$hrefData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $hrefData["ref"]) - 1, 1) == '"') ? '"' : '';
				// subst_str is the string to look for, when substituting lateron
				$hrefData['subst_str'] = $hrefData['quotes'] . $hrefData['ref'] . $hrefData['quotes'];
				if ($hrefData['ref'] && substr(trim($hrefData['ref']), 0, 1) != "#" && !strstr($href_fullpath_list, "|" . $hrefData['subst_str'] . "|")) {
					$href_fullpath_list .= "|" . $hrefData['subst_str'] . "|";
					$hrefData['absRef'] = $this->absRef($hrefData['ref']);
					$hrefData['tag'] = $tag;
					$this->theParts['html']['hrefs'][] = $hrefData;
				}
			}
		}
		// Extracts TYPO3 specific links made by the openPic() JS function
		$codepieces = explode("onClick=\"openPic('", $html_code);
		$pieces = count($codepieces);
		for ($i = 1; $i < $pieces; $i++) {
			$showpic_linkArr = explode("'", $codepieces[$i]);
			$hrefData['ref'] = $showpic_linkArr[0];
			if ($hrefData['ref']) {
				$hrefData['quotes'] = "'";
				// subst_str is the string to look for, when substituting lateron
				$hrefData['subst_str'] = $hrefData['quotes'] . $hrefData['ref'] . $hrefData['quotes'];
				if ($hrefData['ref'] && !strstr($href_fullpath_list, "|" . $hrefData['subst_str'] . "|")) {
					$href_fullpath_list .= "|" . $hrefData['subst_str'] . "|";
					$hrefData['absRef'] = $this->absRef($hrefData['ref']);
					$this->theParts['html']['hrefs'][] = $hrefData;
				}
			}
		}

		// substitute dmailerping URL
		// get all media and search for use_jumpurl then add it to the hrefs array
		$this->extractMediaLinks();
		foreach ($this->theParts['html']['media'] as $k => $mediaData) {
			if ($mediaData['use_jumpurl'] === 1) {
				$this->theParts['html']['hrefs'][$mediaData['ref']] = $mediaData;
			}
		}
	}


	/**
	 * extracts all media-links from $this->theParts["html"]["content"]
	 *
	 * @return	array	two-dimensional array with information about each frame
	 */
	public function extractFramesInfo() {
		$htmlCode = $this->theParts['html']['content'];
		$info = array();
		if (strpos(' ' . $htmlCode, '<frame ')) {
			$attribRegex = $this->tag_regex('frame');
			// Splits the document by the beginning of the above tags
			$codepieces = preg_split($attribRegex, $htmlCode, 1000000);
			$pieces = count($codepieces);
			for ($i = 1; $i < $pieces; $i++) {
				$dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
				// Fetches the attributes for the tag
				$attributes = $this->get_tag_attributes($reg[0]);
				$frame = array();
				$frame['src'] = $attributes['src'];
				$frame['name'] = $attributes['name'];
				$frame['absRef'] = $this->absRef($frame['src']);
				$info[] = $frame;
			}
			return $info;
		}
	}

	/**
	* Creates a regular expression out of a list of tags
	*
	* @param	mixed		$tagArray: the list of tags (either as array or string if it is one tag)
	* @return	string		the regular expression
	*/
	public function tag_regex($tags) {
		$tags = (!is_array($tags) ? array($tags) : $tags);
		$regexp = '/';
		$c = count($tags);
		foreach ($tags as $tag) {
			$c--;
			$regexp .= '<' . $tag . '[[:space:]]' . (($c) ? '|' : '');
		}
		return $regexp . '/i';
	}

	/**
	* This function analyzes a HTML tag
	* If an attribute is empty (like OPTION) the value of that key is just empty. Check it with is_set();
	*
	* @param	string		$tag: is either like this "<TAG OPTION ATTRIB=VALUE>" or
	*				 this " OPTION ATTRIB=VALUE>" which means you can omit the tag-name
	* @return	array		array with attributes as keys in lower-case
	*/
	public function get_tag_attributes($tag) {
		$attributes = array();
		$tag = ltrim(preg_replace('/^<[^ ]*/', '', trim($tag)));
		$tagLen = strlen($tag);
		$safetyCounter = 100;
		// Find attribute
		while ($tag) {
			$value = '';
			$reg = preg_split('/[[:space:]=>]/', $tag, 2);
			$attrib = $reg[0];

			$tag = ltrim(substr($tag, strlen($attrib), $tagLen));
			if (substr($tag, 0, 1) == '=') {
				$tag = ltrim(substr($tag, 1, $tagLen));
				if (substr($tag, 0, 1) == '"') {
					// Quotes around the value
					$reg = explode('"', substr($tag, 1, $tagLen), 2);
					$tag = ltrim($reg[1]);
					$value = $reg[0];
				} else {
					// No quotes around value
					preg_match('/^([^[:space:]>]*)(.*)/', $tag, $reg);
					$value = trim($reg[1]);
					$tag = ltrim($reg[2]);
					if (substr($tag, 0, 1) == '>') {
						$tag = '';
					}
				}
			}
			$attributes[strtolower($attrib)] = $value;
			$safetyCounter--;
			if ($safetyCounter < 0) {
				break;
			}
		}
		return $attributes;
	}

	/**
	* Returns the absolute address of a link. This is based on
	* $this->theParts["html"]["path"] being the root-address
	*
	* @param	string		$ref: address to use
	* @return	string		the absolute address
	*/
	public function absRef($ref) {
		$ref = trim($ref);
		$info = parse_url($ref);
		if ($info['scheme']) {
			// if ref is an url
			return $ref;
		} elseif (preg_match('/^\//', $ref)) {
			// if ref is an absolute link
			$addr = parse_url($this->theParts['html']['path']);
			return $addr['scheme'] . '://' . $addr['host'] . ($addr['port'] ? ':' . $addr['port'] : '') . $ref;
		} else {
			// If the reference is relative, the path is added, in order for us to fetch the content
			if (substr($this->theParts['html']['path'], -1) == "/") {
				// if the last char is a /, then prepend the ref
				return $this->theParts['html']['path'] . $ref;
			} else {
				// if the last char not a /, then assume it's an absolute
				$addr = parse_url($this->theParts['html']['path']);
				return $addr['scheme'] . '://' . $addr['host'] . ($addr['port'] ? ':' . $addr['port'] : '') . '/' . $ref;
			}
		}
	}

	/**
	* reads a url or file
	*
	* @param	string		$url: the URL to fetch
	* @return	string		the content of the URL
	*/
	public function getURL($url) {
		$url = $this->addUserPass($url);
		return t3lib_div::getURL($url);
	}

	/**
	* Adds HTTP user and password (from $this->http_username) to a URL
	*
	* @param	string		$url: the URL
	* @return	string		the URL with the added values
	*/
	public function addUserPass($url) {
		$user = $this->http_username;
		$pass = $this->http_password;
		$matches = array();
		if ($user && $pass && preg_match('/^(https?:\/\/)/', $url, $matches)) {
			return $matches[1] . $user . ':' . $pass . '@' . substr($url, strlen($matches[1]));
		}
		return $url;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.dmailer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.dmailer.php']);
}
?>
