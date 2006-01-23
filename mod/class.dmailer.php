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
 *  461:     function dmailer_addToMailLog($mid,$rid,$size,$parsetime,$html)
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

class dmailer extends t3lib_htmlmail {
	var $sendPerCycle =50;
	var $logArray =array();
	var $massend_id_lists = array();
	var $flag_html = 0;
	var $flag_plain = 0;
	var $user_dmailerLang = 'en';
	var $dmailerEncoding = 'quoted-printable';

	/**
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function dmailer_prepare($row)	{
		$sys_dmail_uid = $row['uid'];
		$this->dmailerEncoding = $row['encoding'] ? $row['encoding'] : $this->dmailerEncoding;
		$this->charset = $row['charset'] ? $row['charset'] : $this->charset;
		if(strtolower($this->dmailerEncoding) == 'quoted-printable') { $this->useQuotedPrintable(); }
		if(strtolower($this->dmailerEncoding) == 'base64') { $this->useBase64(); }
		if(strtolower($this->dmailerEncoding) == '8bit') { $this->use8Bit(); }
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
				$tempContent_HTML = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'],$recipRow['module_sys_dmail_category']);
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
				$tempContent_Plain = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'],$recipRow['module_sys_dmail_category']);
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
			$this->setRecipient($recipRow['email']);

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
	 * [Describe function...]
	 *
	 * @param	[type]		$cArray: ...
	 * @param	[type]		$userCategories: ...
	 * @return	[type]		...
	 */
	function dmailer_getBoundaryParts($cArray,$userCategories)	{
		$userCategories = intval($userCategories);
		reset($cArray);
		$returnVal='';
		while(list(,$cP)=each($cArray))	{
			$key=substr($cP[0],1);
			if ($key=='END' || !$key || $userCategories<0 || (intval($key) & $userCategories)>0)	{
				$returnVal.=$cP[1];
				$this->mediaList.=$cP['mediaList'];
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
				if (!$this->dmailer_isSend($mid,$recipRow['uid'],$tKey))	{
					$pt = t3lib_div::milliseconds();
					if ($recipRow['telephone'])	$recipRow['phone'] = $recipRow['telephone'];	// Compensation for the fact that fe_users has the field, 'telephone' instead of 'phone'
					$recipRow['firstname']=strtok(trim($recipRow['name']),' ');

					$rC = $this->dmailer_sendAdvanced($recipRow,$tKey);
					$this->dmailer_addToMailLog($mid,$tKey.'_'.$recipRow['uid'],strlen($this->message),t3lib_div::milliseconds()-$pt,$rC);
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
	function dmailer_masssend_list($query_info,$mid)	{
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
							$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($table.'.*', $table, 'uid IN ('.$idList.') AND uid NOT IN ('.($sendIds?$sendIds:0).') AND '.($enableFields[$table]?$enableFields[$table]:'1=1'), '', '', $this->sendPerCycle+1);
							if ($GLOBALS['TYPO3_DB']->sql_error())	{die ($GLOBALS['TYPO3_DB']->sql_error());}
							while($recipRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
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
			$this->dmailer_addToMailLog($mid,$tKey.'_'.$recipRow['uid'],strlen($this->message),t3lib_div::milliseconds()-$pt,$rC);
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
		
		//$res = $TYPO3_DB->exec_SELECTquery('count(*)', 'sys_dmail_maillog', 'mid='.intval($mid).' AND response_type=0'.($rtbl ? ' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog') : ''));
		$res = $TYPO3_DB->exec_SELECTquery('count(*)', 'sys_dmail_maillog', 'mid='.intval($mid).' AND response_type=0'.($rtbl ? ' AND rtbl='.$this->fullQuoteStr($rtbl, 'sys_dmail_maillog') : ''));
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
		
		//$res = $TYPO3_DB->exec_SELECTquery('uid', 'sys_dmail_maillog', 'rid='.intval($rid).' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog').' AND mid='.intval($mid).' AND response_type=0');
		$res = $TYPO3_DB->exec_SELECTquery('uid', 'sys_dmail_maillog', 'rid='.intval($rid).' AND rtbl='.$this->fullQuoteStr($rtbl, 'sys_dmail_maillog').' AND mid='.intval($mid).' AND response_type=0');
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
		
		//$res = $TYPO3_DB->exec_SELECTquery('rid', 'sys_dmail_maillog', 'mid='.intval($mid).' AND rtbl='.$TYPO3_DB->fullQuoteStr($rtbl, 'sys_dmail_maillog').' AND response_type=0');
		$res = $TYPO3_DB->exec_SELECTquery('rid', 'sys_dmail_maillog', 'mid='.intval($mid).' AND rtbl='.$this->fullQuoteStr($rtbl, 'sys_dmail_maillog').' AND response_type=0');
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
	function dmailer_addToMailLog($mid,$rid,$size,$parsetime,$html)	{
		global $TYPO3_DB;
		
		$temp_recip = explode('_',$rid);

		$insertFields = array(
			'mid' => intval($mid),
			'rtbl' => $temp_recip[0],
			'rid' => intval($temp_recip[1]),
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

		if(!is_object($LANG) ) {
			require (PATH_typo3.'sysext/lang/lang.php');
			$LANG = t3lib_div::makeInstance('language');
			$L = $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cron_language'] ? $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cron_language'] : $this->user_dmailerLang;
			$LANG->init(trim($L));
			$LANG->includeLLFile('EXT:direct_mail/mod/locallang.php');
		}

		$pt = t3lib_div::milliseconds();

		$res = $TYPO3_DB->exec_SELECTquery('*', 'sys_dmail', 'scheduled!=0 AND scheduled<'.time().' AND scheduled_end=0', '', 'scheduled');
		if ($TYPO3_DB->sql_error())	{
			die ($TYPO3_DB->sql_error());
		}
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
/**
 * t3lib_htmlmail system class extended by Stanislas Rolland so that quoted-printable messages can be correctly sent thus avoiding SPAM filtering
 *
 * @author      Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * translate_uri($uri) code from http://ca.php.net/rawurlencode
 * replaces rawurlencode in substMediaNamesInHTML($absolute) and substHREFsInHTML()
 *
 */
		var $charset = 'iso-8859-1';
		var $alt_8bit = 0;
		var $lineBreak;
		var $innerMessageid;
		var $plain_text_header = "Content-Type: text/plain; charset=iso-8859-1\nContent-Transfer-Encoding: quoted-printable";
		var $html_text_header = "Content-Type: text/html; charset=iso-8859-1\nContent-Transfer-Encoding: quoted-printable"; 

	function start ($user_dmailer_sendPerCycle=50,$user_dmailer_lang='en')       {
		global $TYPO3_CONF_VARS;
			// Sets the message id
		$localhost = gethostbyaddr('127.0.0.1');
		if (!$localhost || $localhost == '127.0.0.1' || $localhost == 'localhost') $localhost = md5($TYPO3_CONF_VARS['SYS']['sitename']).'.TYPO3'; 
		$this->innerMessageid = md5(microtime()) . '@' . $localhost;
		$this->messageid = $this->innerMessageid;
		
			// default line break (Unix)
		$this->lineBreak = chr(10);
			// line break for Windows
		if (TYPO3_OS == 'WIN') {
			$this->lineBreak = chr(13).chr(10);
		}
		
		$this->charset = $this->charset ? $this->charset : 'iso-8859-1';

			// Quoted-printable headers by default
		$this->useQuotedPrintable();

			// Mailer engine parameters
		$this->sendPerCycle = $user_dmailer_sendPerCycle;
		$this->user_dmailerLang = $user_dmailer_lang;
	}

	function useQuotedPrintable()    {
		$this->plain_text_header = 'Content-Type: text/plain; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: quoted-printable';
		$this->html_text_header = 'Content-Type: text/html; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: quoted-printable';
		$this->dmailerEncoding = 'quoted-printable';
	}
	
	function useBase64()    {
		$this->plain_text_header = 'Content-Type: text/plain; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: base64';
		$this->html_text_header = 'Content-Type: text/html; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: base64';
		$this->alt_base64 = 1;
		$this->dmailerEncoding = 'base64';
	}

	function use8Bit()    {
		$this->plain_text_header = 'Content-Type: text/plain; charset=' . $this->charset . '; format=flowed' . $this->lineBreak . 'Content-Transfer-Encoding: 8bit';
		$this->html_text_header = 'Content-Type: text/html; charset=' . $this->charset . $this->lineBreak . 'Content-Transfer-Encoding: 8bit';
		$this->alt_8bit = 1;
		$this->dmailerEncoding = '8bit';
	}

	function encodeMsg($content)    {
		switch($this->dmailerEncoding) {
			case 'base64': return $this->makeBase64($content);
			case '8bit': return $content;
			case 'quoted-printable': 
			default: return $this->quoted_printable($content);
		}
	}

	function setHeaders ()  {
			// Clears the header-string and sets the headers based on object-vars.
		$this->headers = "";
			// Message_id
		$this->add_header("Message-ID: <" . $this->innerMessageid . ">");
			// Return path
		if ($this->returnPath)  {
			$this->add_header("Return-Path: ".$this->returnPath);
		}
                         // X-id
                 if ($this->Xid) {
                         $this->add_header("X-Typo3MID: ".$this->Xid);
                 }
                         // From
                 if ($this->from_email)  {
                         if ($this->from_name)   {
                                 $name = $this->convertName($this->from_name);
                                 $this->add_header("From: $name <$this->from_email>");
                         } else {
                                 $this->add_header("From: $this->from_email");
                         }
                 }
                         // Reply
                 if ($this->replyto_email)       {
                         if ($this->replyto_name)        {
                                 $name = $this->convertName($this->replyto_name);
                                 $this->add_header("Reply-To: $name <$this->replyto_email>");
                         } else {
                                 $this->add_header("Reply-To: $this->replyto_email");
                         }
                 }
                         // Organisation
                 if ($this->organisation)        {
                         $name = $this->convertName($this->organisation);
                         $this->add_header("Organisation: $name");
                 }
                         // mailer
                 if ($this->mailer)      {
                         $this->add_header("X-Mailer: $this->mailer");
                 }
                         // priority
                 if ($this->priority)    {
                         $this->add_header("X-Priority: $this->priority");
                 }
                 $this->add_header("Mime-Version: 1.0");
         }

	function constructHTML ($boundary)      {
                 if (count($this->theParts["html"]["media"]))    {       // If media, then we know, the multipart/related content-type has been set before this function call...
                         $this->add_message("--".$boundary);
                         // HTML has media
                         $newBoundary = $this->getBoundary();
                         $this->add_message("Content-Type: multipart/alternative;");
                         $this->add_message(' boundary="'.$newBoundary.'"');

// <Set encoding of media files>
                         $this->add_message("Content-Transfer-Encoding: 7bit");
// </Set encoding of media files>
                         $this->add_message('');
                         $this->constructAlternative($newBoundary);      // Adding the plaintext/html mix
                         $this->constructHTML_media($boundary);
                 } else {
                         $this->constructAlternative($boundary); // Adding the plaintext/html mix, and if no media, then use $boundary instead of $newBoundary
                 }
         }

	/*
	* @author	Kasper Skaarhoj <kasperYYYY@typo3.com>
	*
	* Enables "return path" feature. Function from class.t3lib_htmlmail.php in Typo 3.7.0 framework.
	*/
	function sendTheMail () {
			// Sends the mail.
			// Requires the recipient, message and headers to be set.
#debug(array($this->recipient,$this->subject,$this->message,$this->headers));
		if (trim($this->recipient) && trim($this->message))	{	//  && trim($this->headers)
			$returnPath = (strlen($this->returnPath)>0)?'-f ' . $this->returnPath : '';
				//On windows the -f flag is not used (specific for sendmail and postfix), but instead the php.ini parameter sendmail_from is used.
			if($this->returnPath) {
				ini_set(sendmail_from, $this->returnPath);
			}
				//If safe mode is on, the fifth parameter to mail is not allowed, so the fix wont work on unix with safe_mode=On
			if(!ini_get('safe_mode')) {
				mail(   $this->recipient,
					$this->subject,
					$this->message,
					$this->headers,
					$returnPath);
			} else {
				mail(   $this->recipient,
					$this->subject,
					$this->message,
					$this->headers);
			}
				// Sending copy:
			if ($this->recipient_copy)	{
				if(!ini_get('safe_mode')) {
					mail( 	$this->recipient_copy,
						$this->subject,
						$this->message,
						$this->headers,
						$returnPath);
				} else {
					mail( 	$this->recipient_copy,
						$this->subject,
						$this->message,
						$this->headers	);
				}
			}
				// Auto response
			if ($this->auto_respond_msg)	{
				$theParts = explode("/",$this->auto_respond_msg,2);
				$theParts[1] = str_replace("/",chr(10),$theParts[1]);
				mail( 	$this->from_email,
						$theParts[0],
						$theParts[1],
						"From: ".$this->recipient,
					        $returnPath);
			}
			if($this->returnPath) {
				ini_restore(sendmail_from);
			}
			return true;
		} else {return false;}
	}


	function substMediaNamesInHTML($absolute)       {
		// This substitutes the media-references in $this->theParts["html"]["content"]
		// If $absolute is true, then the refs are substituted with http:// ref's indstead of Content-ID's (cid).
		if (is_array($this->theParts["html"]["media"])) {
                         reset ($this->theParts["html"]["media"]);
                         while (list($key,$val) = each ($this->theParts["html"]["media"]))       {
                                  if ($val["use_jumpurl"] && $this->jumperURL_prefix)     {

                                           $theSubstVal = $this->jumperURL_prefix.$this->ux_translate_uri($val["absRef"]);
               //                          $theSubstVal = $this->jumperURL_prefix.rawurlencode($val["absRef"]);
                                  } else {
                                           $theSubstVal = ($absolute) ? $val["absRef"] : "cid:part".$key.".".$this->messageid;
                                  }
                                  $this->theParts["html"]["content"] = str_replace(
                                           $val["subst_str"], 
                                           $val["quotes"].$theSubstVal.$val["quotes"],
                                           $this->theParts["html"]["content"]      );
                         }
		}
		if (!$absolute) {
                         $this->fixRollOvers();
		}
	}

	function substHREFsInHTML()	{
		// This substitutes the hrefs in $this->theParts["html"]["content"]
		if (is_array($this->theParts["html"]["hrefs"]))	{

			reset ($this->theParts["html"]["hrefs"]);
			while (list($key,$val) = each ($this->theParts["html"]["hrefs"]))	{

				if ($this->jumperURL_prefix && $val["tag"]!="form")	{	// Form elements cannot use jumpurl!
					if ($this->jumperURL_useId)	{
						$theSubstVal = $this->jumperURL_prefix.$key;
					} else {

						$theSubstVal = $this->jumperURL_prefix.$this->ux_translate_uri($val["absRef"]);
               //                               $theSubstVal = $this->jumperURL_prefix.rawurlencode($val["absRef"]);
					}
				} else {
					$theSubstVal = $val["absRef"];
				}
				$this->theParts["html"]["content"] = str_replace(
						$val["subst_str"], 
						$val["quotes"].$theSubstVal.$val["quotes"],
						$this->theParts["html"]["content"]	);
			}
		}
	}

	function substHTTPurlsInPlainText($content)	{
		// This substitutes the http:// urls in plain text with links 
		if ($this->jumperURL_prefix)	{
			$textpieces = explode("http://", $content);
			$pieces = count($textpieces);
			$textstr = $textpieces[0];
			for($i=1; $i<$pieces; $i++)	{
				$len=strcspn($textpieces[$i],chr(32).chr(9).chr(13).chr(10));
				if (trim(substr($textstr,-1))=="" && $len)	{
					$lastChar=substr($textpieces[$i],$len-1,1);
					if (!ereg("[A-Za-z0-9\/#]",$lastChar)) {$len--;}		// Included "\/" 3/12

					$parts[0]="http://".substr($textpieces[$i],0,$len);
					$parts[1]=substr($textpieces[$i],$len);
					
					if ($this->jumperURL_useId)	{
						$this->theParts["plain"]["link_ids"][$i]=$parts[0];
						$parts[0] = $this->jumperURL_prefix."-".$i;
					} else {
						$parts[0] = $this->jumperURL_prefix.$this->ux_translate_uri($parts[0]);
               //                               $parts[0] = $this->jumperURL_prefix.rawurlencode($parts[0]);
					}
					$textstr.=$parts[0].$parts[1];
				} else {
					$textstr.='http://'.$textpieces[$i];
				}				
			}
			$content = $textstr;
		}
		return $content;
	}

	function ux_translate_uri($uri) {           //rawurlencode only in between "/", do not rawurlencode the slas, 
                                                               //because if you do rawurlencode() over the whole URI, path separator characters '/' are also encoded and request will not happen to be correct.
                                                               //  '/' characters should not be encoded, only those parts in between.
		$url_parts = explode('/', $uri);
		for ($i = 0; $i < count($url_parts); $i++) {
		$url_parts[$i] = rawurlencode($url_parts[$i]);

		}
		return implode('/', $url_parts);
	}
	function getExtendedURL($url)   {
			// reads the URL or file and determines the Content-type by either guessing or opening a connection to the host
		$res["content"] = $this->getURL($url);
		if (!$res["content"])   {return false;}
		$pathInfo = parse_url($url);
		$fileInfo = $this->split_fileref($pathInfo["path"]);
		if ($fileInfo["fileext"] == "gif")      {$res["content_type"] = "image/gif";}
		if ($fileInfo["fileext"] == "jpg" || $fileInfo["fileext"] == "jpeg")    {$res["content_type"] = "image/jpeg";}
		if ($fileInfo["fileext"] == "png") {$res["content_type"] = "image/png";}
		if ($fileInfo["fileext"] == "html" || $fileInfo["fileext"] == "htm")    {$res["content_type"] = "text/html";}
		if ($fileInfo["fileext"] == "swf")      {$res["content_type"] = "application/x-shockwave-flash";}
		if (!$res["content_type"])      {$res["content_type"] = $this->getMimeType($url);}
		return $res;
	}
	/**
	 * Quoted-printable encoding modified by Martin Kutschker <Martin.Kutschker@activesolution.at>
	 *
	 * @param       [type]          $string: ...
	 * @return      [type]          ...
	 */
	function quoted_printable($string)      {
			// This functions is buggy. It seems that in the part where the lines are breaked every 76th character, that it fails if the break happens right in a quoted_printable encode character!
		$newString = "";
			// unify internal line breaks
		$string = str_replace(chr(13).chr(10),chr(10),$string); // DOS -> Unix
		$string = str_replace(chr(13),chr(10),$string); // Mac -> Unix
		$theLines = explode(chr(10),$string);   // Break lines. Doesn't work with mac eol's which seems to be 13. But 13-10 or 10 will work
		while (list(,$val)=each($theLines))     {
			$val = ereg_replace(chr(13)."$","",$val);               // removes possible character 13 at the end of line
			$newVal = "";
			$theValLen = strlen($val);
			$len = 0;
			for ($index=0;$index<$theValLen;$index++)       {
				$char = substr($val,$index,1);
				$ordVal =Ord($char);
				if ($len>(76-4) || ($len>(66-4)&&$ordVal==32))  {
					$len=0;
					$newVal.="=".$this->lineBreak;
				}
				if (($ordVal>=33 && $ordVal<=60) || ($ordVal>=62 && $ordVal<=126) || $ordVal==9 || $ordVal==32) {
					$newVal.=$char;
					$len++;
				} else {
					$newVal.=sprintf("=%02X",$ordVal);
					$len+=3;
				}
			}
			$newVal = ereg_replace(chr(32)."$","=20",$newVal);              // replaces a possible SPACE-character at the end of a line
			$newVal = ereg_replace(chr(9)."$","=09",$newVal);               // replaces a possible TAB-character at the end of a line
			$newString.=$newVal.$this->lineBreak;
		}

		return $newString;
	}

	/*
	* @author	Bertrand Mure <bertrand.mure@worldonline.fr>
	*
	* This modification enables using CURL library functions when $TYPO3_CONF_VARS["SYS"]["curlUse"] = '1';
	*/
	function getURL($url)	{
		$url = $this->addUserPass($url);
		return t3lib_div::getURL($url);
	}
	
	function fullQuoteStr($str, $table)     {
		return '\''.addslashes($str).'\'';
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.dmailer.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.dmailer.php']);
}
?>