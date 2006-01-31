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
 * Class, doing the sending of Direct-mails, eg. through a cron-job
 *
 * @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author      Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * $Id$
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   86: class dmailer extends t3lib_htmlmail
 *   97:     function dmailer_prepare($row)
 *  147:     function dmailer_sendAdvanced($recipRow,$tableNameChar)
 *  218:     function dmailer_sendSimple($addressList)
 *  241:     function dmailer_getBoundaryParts($cArray,$userCategories)
 *  263:     function dmailer_masssend($query_info,$table,$mid)
 *  299:     function dmailer_masssend_list($query_info,$mid)
 *  360:     function shipOfMail($mid,$recipRow,$tKey)
 *  377:     function convertFields($recipRow)
 *  392:     function dmailer_setBeginEnd($mid,$key)
 *  416:     function dmailer_howManySendMails($mid,$rtbl='')
 *  430:     function dmailer_isSend($mid,$rid,$rtbl)
 *  442:     function dmailer_getSentMails($mid,$rtbl)
 *  461:     function dmailer_addToMailLog($mid,$rid,$size,$parsetime,$html,$email)
 *  483:     function runcron()
 *
 * TOTAL FUNCTIONS: 14
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
class dmailer extends t3lib_htmlmail {
	var $sendPerCycle =50;
	var $logArray =array();
	var $massend_id_lists = array();
	var $flag_html = 0;
	var $flag_plain = 0;
	var $user_dmailerLang = 'en';

	/**
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function dmailer_prepare($row)	{
		$sys_dmail_uid = $row['uid'];
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
		$this->from_email = $row['from_email'];
		$this->from_name = ($row['from_name']) ? $row['from_name'] : '';
		$this->replyto_email = ($row['replyto_email']) ? $row['replyto_email'] : '';
		$this->replyto_name = ($row['replyto_name']) ? $row['replyto_name'] : '';
		$this->organisation = ($row['organisation']) ? $row['organisation'] : '';
		$this->priority = t3lib_div::intInRange($row['priority'],1,5);
		$this->mailer = 'TYPO3 Direct Mail module';
		
		$this->dmailer['sectionBoundary'] = '<!--DMAILER_SECTION_BOUNDARY';
		$this->dmailer['html_content'] = base64_decode($this->theParts['html']['content']);
		$this->dmailer['plain_content'] = base64_decode($this->theParts['plain']['content']);
		$this->dmailer['messageID'] = $this->messageid;
		$this->dmailer['sys_dmail_uid'] = $sys_dmail_uid;
		$this->dmailer['sys_dmail_rec'] = $row;
		
		$this->dmailer['boundaryParts_html'] = explode($this->dmailer['sectionBoundary'], '_END-->'.$this->dmailer['html_content']);
		while(list($bKey,$bContent)=each($this->dmailer['boundaryParts_html']))	{
			$this->dmailer['boundaryParts_html'][$bKey] = explode('-->',$bContent,2);
				// Now, analyzing which media files are used in this part of the mail:
			$mediaParts = explode('cid:part',$this->dmailer['boundaryParts_html'][$bKey][1]);
			reset($mediaParts);
			next($mediaParts);
			while(list(,$part)=each($mediaParts))	{
				$this->dmailer['boundaryParts_html'][$bKey]['mediaList'].=','.strtok($part,'.');
			}
		}
		$this->dmailer['boundaryParts_plain'] = explode($this->dmailer['sectionBoundary'], '_END-->'.$this->dmailer['plain_content']);
		while(list($bKey,$bContent)=each($this->dmailer['boundaryParts_plain']))	{
			$this->dmailer['boundaryParts_plain'][$bKey] = explode('-->',$bContent,2);
		}

		$this->flag_html = $this->theParts['html']['content'] ? 1 : 0;
		$this->flag_plain = $this->theParts['plain']['content'] ? 1 : 0;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$recipRow: ...
	 * @param	[type]		$tableNameChar: ...
	 * @return	[type]		...
	 */
	function dmailer_sendAdvanced($recipRow,$tableNameChar)	{
		$returnCode=0;
		if ($recipRow['email'])	{
			$midRidId = 'MID'.$this->dmailer['sys_dmail_uid'].'_'.$tableNameChar.$recipRow['uid'];
			$uniqMsgId = md5(microtime()).'_'.$midRidId;
			$rowFieldsArray = explode(',','uid,name,title,email,phone,www,address,company,city,zip,country,fax,firstname');
			if ($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields'])	{
				$rowFieldsArray = array_merge($rowFieldsArray, explode(',',$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields']));
			}
			$uppercaseFieldsArray = explode(',', 'name,firstname');
			$authCode = t3lib_div::stdAuthCode($recipRow['uid']);
			$this->mediaList='';
			if ($this->flag_html && $recipRow['module_sys_dmail_html'])		{
				$tempContent_HTML = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'],$recipRow['sys_dmail_categories_list']);
				
				reset($rowFieldsArray);
				while(list(,$substField)=each($rowFieldsArray))	{
					$tempContent_HTML = str_replace('###USER_'.$substField.'###', $recipRow[$substField], $tempContent_HTML);
				}
				reset($uppercaseFieldsArray);
				while(list(,$substField)=each($uppercaseFieldsArray))	{
					$tempContent_HTML = str_replace('###USER_'.strtoupper($substField).'###', strtoupper($recipRow[$substField]), $tempContent_HTML);
				}
				$tempContent_HTML = str_replace('###SYS_TABLE_NAME###', $tableNameChar, $tempContent_HTML);	// Put in the tablename of the userinformation
				$tempContent_HTML = str_replace('###SYS_MAIL_ID###', $this->dmailer['sys_dmail_uid'], $tempContent_HTML);	// Put in the uid of the mail-record
				$tempContent_HTML = str_replace('###SYS_AUTHCODE###', $authCode, $tempContent_HTML);
				$tempContent_HTML = str_replace($this->dmailer['messageID'], $uniqMsgId, $tempContent_HTML);	// Put in the unique message id in HTML-code
				$this->theParts['html']['content'] = $this->encodeMsg($tempContent_HTML);
				$returnCode|=1;
			} else $this->theParts['html']['content'] = '';

				// Plain
			if ($this->flag_plain)		{
				$tempContent_Plain = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'],$recipRow['sys_dmail_categories_list']);
				reset($rowFieldsArray);
				while(list(,$substField)=each($rowFieldsArray))	{
					$tempContent_Plain = str_replace('###USER_'.$substField.'###', $recipRow[$substField], $tempContent_Plain);
				}
				reset($uppercaseFieldsArray);
				while(list(,$substField)=each($uppercaseFieldsArray))	{
					$tempContent_Plain = str_replace('###USER_'.strtoupper($substField).'###', strtoupper($recipRow[$substField]), $tempContent_Plain);
				}
				$tempContent_Plain = str_replace('###SYS_TABLE_NAME###', $tableNameChar, $tempContent_Plain);	// Put in the tablename of the userinformation
				$tempContent_Plain = str_replace('###SYS_MAIL_ID###', $this->dmailer['sys_dmail_uid'], $tempContent_Plain);	// Put in the uid of the mail-record
				$tempContent_Plain = str_replace('###SYS_AUTHCODE###', $authCode, $tempContent_Plain);

				if (trim($this->dmailer['sys_dmail_rec']['use_rdct']))        {
					$tempContent_Plain = t3lib_div::substUrlsInPlainText($tempContent_Plain, $this->dmailer['sys_dmail_rec']['long_link_mode']?'all':'76');
				}

				$this->theParts['plain']['content'] = $this->encodeMsg($tempContent_Plain);
				$returnCode|=2;
			} else $this->theParts['plain']['content'] = '';

				// Set content
			$this->Xid = $midRidId.'-'.md5($midRidId);
			$this->returnPath = str_replace('###XID###',$midRidId,$this->dmailer['sys_dmail_rec']['return_path']);
			
			$this->part=0;
			$this->setHeaders();
			$this->setContent();
			if ($recipRow['name']) $this->setRecipient('"' . $recipRow['name'] . '" <' . $recipRow['email'] . '>');
				else $this->setRecipient($recipRow['email']);
			
			$this->message = str_replace($this->dmailer['messageID'], $uniqMsgId, $this->message);	// Put in the unique message id in whole message body
			$this->sendtheMail();
		}
		return $returnCode;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$addressList: ...
	 * @return	[type]		...
	 */
	function dmailer_sendSimple($addressList)	{
		if ($this->theParts['html']['content'])		{
			$this->theParts['html']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'],-1));
		} else $this->theParts['html']['content'] = '';
		if ($this->theParts['plain']['content'])		{
			$this->theParts['plain']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'],-1));
		} else $this->theParts['plain']['content'] = '';

		$this->setHeaders();
		$this->setContent();
		$this->setRecipient($addressList);
		$this->sendtheMail();
		return true;
	}

	/**
	 * This function checks which content elements are suppsed to be sent to the recipient. tslib_content inserts dmail boudary markers in the content specifying which elements are intended for which categories, this functions check if the recipeient is subscribing to any of these categories and filters out the elements that are inteded for categories not subscribed to.
	 *
	 * @param	[type]		$cArray: array of content split by dmail voundary
	 * @param	[type]		$userCategories: The list of categories the user is subscrbing to.
	 * @return	[type]		...
	 */
	function dmailer_getBoundaryParts($cArray,$userCategories)	{
		//$userCategories = intval($userCategories);
		reset($cArray);
		$returnVal='';
		while(list(,$cP)=each($cArray))	{
			$key=substr($cP[0],1);
			$isSubscribed = FALSE;
			
			if ($key=='END' || !$key || intval($userCategories)==-1) {
				    $returnVal.=$cP[1];
				    $this->mediaList.=$cP['mediaList'];
			} else {
				foreach(explode(',',$key) as $group) {
					if(t3lib_div::inList($userCategories,$group)) {
						$isSubscribed= TRUE;
					}
				}
				if ($isSubscribed) {
					$returnVal.=$cP[1];
					$this->mediaList.=$cP['mediaList'];
				}
			}
		}
		return $returnVal;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$query_info: ...
	 * @param	[type]		$table: ...
	 * @param	[type]		$mid: ...
	 * @return	[type]		...
	 */
	function dmailer_masssend($query_info,$table,$mid)	{
		$enableFields['tt_address']='tt_address.deleted=0 AND tt_address.hidden=0';
		$enableFields['fe_users']='fe_users.deleted=0 AND fe_users.disable=0';
		$tKey = substr($table,0,1);
		$begin=intval($this->dmailer_howManySendMails($mid,$tKey));
		if ($query_info[$table])	{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($table.'.*', $table, $enableFields[$table].' AND ('.$query_info[$table].')', '', 'tstamp DESC', intval($begin).','.$this->sendPerCycle); // This way, we select newest edited records first. So if any record is added or changed in between, it'll end on top and do no harm
			if ($GLOBALS['TYPO3_DB']->sql_error())	{
				die ($GLOBALS['TYPO3_DB']->sql_error());
			}
			$numRows = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			$cc=0;
			while($recipRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$recipRow['sys_dmail_categories_list'] = $this->getListOfRecipentCategories($table,$recipRow['uid']);
				if (!$this->dmailer_isSend($mid,$recipRow['uid'],$tKey))	{
					$pt = t3lib_div::milliseconds();
					if ($recipRow['telephone'])	$recipRow['phone'] = $recipRow['telephone'];	// Compensation for the fact that fe_users has the field, 'telephone' instead of 'phone'
					$recipRow['firstname']=strtok(trim($recipRow['name']),' ');

					$rC = $this->dmailer_sendAdvanced($recipRow,$tKey);
					$this->dmailer_addToMailLog($mid,$tKey.'_'.$recipRow['uid'],strlen($this->message),t3lib_div::milliseconds()-$pt,$rC,$recipRow['email']);
				}
				$cc++;
			}
			$this->logArray[]='Sending '.$cc.' mails to table '.$table;
			if ($numRows < $this->sendPerCycle)	return true;
		}
		return false;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$query_info: ...
	 * @param	[type]		$mid: ...
	 * @return	[type]		...
	 */
	function dmailer_masssend_list($query_info,$mid) {
		global $TYPO3_DB;
		
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
					} else {$tKey='u';}

						// Send mails
					$sendIds=$this->dmailer_getSentMails($mid,$tKey);
					if ($table=='PLAINLIST')	{
						$sendIdsArr=explode(',',$sendIds);
						reset($listArr);
						while(list($kval,$recipRow)=each($listArr))	{
							$kval++;
							if (!in_array($kval,$sendIdsArr))	{
								if ($c>=$this->sendPerCycle)	{$returnVal = false; break;}		// We are NOT finished!
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
								'uid IN ('.$idList.') AND uid NOT IN ('.($sendIds?$sendIds:0).') AND '.($enableFields[$table]?$enableFields[$table]:'1=1'),
								'',
								'',
								$this->sendPerCycle+1);
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
					$this->logArray[]='Sending '.$ct.' mails to table '.$table;
				}
			}
		}
		return $returnVal;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mid: ...
	 * @param	[type]		$recipRow: ...
	 * @param	[type]		$tKey: ...
	 * @return	[type]		...
	 */
	function shipOfMail($mid,$recipRow,$tKey)	{
		if (!$this->dmailer_isSend($mid,$recipRow['uid'],$tKey))	{
			$pt = t3lib_div::milliseconds();
			$recipRow=$this->convertFields($recipRow);

			$rC=$this->dmailer_sendAdvanced($recipRow,$tKey);
			$this->dmailer_addToMailLog($mid,$tKey.'_'.$recipRow['uid'],strlen($this->message),t3lib_div::milliseconds()-$pt,$rC,$recipRow['email']);
		}
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$recipRow: ...
	 * @return	[type]		...
	 */
	function convertFields($recipRow)	{
		if ($recipRow['telephone'])	$recipRow['phone'] = $recipRow['telephone'];	// Compensation for the fact that fe_users has the field, 'telephone' instead of 'phone'
		$recipRow['firstname']=trim(strtok(trim($recipRow['name']),' '));
		if (strlen($recipRow['firstname'])<2 || ereg('[^[:alnum:]]$',$recipRow['firstname']))		$recipRow['firstname']=$recipRow['name'];		// Firstname must be more that 1 character
		if (!trim($recipRow['firstname']))	$recipRow['firstname']=$recipRow['email'];
		return 	$recipRow;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mid: ...
	 * @param	[type]		$key: ...
	 * @return	[type]		...
	 */
	function dmailer_setBeginEnd($mid,$key)	{
		global $LANG, $TYPO3_CONF_VARS, $TYPO3_DB;
		
		$TYPO3_DB->exec_UPDATEquery('sys_dmail', 'uid='.intval($mid), array('scheduled_'.$key => time()));
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

		$headers[]='From: '.$this->from_name.' <'.$this->from_email.'>';
		$headers[]='Reply-To: '.$this->replyto_email;

		$this->charset = $LANG->origCharSet;
		if ($TYPO3_CONF_VARS['BE']['forceCharset'] && $TYPO3_CONF_VARS['BE']['forceCharset']!=$this->charset)     {
			$message = $LANG->csConvObj->conv($message, $LANG->charSet, $LANG->origCharSet, 1);
		}
		t3lib_div::plainMailEncoded($this->from_email,$subject,$message,implode(chr(13).chr(10),$headers),'quoted-printable',$this->charset);
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mid: ...
	 * @param	[type]		$rtbl: ...
	 * @return	[type]		...
	 */
	function dmailer_howManySendMails($mid,$rtbl='')	{
		global $TYPO3_DB;
		
		$res = $TYPO3_DB->exec_SELECTquery('count(*)', 'sys_dmail_maillog', 'mid='.intval($mid).' AND response_type=0'.($rtbl ? ' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog') : ''));
		$row = $TYPO3_DB->sql_fetch_row($res);
		return $row[0];
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mid: ...
	 * @param	[type]		$rid: ...
	 * @param	[type]		$rtbl: ...
	 * @return	[type]		...
	 */
	function dmailer_isSend($mid,$rid,$rtbl)	{
		global $TYPO3_DB;
		
		$res = $TYPO3_DB->exec_SELECTquery('uid', 'sys_dmail_maillog', 'rid='.intval($rid).' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog').' AND mid='.intval($mid).' AND response_type=0');
		return $TYPO3_DB->sql_num_rows($res);
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mid: ...
	 * @param	[type]		$rtbl: ...
	 * @return	[type]		...
	 */
	function dmailer_getSentMails($mid,$rtbl)	{
		global $TYPO3_DB;
		
		$res = $TYPO3_DB->exec_SELECTquery('rid', 'sys_dmail_maillog', 'mid='.intval($mid).' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog').' AND response_type=0');
		$list = array();
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$list[] = $row['rid'];
		}
		return implode(',', $list);
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mid: ...
	 * @param	[type]		$rid: ...
	 * @param	[type]		$size: ...
	 * @param	[type]		$parsetime: ...
	 * @param	[type]		$html: ...
	 * @return	[type]		...
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

		$TYPO3_DB->exec_INSERTquery('sys_dmail_maillog', $insertFields);
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function runcron()      {
		global $LANG, $TYPO3_CONF_VARS, $TYPO3_DB;
		
		$this->sendPerCycle = trim($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['sendPerCycle']) ? intval($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['sendPerCycle']) : 50;
		$this->useDeferMode = trim($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['useDeferMode']) ? intval($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['useDeferMode']) : 0;

		if(!is_object($LANG) ) {
			require (PATH_typo3.'sysext/lang/lang.php');
			$LANG = t3lib_div::makeInstance('language');
			$L = $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cron_language'] ? $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cron_language'] : $this->user_dmailerLang;
			$LANG->init(trim($L));
			$LANG->includeLLFile('EXT:direct_mail/mod/locallang.php');
		}

		$pt = t3lib_div::milliseconds();

		$res = $TYPO3_DB->exec_SELECTquery('*', 'sys_dmail', 'scheduled!=0 AND scheduled<'.time().' AND scheduled_end=0'.t3lib_BEfunc::deleteClause('sys_dmail'), '', 'scheduled');
		$this->logArray[]=$LANG->getLL('dmailer_invoked_at'). ' ' . date('h:i:s d-m-Y');
		
		if ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
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
	
	function start($user_dmailer_sendPerCycle=50,$user_dmailer_lang='en') {
		global $TYPO3_CONF_VARS;
		
		parent::start();
		
			// Mailer engine parameters
		$this->sendPerCycle = $user_dmailer_sendPerCycle;
		$this->user_dmailerLang = $user_dmailer_lang;
		
	}
	
	function sendTheMail () {
#debug(array($this->recipient,$this->subject,$this->message,$this->headers));
			// Sends the mail, requires the recipient, message and headers to be set.
		if (trim($this->recipient) && trim($this->message))	{	//  && trim($this->headers)
			$returnPath = (strlen($this->returnPath)>0)?"-f".$this->returnPath:'';
				//On windows the -f flag is not used (specific for Sendmail and Postfix), but instead the php.ini parameter sendmail_from is used.
			if($this->returnPath) {
				ini_set(sendmail_from, $this->returnPath);
			}
				// Setting defer mode
			$deferMode = $this->useDeferMode ? (($returnPath ? ' ': '') . '-O DeliveryMode=defer') : '';
			
				//If safe mode is on, the fifth parameter to mail is not allowed, so the fix wont work on unix with safe_mode=On
			if(!ini_get('safe_mode') && $this->forceReturnPath) {
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
				if(!ini_get('safe_mode') && $this->forceReturnPath) {
					mail( 	$this->recipient_copy,
								$this->subject,
								$this->message,
								$this->headers,
								$returnPath.$deferMode);
				} else {
					mail( 	$this->recipient_copy,
								$this->subject,
								$this->message,
								$this->headers	);
				}
			}
				// Auto response
			if ($this->auto_respond_msg)	{
				$theParts = explode('/',$this->auto_respond_msg,2);
				$theParts[1] = str_replace("/",chr(10),$theParts[1]);
				if(!ini_get('safe_mode') && $this->forceReturnPath) {
					mail( 	$this->from_email,
								$theParts[0],
								$theParts[1],
								"From: ".$this->recipient,
								$returnPath.$deferMode);
				} else {
					mail( 	$this->from_email,
								$theParts[0],
								$theParts[1],
								"From: ".$this->recipient);
				}
			}
			if($this->returnPath) {
				ini_restore(sendmail_from);
			}
			return true;
		} else {return false;}
	}
	
	/**
	 * Get the list of categories ids subscribed to by recipient $uid from table $table
	 *
	 */
	function getListOfRecipentCategories($table,$uid) {
		global $TCA, $TYPO3_DB;
		
		t3lib_div::loadTCA($table);
		$mm_table = $TCA[$table]['columns']['module_sys_dmail_category']['config']['MM'];
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid_foreign',
			$mm_table.','.$table,
			'uid_local='.intval($uid).' AND '.$mm_table.'.uid_local='.$table.'.uid'.t3lib_BEfunc::deleteClause($table));
		$list = array();
		while($row = $TYPO3_DB->sql_fetch_assoc($res)) {
			$list[] = $row['uid_foreign'];
		}
		return implode(',', $list);
    }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.dmailer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.dmailer.php']);
}
?>
