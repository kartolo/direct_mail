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
 * @version		$Id$
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
 *  398:     function dmailer_masssend($query_info,$table,$mid)
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

require_once(PATH_t3lib.'class.t3lib_htmlmail.php');
require_once(PATH_t3lib.'class.t3lib_befunc.php');

// include Pear::Mail if exists
@include_once('Mail.php');

/**
 * Class, doing the sending of Direct-mails, eg. through a cron-job
 *
 */
class dmailer extends t3lib_htmlmail {
	var $sendPerCycle =50;
	var $logArray =array();
	var $massend_id_lists = array();
	var $mailHasContent;
	var $flag_html = 0;
	var $flag_plain = 0;
	var $includeMedia = 0;
	var $flowedFormat = 0;
	var $user_dmailerLang = 'en';
	var $mailObject = NULL;

	/**
	 * Preparing the Email. Headers are set in global variables
	 *
	 * @param	array		$row: Record from the sys_dmail table
	 * @return	void
	 */
	function dmailer_prepare($row)	{
		global $LANG;

			// We need to take care of header encoding ourselves, otherwise the subject may get encoded multiple times.
		$this->dontEncodeHeader = 1;

		$sys_dmail_uid = $row['uid'];
		if ($row['flowedFormat']) {
			$this->flowedFormat = 1;
		}
		if ($row['charset']) {
			$this->charset = $row['charset'];
		}
		switch ($row['encoding']) {
			case 'base64':
				$this->useBase64();
				break;
			case '8bit':
				$this->use8Bit();
				break;
			case 'printed-quotable':
			default:
				$this->useQuotedPrintable();
		}
		$this->theParts = unserialize($row['mailContent']);

		$this->messageid = $this->theParts['messageid'];

		$this->subject = $row['subject'];
		$this->subject = $LANG->csConvObj->conv($this->subject, $LANG->charSet, $this->charset);
		$this->subject = t3lib_div::encodeHeader($this->subject, ($this->alt_base64 ? 'base64' : 'quoted_printable'), $this->charset);

		$this->from_email = $row['from_email'];
		$this->from_name = ($row['from_name']) ? $LANG->csConvObj->conv($row['from_name'], $LANG->charSet, $this->charset) : '';
		$this->replyto_email = ($row['replyto_email']) ? $row['replyto_email'] : '';
		$this->replyto_name = ($row['replyto_name']) ? $LANG->csConvObj->conv($row['replyto_name'], $LANG->charSet, $this->charset) : '';
		$this->organisation = ($row['organisation']) ? $LANG->csConvObj->conv($row['organisation'], $LANG->charSet, $this->charset) : '';
		$this->priority = t3lib_div::intInRange($row['priority'],1,5);
		$this->authCode_fieldList = ($row['authcode_fieldList']) ? $row['authcode_fieldList'] : 'uid';
		$this->mailer = 'TYPO3 Direct Mail module';

		$this->dmailer['sectionBoundary'] = '<!--DMAILER_SECTION_BOUNDARY';
		$this->dmailer['html_content'] = base64_decode($this->theParts['html']['content']);
		$this->dmailer['plain_content'] = base64_decode($this->theParts['plain']['content']);
		$this->dmailer['messageID'] = $this->messageid;
		$this->dmailer['sys_dmail_uid'] = $sys_dmail_uid;
		$this->dmailer['sys_dmail_rec'] = $row;

		$this->dmailer['boundaryParts_html'] = explode($this->dmailer['sectionBoundary'], '_END-->'.$this->dmailer['html_content']);
		reset($this->dmailer['boundaryParts_html']);
		while(list($bKey,$bContent)=each($this->dmailer['boundaryParts_html']))	{
			$this->dmailer['boundaryParts_html'][$bKey] = explode('-->',$bContent,2);
				// Remove useless HTML comments
			if (substr($this->dmailer['boundaryParts_html'][$bKey][0],1) == 'END') {
				$this->dmailer['boundaryParts_html'][$bKey][1] = $this->removeHTMLComments($this->dmailer['boundaryParts_html'][$bKey][1]);
			}
				// Now, analyzing which media files are used in this part of the mail:
			$mediaParts = explode('cid:part',$this->dmailer['boundaryParts_html'][$bKey][1]);
			reset($mediaParts);
			next($mediaParts);
			while(list(,$part)=each($mediaParts))	{
				$this->dmailer['boundaryParts_html'][$bKey]['mediaList'].=','.strtok($part,'.');
			}
		}
		$this->dmailer['boundaryParts_plain'] = explode($this->dmailer['sectionBoundary'], '_END-->'.$this->dmailer['plain_content']);
		reset($this->dmailer['boundaryParts_plain']);
		while(list($bKey,$bContent)=each($this->dmailer['boundaryParts_plain']))	{
			$this->dmailer['boundaryParts_plain'][$bKey] = explode('-->',$bContent,2);
		}

		$this->flag_html = $this->theParts['html']['content'] ? 1 : 0;
		$this->flag_plain = $this->theParts['plain']['content'] ? 1 : 0;
		$this->includeMedia = $this->flag_html && $row['includeMedia'];
	}

	/**
	 * Removes html comments when outside script and style pairs
	 *
	 * @param	string		$content: The email content
	 * @return	string		HTML content without comments
	 */
	function removeHTMLComments($content) {
		$content = preg_replace('/\/\*<!\[CDATA\[\*\/[\t\v\n\r\f]*<!--/','/*<![CDATA[*/',$content);
		$content = preg_replace('/[\t\v\n\r\f]*<!(?:--[\s\S]*?--\s*)?>[\t\v\n\r\f]*/','',$content);
		return preg_replace('/\/\*<!\[CDATA\[\*\//','/*<![CDATA[*/<!--',$content);
	}

	/**
	 * Replace the marker with recipient data and then send it
	 *
	 * @param	array		$recipRow: Recipient's data array
	 * @param	string		$tableNameChar: Tablename, from which the recipient come from
	 * @return	integer		which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
	 */
	function dmailer_sendAdvanced($recipRow,$tableNameChar)	{
		global $TYPO3_CONF_VARS, $LANG;

		$returnCode=0;
		if ($recipRow['email'])	{
			$midRidId = 'MID'.$this->dmailer['sys_dmail_uid'].'_'.$tableNameChar.$recipRow['uid'];
			$uniqMsgId = md5(microtime()).'_'.$midRidId;
			$rowFieldsArray = explode(',', $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['defaultRecipFields']);
			if ($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields']) {
				$rowFieldsArray = array_merge($rowFieldsArray, explode(',',$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields']));
			}
			$uppercaseFieldsArray = explode(',', 'name,firstname');
			$authCode = t3lib_div::stdAuthCode($recipRow, $this->authCode_fieldList);
			$this->mediaList='';
			$this->theParts['html']['content'] = '';
			if ($this->flag_html && $recipRow['module_sys_dmail_html']) {
				$tempContent_HTML = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'],$recipRow['sys_dmail_categories_list']);
				if ($this->mailHasContent) {
					reset($rowFieldsArray);
					while(list(,$substField)=each($rowFieldsArray))	{
						$subst = $LANG->csConvObj->conv($recipRow[$substField], $LANG->charSet, $this->charset);
						$tempContent_HTML = str_replace('###USER_'.$substField.'###', $subst, $tempContent_HTML);
					}
					reset($uppercaseFieldsArray);
					while(list(,$substField)=each($uppercaseFieldsArray))	{
						$subst = $LANG->csConvObj->conv($recipRow[$substField], $LANG->charSet, $this->charset);
						$tempContent_HTML = str_replace('###USER_'.strtoupper($substField).'###', strtoupper($subst), $tempContent_HTML);
					}
					$tempContent_HTML = str_replace('###SYS_TABLE_NAME###', $tableNameChar, $tempContent_HTML);	// Put in the tablename of the userinformation
					$tempContent_HTML = str_replace('###SYS_MAIL_ID###', $this->dmailer['sys_dmail_uid'], $tempContent_HTML);	// Put in the uid of the mail-record
					$tempContent_HTML = str_replace('###SYS_AUTHCODE###', $authCode, $tempContent_HTML);
					$tempContent_HTML = str_replace($this->dmailer['messageID'], $uniqMsgId, $tempContent_HTML);	// Put in the unique message id in HTML-code
					$this->theParts['html']['content'] = $this->encodeMsg($tempContent_HTML);
					$returnCode|=1;
				}
			}

				// Plain
			$this->theParts['plain']['content'] = '';
			if ($this->flag_plain) {
				$tempContent_Plain = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'],$recipRow['sys_dmail_categories_list']);
				if ($this->mailHasContent) {
					reset($rowFieldsArray);
					while(list(,$substField)=each($rowFieldsArray))	{
						$subst = $LANG->csConvObj->conv($recipRow[$substField], $LANG->charSet, $this->charset);
						$tempContent_Plain = str_replace('###USER_'.$substField.'###', $subst, $tempContent_Plain);
					}
					reset($uppercaseFieldsArray);
					while(list(,$substField)=each($uppercaseFieldsArray))	{
						$subst = $LANG->csConvObj->conv($recipRow[$substField], $LANG->charSet, $this->charset);
						$tempContent_Plain = str_replace('###USER_'.strtoupper($substField).'###', strtoupper($subst), $tempContent_Plain);
					}
					$tempContent_Plain = str_replace('###SYS_TABLE_NAME###', $tableNameChar, $tempContent_Plain);	// Put in the tablename of the userinformation
					$tempContent_Plain = str_replace('###SYS_MAIL_ID###', $this->dmailer['sys_dmail_uid'], $tempContent_Plain);	// Put in the uid of the mail-record
					$tempContent_Plain = str_replace('###SYS_AUTHCODE###', $authCode, $tempContent_Plain);

					if (trim($this->dmailer['sys_dmail_rec']['use_rdct']))        {
						$tempContent_Plain = t3lib_div::substUrlsInPlainText($tempContent_Plain, $this->dmailer['sys_dmail_rec']['long_link_mode']?'all':'76', $this->dmailer['sys_dmail_rec']['long_link_rdct_url']);
					}

					$this->theParts['plain']['content'] = $this->encodeMsg($tempContent_Plain);
					$returnCode|=2;
				}
			}

				// Set content
			$this->Xid = $midRidId.'-'.md5($midRidId);
			$this->returnPath = str_replace('###XID###',$midRidId,$this->dmailer['sys_dmail_rec']['return_path']);

			$this->part=0;

			if (strlen(trim($recipRow['name']))) {
				$this->setRecipient('"' . $LANG->csConvObj->conv($recipRow['name'], $LANG->charSet, $this->charset) . '" <' . $recipRow['email'] . '>');	
			} else {
				$this->setRecipient($recipRow['email']);	
			}
			
			if(!$this->dontEncodeHeader){
				$this->recipient = t3lib_div::encodeHeader($this->recipient, ($this->alt_base64 ? 'base64' : 'quoted_printable'), $this->charset);	
			}
			$this->setHeaders();
			$this->setContent();

			$this->message = str_replace($this->dmailer['messageID'], $uniqMsgId, $this->message);	// Put in the unique message id in whole message body
			if ($returnCode) {
				$this->sendtheMail();
			}
		}
		return $returnCode;
	}

	/**
	 * Send a simple email (without personalizing)
	 *
	 * @param	string		$addressList: list of recipient address
	 * @return	boolean		...
	 */
	function dmailer_sendSimple($addressList) {
		global $TYPO3_CONF_VARS;

		if ($this->theParts['html']['content'])		{
			$this->theParts['html']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'],-1));
		} else $this->theParts['html']['content'] = '';
		if ($this->theParts['plain']['content'])		{
			$this->theParts['plain']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'],-1));
		} else $this->theParts['plain']['content'] = '';

		$this->useDeferMode = trim($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['useDeferMode']) ? intval($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['useDeferMode']) : 0;
		$this->returnPath = $this->dmailer['sys_dmail_rec']['return_path'];

		$this->setRecipient($addressList);
		if(!$this->dontEncodeHeader){
			$this->recipient = t3lib_div::encodeHeader($this->recipient, ($this->alt_base64 ? 'base64' : 'quoted_printable'), $this->charset);	
		}

		$this->setHeaders();
		$this->setContent();

		$this->sendtheMail();
		return true;
	}

	/**
	 * This function checks which content elements are suppsed to be sent to the recipient. tslib_content inserts dmail boudary markers in the content specifying which elements are intended for which categories, this functions check if the recipeient is subscribing to any of these categories and filters out the elements that are inteded for categories not subscribed to.
	 *
	 * @param	array		$cArray: array of content split by dmail boundary
	 * @param	string		$userCategories: The list of categories the user is subscribing to.
	 * @return	string		Content of the email, which the recipient subscribed
	 */
	function dmailer_getBoundaryParts($cArray,$userCategories)	{
		$returnVal='';
		$this->mailHasContent = FALSE;
		$boundaryMax = count($cArray)-1;
		reset($cArray);
		while(list($bKey,$cP)=each($cArray))	{
			$key=substr($cP[0],1);
			$isSubscribed = FALSE;
			if (!$key || intval($userCategories)==-1) {
				$returnVal.=$cP[1];
				$this->mediaList.=$cP['mediaList'];
				if ($cP[1]) {
					$this->mailHasContent = TRUE;
				}
			} elseif ($key == 'END') {
				$returnVal.=$cP[1];
				$this->mediaList.=$cP['mediaList'];
					// There is content and it is not just the header and footer content, or it is the only content because we have no direct mail boundaries.
				if (($cP[1] && !($bKey == 0 || $bKey == $boundaryMax)) || count($cArray) == 1) {
					$this->mailHasContent = TRUE;
				}
			} else {
				foreach(explode(',',$key) as $group) {
					if(t3lib_div::inList($userCategories,$group)) {
						$isSubscribed= TRUE;
					}
				}
				if ($isSubscribed) {
					$returnVal.=$cP[1];
					$this->mediaList.=$cP['mediaList'];
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
	function getListOfRecipentCategories($table,$uid) {
		global $TCA, $TYPO3_DB;

		if ($table == 'PLAINLIST') return '';

		t3lib_div::loadTCA($table);
		$mm_table = $TCA[$table]['columns']['module_sys_dmail_category']['config']['MM'];
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid_foreign',
			$mm_table.' LEFT JOIN '.$table.' ON '.$mm_table.'.uid_local='.$table.'.uid',
			$mm_table.'.uid_local='.intval($uid).
				t3lib_BEfunc::deleteClause($table)
			);
		$list = array();
		while($row = $TYPO3_DB->sql_fetch_assoc($res)) {
			$list[] = $row['uid_foreign'];
		}
		return implode(',', $list);
	}

	/**
	 * Mass sending email
	 *
	 * @param	array		$query_info: List of recipient from the sys_dmail record
	 * @param	string		$table: which table of the recipient
	 * @param	integer		$mid: Email ID from sys_dmail table
	 * @return	boolean		...
	 */
	function dmailer_masssend($query_info,$table,$mid)	{
		global $TYPO3_DB;

		$enableFields['tt_address']='tt_address.deleted=0 AND tt_address.hidden=0';
		$enableFields['fe_users']='fe_users.deleted=0 AND fe_users.disable=0';
		$tKey = substr($table,0,1);
		$begin=intval($this->dmailer_howManySendMails($mid,$tKey));
		if ($query_info[$table])	{
			$res = $TYPO3_DB->exec_SELECTquery(
				$table.'.*',
				$table,
				$enableFields[$table].
					' AND ('.$query_info[$table].')',
				'',
				'tstamp DESC',
				intval($begin).','.$this->sendPerCycle
				); // This way, we select newest edited records first. So if any record is added or changed in between, it'll end on top and do no harm
			$numRows = $TYPO3_DB->sql_num_rows($res);
			$cc=0;
			while($recipRow = $TYPO3_DB->sql_fetch_assoc($res))	{
				if (!$this->dmailer_isSend($mid,$recipRow['uid'],$tKey))	{
					$pt = t3lib_div::milliseconds();
					if ($recipRow['telephone'])	$recipRow['phone'] = $recipRow['telephone'];	// Compensation for the fact that fe_users has the field, 'telephone' instead of 'phone'
					$recipRow['firstname']=strtok(trim($recipRow['name']),' ');
					$recipRow['sys_dmail_categories_list'] = $this->getListOfRecipentCategories($table,$recipRow['uid']);
					$rC = $this->dmailer_sendAdvanced($recipRow,$tKey);
					if ($rC) {
						$this->dmailer_addToMailLog($mid,$tKey.'_'.$recipRow['uid'],strlen($this->message),t3lib_div::milliseconds()-$pt,$rC,$recipRow['email']);
						$cc++;
					}
				}
			}
			$this->logArray[]='Sending '.$cc.' mails to table '.$table;
			if ($numRows < $this->sendPerCycle)	return true;
		}
		return false;
	}

	/**
	 * Mass send to recipient in the list
	 *
	 * @param	array		$query_info: List of recipients' ID in the sys_dmail table
	 * @param	integer		$mid: directmail ID. UID of the sys_dmail table
	 * @return	boolean		...
	 */
	function dmailer_masssend_list($query_info,$mid) {
		global $TYPO3_DB, $LANG;

		$enableFields['tt_address']='tt_address.deleted=0 AND tt_address.hidden=0';
		$enableFields['fe_users']='fe_users.deleted=0 AND fe_users.disable=0';

		$c=0;
		$returnVal=true;
		if (is_array($query_info['id_lists']))	{
			reset($query_info['id_lists']);
			while(list($table,$listArr)=each($query_info['id_lists']))	{
				if (is_array($listArr))	{
					$ct=0;
						// FInd tKey
					if ($table=='tt_address' || $table=='fe_users')	{
						$tKey = substr($table,0,1);
					} elseif ($table=='PLAINLIST')	{
						$tKey='P';
					} else {
						$tKey='u';
					}

						// Send mails
					$sendIds = $this->dmailer_getSentMails($mid,$tKey);
					if ($table=='PLAINLIST')	{
						$sendIdsArr = explode(',',$sendIds);
						reset($listArr);
						while(list($kval,$recipRow)=each($listArr))	{
							$kval++;
							if (!in_array($kval,$sendIdsArr))	{
								if ($c >= $this->sendPerCycle)	{
									$returnVal = false;
									break;
								}
								$recipRow['uid']=$kval;
								$this->shipOfMail($mid,$recipRow,$tKey);
								$ct++;
								$c++;
							}
						}
					} else {
						$idList = implode(',',$listArr);
						if ($idList)	{
							$res = $TYPO3_DB->exec_SELECTquery(
								$table.'.*',
								$table,
								'uid IN ('.$idList.')'.
									' AND uid NOT IN ('.($sendIds?$sendIds:0).')'.
									($enableFields[$table]?(' AND '.$enableFields[$table]):''),
								'',
								'',
								$this->sendPerCycle+1
								);
							if ($TYPO3_DB->sql_error())	{
								die ($TYPO3_DB->sql_error());
							}
							while($recipRow = $TYPO3_DB->sql_fetch_assoc($res))	{
								$recipRow['sys_dmail_categories_list'] = $this->getListOfRecipentCategories($table,$recipRow['uid']);
								if ($c>=$this->sendPerCycle)	{$returnVal = false; break;}		// We are NOT finished!
								$this->shipOfMail($mid,$recipRow,$tKey);
								$ct++;
								$c++;
							}
						}
					}
					$this->logArray[] = $LANG->getLL('dmailer_sending').' '.$ct.' '.$LANG->getLL('dmailer_sending_to_table').' '.$table.'.';
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
	 * @param	string		$tKey: table of the recipient
	 * @return	void		...
	 */
	function shipOfMail($mid,$recipRow,$tKey) {
        if (!$this->dmailer_isSend($mid,$recipRow['uid'],$tKey)) {
            $pt = t3lib_div::milliseconds();
            $recipRow=$this->convertFields($recipRow);

			// write to dmail_maillog table. if it can be written, continue with sending.
			// if not, stop the script and report error
            $rC = 0;
            $ok = $this->dmailer_addToMailLog($mid,$tKey.'_'.$recipRow['uid'],strlen($this->message),t3lib_div::milliseconds()-$pt,$rC,$recipRow['email']);
            if ($ok) {
                $logUid = $GLOBALS['TYPO3_DB']->sql_insert_id();
                $rC=$this->dmailer_sendAdvanced($recipRow,$tKey);
                $parsetime = t3lib_div::milliseconds()-$pt;
					// Update the log with real values
                $updateFields = array(
                    'tstamp' => time(),
                    'size' => strlen($this->message),
                    'parsetime' => $parsetime,
                    'html_sent' => intval($rC));
                $ok = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_dmail_maillog','uid=' . $logUid,$updateFields);
                if(!$ok){
					$this->dmailer_log('a','error: cannot write to DB');
					die("Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=".$mid.").");
                }
            }
            else {
                // stop the script if dummy log can't be made
                $this->dmailer_log('a','error: cannot write to DB');
                die("Unable to add Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=".$mid.").");
            }
        }
    }

	/**
	 * converting array key. fe_user and tt_address are using different fieldname for the same information
	 *
	 * @param	array		$recipRow: recipient's data array
	 * @return	array		fixed recipient's data array
	 */
	function convertFields($recipRow)	{
		if ($recipRow['telephone'])	$recipRow['phone'] = $recipRow['telephone'];	// Compensation for the fact that fe_users has the field, 'telephone' instead of 'phone'
		$recipRow['firstname']=trim(strtok(trim($recipRow['name']),' '));
		if (strlen($recipRow['firstname'])<2 || ereg('[^[:alnum:]]$',$recipRow['firstname']))		$recipRow['firstname']=$recipRow['name'];		// Firstname must be more that 1 character
		if (!trim($recipRow['firstname']))	$recipRow['firstname']=$recipRow['email'];
		return 	$recipRow;
	}

	/**
	 * Set job begin and end time. And send this to admin
	 *
	 * @param	integer		$mid: sys_dmail UID
	 * @param	string		$key: begin or end
	 * @return	void		...
	 */
	function dmailer_setBeginEnd($mid,$key)	{
		global $LANG, $TYPO3_CONF_VARS, $TYPO3_DB;

		$res = $TYPO3_DB->exec_UPDATEquery(
			'sys_dmail',
			'uid='.intval($mid),
			array('scheduled_'.$key => time())
			);
		switch($key)	{
			case 'begin':
				$subject=$LANG->getLL('dmailer_mid').' '.$mid. ' ' . $LANG->getLL('dmailer_job_begin');
				$message=$LANG->getLL('dmailer_job_begin') . ': ' .date("d-m-y h:i:s");
			break;
			case 'end':
				$subject=$LANG->getLL('dmailer_mid').' '.$mid. ' ' . $LANG->getLL('dmailer_job_end');
				$message=$LANG->getLL('dmailer_job_end') . ': ' .date("d-m-y h:i:s");
			break;
		}
		$this->logArray[]=$subject.": ".$message;

		$from_name = ($this->from_name) ? $LANG->csConvObj->conv($this->from_name, $this->charset, $LANG->charSet) : '';

		$headers[]='From: '.$from_name.' <'.$this->from_email.'>';
		$headers[]='Reply-To: '.$this->replyto_email;

		$email = $from_name.' <'.$this->from_email.'>';

		if($this->notificationJob){
				// format headers for SMTP use
			if ($this->useSmtp) {
				$headersSMTP = array();
//				$headerlines = explode("\n",trim($this->headers));
				foreach($headers as $k => $hd) {
					if (substr($hd[$i],0,9)==" boundary") {
						$headersSMTP['Content-Type'] .= "\n " . $hd;
					} else {
						$current = explode(':',$hd);
						$headersSMTP[$current[0]] = trim($current[1]);
					}
				}
				$headersSMTP['To']      = $email;
				$headersSMTP['Subject'] = $subject;
	
				// create a new mail object if not existing
				if (!is_a($this->mailObject, 'Mail_smtp') || $this->confSMTP['persist'] == 1) {
					$this->mailObject = NULL;
					$this->mailObject =& Mail::factory('smtp', $this->confSMTP);
				}
				$res = $this->mailObject->send($email, $headersSMTP, $message);
				
			} else {
				t3lib_div::plainMailEncoded($email,$subject,$message,implode(chr(10),$headers));
			}
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
		global $TYPO3_DB;

		$res = $TYPO3_DB->exec_SELECTquery(
			'count(*)',
			'sys_dmail_maillog',
			'mid='.intval($mid).
				' AND response_type=0'.
				($rtbl ? ' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog') : '')
			);
		$row = $TYPO3_DB->sql_fetch_row($res);
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
	function dmailer_isSend($mid,$rid,$rtbl)	{
		global $TYPO3_DB;

		$res = $TYPO3_DB->exec_SELECTquery(
			'uid',
			'sys_dmail_maillog',
			'rid='.intval($rid).
				' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog').
				' AND mid='.intval($mid).
				' AND response_type=0'
			);
		return $TYPO3_DB->sql_num_rows($res);
	}

	/**
	 * get IDs of recipient, which has been sent
	 *
	 * @param	integer		$mid: newsletter ID. UID of the sys_dmail record
	 * @param	string		$rtbl: recipient table
	 * @return	string		list of sent recipients
	 */
	function dmailer_getSentMails($mid,$rtbl)	{
		global $TYPO3_DB;

		$res = $TYPO3_DB->exec_SELECTquery(
			'rid',
			'sys_dmail_maillog',
			'mid='.intval($mid).
				' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl,'sys_dmail_maillog').
				' AND response_type=0'
			);
		$list = array();
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
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
	function dmailer_addToMailLog($mid,$rid,$size,$parsetime,$html,$email)	{
		global $TYPO3_DB;

		$temp_recip = explode('_',$rid);

		$insertFields = array(
			'mid' => intval($mid),
			'rtbl' => $temp_recip[0],
			'rid' => intval($temp_recip[1]),
			'email' => $email,
			'tstamp' => time(),
			'url' => '',
			'size' => $size,
			'parsetime' => $parsetime,
			'html_sent' => intval($html)
			);

		$res = $TYPO3_DB->exec_INSERTquery(
			'sys_dmail_maillog',
			$insertFields
			);
		return $res;
	}

	/**
	 * called from the dmailerd script. Look if there is newsletter to be sent and do the sending process. Otherwise quit runtime
	 *
	 * @return	void		...
	 */
	function runcron()      {
		global $LANG, $TYPO3_CONF_VARS, $TYPO3_DB;

		$this->sendPerCycle = trim($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['sendPerCycle']) ? intval($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['sendPerCycle']) : 50;
		$this->useDeferMode = trim($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['useDeferMode']) ? intval($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['useDeferMode']) : 0;
		$this->notificationJob = intval($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['notificationJob']);
		if(!is_object($LANG) ) {
			require (PATH_typo3.'sysext/lang/lang.php');
			$LANG = t3lib_div::makeInstance('language');
			$L = $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cron_language'] ? $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cron_language'] : $this->user_dmailerLang;
			$LANG->init(trim($L));
			$LANG->includeLLFile('EXT:direct_mail/locallang/locallang_mod2-6.xml');
		}

		$pt = t3lib_div::milliseconds();

		$res = $TYPO3_DB->exec_SELECTquery(
			'*',
			'sys_dmail',
			'scheduled!=0'.
				' AND scheduled<'.time().
				' AND scheduled_end=0'.
				t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			'scheduled'
			);
		$this->logArray[]=$LANG->getLL('dmailer_invoked_at'). ' ' . date('h:i:s d-m-Y');

		if ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				// format headers for SMTP use
			if ($this->useSmtp) {
				// create a new mail object to be used to sending the mass email and notification job
				if (!is_a($this->mailObject, 'Mail_smtp') || $this->confSMTP['persist'] == 1) {
					$this->mailObject = NULL;
					$this->mailObject =& Mail::factory('smtp', $this->confSMTP['smtp']);
				}
			}

			$this->logArray[]=$LANG->getLL('dmailer_sys_dmail_record') . ' ' . $row['uid']. ", '" . $row['subject'] . "' " . $LANG->getLL('dmailer_processed');
			$this->dmailer_prepare($row);
			$query_info=unserialize($row['query_info']);
			if (!$row['scheduled_begin'])   {
				$this->dmailer_setBeginEnd($row['uid'],'begin');
			}
			$finished = $this->dmailer_masssend_list($query_info,$row['uid']);
			if ($finished)  {
				$this->dmailer_setBeginEnd($row['uid'],'end');
			}
		} else {
			$this->logArray[]=$LANG->getLL('dmailer_nothing_to_do');
		}

		$parsetime=t3lib_div::milliseconds()-$pt;
		$this->logArray[]=$LANG->getLL('dmailer_ending'). ' ' . $parsetime . ' ms';
	}

	/**
	 * initializing the t3lib_htmlmail class and setting the first global variables. Write to log file if it's a cronjob
	 *
	 * @param	integer		$user_dmailer_sendPerCycle: total of recipient in a cycle
	 * @param	string		$user_dmailer_lang: language of the user
	 * @return	void		...
	 */
	function start($user_dmailer_sendPerCycle=50,$user_dmailer_lang='en') {

		parent::start();

			// Mailer engine parameters
		$this->sendPerCycle = $user_dmailer_sendPerCycle;
		$this->user_dmailerLang = $user_dmailer_lang;
		if(!$this->nonCron){
			$this->dmailer_log('w','starting directmail cronjob');
		}
		
		// Ivan Kartolo
		// set conf for SMTP
		$this->confSMTP = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['smtp'];
		$this->useSmtp = ($this->confSMTP['enabled'] && class_exists('Mail'));
		if($this->useSmtp){
			//if using SMTP, don't encode the headers
			$this->dontEncodeHeader = TRUE;
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
		$email = $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['adminEmail'];
		$subject = 'Direct Mail cronjob from '.$TYPO3_CONF_VARS['SYS']['sitename'];

		$headers[]='From: '.$email;
		$headers[]='Reply-To: '.$email;

		$message = 'Cannot write to logfile '.$logfilePath.chr(10).
				'Please check diskspace.'.chr(10).
				'Log message: '.chr(10).date('d-m-Y H:i:s').' => '.$logMsg.chr(10).
				'Remove '.PATH_site.'typo3temp/tx_directmail_cron.lock '.'to continue sending';

		if(!$fp = fopen(PATH_site.$logfilePath,$writeMode)){
			//cannot create or open file, email admin
			t3lib_div::plainMailEncoded($email, $subject, $message, implode(chr(10),$headers));
			//die
			die('logfile cannot be opened. Quiting directmail sending!');
		} else {
			if(fwrite($fp,$content) === false){
				//cannot write log file, email admin
				t3lib_div::plainMailEncoded($email, $subject, $message, implode(chr(10),$headers));
				//die
				die('logfile cannot be written. Quiting directmail sending!');
			}
			fclose($fp);
		}
	}

	/**
	 * call parent's useBase64 function and set an additional email header.
	 *
	 * @return	void		...
	 */
	function useBase64()	{
		parent::useBase64();
		if ($this->flowedFormat) {
			$this->plain_text_header = 'Content-Type: text/plain; charset='.$this->charset.'; format=flowed'.$this->linebreak.'Content-Transfer-Encoding: base64';
		}
	}

	/**
	 * call parent's use8it function and set an additional email header
	 *
	 * @return	void		...
	 */
	function use8Bit()	{
		parent::use8Bit();
		if ($this->flowedFormat) {
			$this->plain_text_header = 'Content-Type: text/plain; charset='.$this->charset.'; format=flowed'.$this->linebreak.'Content-Transfer-Encoding: 8bit';
		}
	}

	/**
	 * Send of the email using php mail function.
	 *
	 * @return	boolean		true if there is recipient and content, otherwise false
	 */
	function sendTheMail () {
//		$conf = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail'];

			// format headers for SMTP use
		if ($this->useSmtp) {
			$headers = array();
			$headerlines = explode("\n",trim($this->headers));
			foreach($headerlines as $k => $hd) {
				if (substr($hd[$i],0,9)==" boundary") {
					$headers['Content-Type'] .= "\n " . $hd;
				} else {
					$current = explode(':',$hd);
					$headers[$current[0]] = $current[1];
				}
			}
			$headers['To']      = $this->recipient;
			$headers['Subject'] = $this->subject;

			// create a new mail object if not existing
			if (!is_a($this->mailObject, 'Mail_smtp') || $this->confSMTP['persist'] == 1) {
				$this->mailObject = NULL;
				$this->mailObject =& Mail::factory('smtp', $this->confSMTP);
			}
		}

		// Sends the mail, requires the recipient, message and headers to be set.
		if (trim($this->recipient) && trim($this->message))	{	//  && trim($this->headers)
			$returnPath = (strlen($this->returnPath)>0)?"-f".$this->returnPath:'';
				//On windows the -f flag is not used (specific for Sendmail and Postfix), but instead the php.ini parameter sendmail_from is used.
			if($this->returnPath) {
				ini_set(sendmail_from, $this->returnPath);
			}
				// Setting defer mode
			$deferMode = $this->useDeferMode ? (($returnPath ? ' ': '') . '-O DeliveryMode=defer') : '';

			if ($this->useSmtp)	{
				$res = $this->mailObject->send($this->recipient, $headers, $this->message);
			} 
			elseif(!ini_get('safe_mode') && $this->forceReturnPath) {
				//If safe mode is on, the fifth parameter to mail is not allowed, so the fix wont work on unix with safe_mode=On
				mail($this->recipient,
					  $this->subject,
					  $this->message,
					  $this->headers,
					  $returnPath.$deferMode);
			} else {
				mail($this->recipient,
					  $this->subject,
					  $this->message,
					  $this->headers);
			}
				// Sending copy:
			if ($this->recipient_copy)	{
				if ($this->useSmtp)	{
					$res = $this->mailObject->send($this->recipient_copy, $headers, $this->message);
				} elseif (!ini_get('safe_mode') && $this->forceReturnPath) {
					mail($this->recipient_copy,
								$this->subject,
								$this->message,
								$this->headers,
								$returnPath.$deferMode);
				} else {
					mail($this->recipient_copy,
								$this->subject,
								$this->message,
								$this->headers	);
				}
			}
				// Auto response
			if ($this->auto_respond_msg)	{
				$theParts = explode('/',$this->auto_respond_msg,2);
				$theParts[1] = str_replace("/",chr(10),$theParts[1]);
				if ($this->useSmtp)	{
	                                $headers['Subject'] = $theParts[0];
	                                $headers['From'] = $this->recipient;
	                                $res = $this->mailObject->send($this->from_email, $headers, $theParts[1]);
				} elseif (!ini_get('safe_mode') && $this->forceReturnPath) {
					mail($this->from_email,
								$theParts[0],
								$theParts[1],
								"From: ".$this->recipient,
								$returnPath.$deferMode);
				} else {
					mail($this->from_email,
								$theParts[0],
								$theParts[1],
								"From: ".$this->recipient);
				}
			}
			if($this->returnPath) {
				ini_restore(sendmail_from);
			}
			return true;
		} else {
			return false;
		}
	}


	/**
	 * add HTML to an email
	 *
	 * @param	string		location of the HTML
	 * @return	mixed		bool: HTML fetch status. string: if HTML is a frameset.
	 */
	function addHTML($file)	{
			// Adds HTML and media, encodes it from a URL or file
		$status = $this->fetchHTML($file);
		if (!$status)	{
			return false;
		}
		if ($this->extractFramesInfo())	{
			return "Document was a frameset. Stopped";
		}
		$this->extractMediaLinks();
		$this->extractHyperLinks();
		$this->fetchHTMLMedia();
		$this->substMediaNamesInHTML(!$this->includeMedia);	// 0 = relative
		$this->substHREFsInHTML();
		$this->setHTML($this->encodeMsg($this->theParts["html"]["content"]));
		return true;
	}

	/**
	 * if it's a HTML email, which MIME type?
	 *
	 * @return	string		MIME type of the email
	 */
	function getHTMLContentType()	{
		return (count($this->theParts["html"]["media"]) && $this->includeMedia) ? 'multipart/related;' : 'multipart/alternative;';
	}

	/**
	 * added additional HTML and set the boundary and transfer encoding
	 *
	 * @param	string		$boundary: boundary of the parts
	 * @return	void		...
	 */
	function constructHTML ($boundary)	{
		if (count($this->theParts["html"]["media"]) && $this->includeMedia) {	// If media, then we know, the multipart/related content-type has been set before this function call...
			$this->add_message("--".$boundary);
				// HTML has media
			$newBoundary = $this->getBoundary();
			$this->add_message("Content-Type: multipart/alternative;");
			$this->add_message(' boundary="'.$newBoundary.'"');
			$this->add_message('Content-Transfer-Encoding: 7bit');
			$this->add_message('');

			$this->constructAlternative($newBoundary);	// Adding the plaintext/html mix

			$this->constructHTML_media($boundary);
		} else {
			$this->constructAlternative($boundary);	// Adding the plaintext/html mix, and if no media, then use $boundary instead of $newBoundary
		}
	}

	/**
	 * adding HTML media. encode the media using base64.
	 *
	 * @param	string		$boundary: boundary of the parts
	 * @return	void		...
	 */
	function constructHTML_media($boundary) {
			// media is added
		if (is_array($this->theParts["html"]["media"]) && $this->includeMedia)	{
			reset($this->theParts["html"]["media"]);
			while(list($key,$media)=each($this->theParts["html"]["media"]))	{
				if (!$this->mediaList || t3lib_div::inList($this->mediaList,$key))	{
					$this->add_message("--".$boundary);
					$this->add_message("Content-Type: ".$media["ctype"]);
					$this->add_message("Content-ID: <part".$key.".".$this->messageid.">");
					$this->add_message("Content-Transfer-Encoding: base64");
					$this->add_message('');
					$this->add_message($this->makeBase64($media["content"]));
				}
			}
		}
		$this->add_message("--".$boundary."--\n");
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
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.dmailer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.dmailer.php']);
}
?>
