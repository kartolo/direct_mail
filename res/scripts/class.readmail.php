<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2003 Kasper Sk�rh�j (kasper@typo3.com)
*  (c) 2003-2006 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
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
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version		$Id: class.readmail.php 6012 2007-07-23 12:54:25Z ivankartolo $
 */

require_once (PATH_t3lib.'class.t3lib_readmail.php');

/**
 * Extension of the t3lib_readmail class for the purposes of the Direct mail extension.
 * Analysis of return mail reason is enhanced by checking more possible reason texts.
 * Tested on mailing list of approx. 1500 members with most domains in M�xico and reason text in English or Spanish.
 *
 */
class readmail extends t3lib_readmail {

	var $reason_text = array(
		'550' => 'no mailbox|account does not exist|user unknown|user is unknown|unknown user|unknown local part|unrouteable address|does not have an account here|no such user|user not listed|account has been disabled or discontinued|user disabled|unknown recipient|invalid recipient|recipient problem|recipient name is not recognized|mailbox unavailable|550 5\.1\.1 recipient|status: 5\.1\.1|delivery failed 550|550 requested action not taken|receiver not found|unknown or illegal alias|is unknown at host|is not a valid mailbox|no mailbox here by that name|we do not relay|5\.7\.1 unable to relay|cuenta no activa|inactive user|user is inactive|mailaddress is administratively disabled|not found in directory|not listed in public name & address book|destination addresses were unknown|rejected address|not listed in domino directory|domino directory entry does not|550-5\.1.1 The email account that you tried to reach does not exist',
		'551' => 'over quota|quota exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full|mailbox lleno|user mailbox exceeds allowed size',
		'552' => 't find any host named|unrouteable mail domain|not reached for any host after a long failure period|domain invalid|host lookup did not complete: retry timeout exceeded|no es posible conectar correctamente',
		'554' => 'error in header|header error|invalid message|invalid structure|header line format error'
	);

	/**
	 * Returns special TYPO3 Message ID (MID) from input TO header (the return address of the sent mail from Dmailer)
	 *
	 * @param	string		$to: email address, return address string
	 * @return	array		array with 'mid', 'rtbl' and 'rid' keys are returned.
	 */
	function find_MIDfromReturnPath($to)	{
		$parts = explode('mid',strtolower($to));
		$moreParts=explode('_',$parts[1]);
		$out=array(
			'mid' => $moreParts[0],
			'rtbl' => substr($moreParts[1],0,1),
			'rid' => intval(substr($moreParts[1],1))
		);
		if ($out['rtbl']=='p')		$out['rtbl']='P';

		return($out);
	}

	/**
	 * Returns special TYPO3 Message ID (MID) from input mail content
	 *
	 * @param	string		$content: Mail (header) content
	 * @return	mixed		If "X-Typo3MID" header is found and integrity is OK, then an array with 'mid', 'rtbl' and 'rid' keys are returned. Otherwise void.
	 * @internal
	 */
	function find_XTypo3MID($content) {
		if (strstr($content,"X-TYPO3MID:"))	{
			$p = explode("X-TYPO3MID:", $content, 2);
			$l = explode(chr(10), $p[1], 2);
			list($mid,$hash) = t3lib_div::trimExplode('-',$l[0]);
			if (md5($mid) == $hash)	{
				$moreParts = explode('_',substr($mid,3));
				$out = array(
					'mid' => $moreParts[0],
					'rtbl' => substr($moreParts[1], 0, 1),
					'rid' => substr($moreParts[1], 1)
				);
				return($out);
			}
		}
		return "";
	}

	/**
	 * The getMessage method is modified to avoid breaking the message when it contains a Content-Type: message/delivery-status
	 *
	 * @param	string		$mailParts: parts of the returned email
	 * @return	string		only the content part
	 */
	function getMessage($mailParts) {
		if ( preg_match('/^Content-Type: message\/delivery-status/', substr($mailParts['CONTENT'],0,5000)) ) {		//Don't break it, we're only looking for a reason
			$c = $mailParts['CONTENT'];
		} elseif ($mailParts['content-type']) {
			$CType = $this->getCType($mailParts['content-type']);
			if ($CType['boundary']) {
				$parts = $this->getMailBoundaryParts($CType['boundary'],$mailParts['CONTENT']);
				$c = $this->getTextContent($parts[0]);
                        } else {
				$c=$this->getTextContent(
                                	'Content-Type: '.$mailParts['content-type'].'
					'.$mailParts['CONTENT']
				);
			}
		} else {
			$c = $mailParts['CONTENT'];
		}
		return $c;
	}

	/**
	 * Analyses the return-mail content for the Dmailer module - used to find what reason there was for rejecting the mail
	 * Used by the Dmailer, but not exclusively.
	 *
	 * @param	string		$c: message body/text
	 * @return	array		key/value pairs with analysis result. Eg. "reason", "content", "reason_text", "mailserver" etc.
	 */
	function analyseReturnError($c) {
		$cp = array();
		if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '|' . preg_quote('------ This is a copy of the message, including all the headers.') . '/i', $c)) {               // QMAIL
			if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '/i', $c)) {
				$parts = explode('-- Below this line is a copy of the message.',$c,2);		// Splits by the QMAIL divider
			} else {
				$parts = explode('------ This is a copy of the message, including all the headers.',$c,2);	// Splits by the QMAIL divider
			}
			$cp['content'] = trim($parts[0]);
			$parts = explode('>:',$cp['content'],2);
			$cp['reason_text'] = trim($parts[1])?trim($parts[1]):$cp['content'];
			$cp['mailserver'] = 'Qmail';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		} elseif (strstr($c, 'The Postfix program')) {               // Postfix
			$cp['content'] = trim($c);
			$parts = explode('>:',$c,2);
			$cp['reason_text'] = trim($parts[1]);
			$cp['mailserver'] = 'Postfix';
			if (stristr($cp['reason_text'],'550'))  {
				$cp['reason'] = 550;      // 550 Invalid recipient, User unknown
			} elseif (stristr($cp['reason_text'],'553')) {
				$cp['reason'] = 553;      // No such user
			} elseif (stristr($cp['reason_text'],'551')) {
				$cp['reason'] = 551;      // Mailbox full
			} elseif (stristr($cp['reason_text'],'recipient storage full')) {
				$cp['reason'] = 551;      // Mailbox full
			} else {
				$cp['reason'] = -1;
			}
		} elseif (strstr($c, 'Your message cannot be delivered to the following recipients:')) {        // whoever this is...
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(strstr($cp['content'],'Your message cannot be delivered to the following recipients:'));
			$cp['reason_text']=trim(substr($cp['reason_text'],0,500));
			$cp['mailserver']='unknown';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		} elseif (strstr($c, 'Diagnostic-Code: X-Notes')) {        // Lotus Notes
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(strstr($cp['content'],'Diagnostic-Code: X-Notes'));
			$cp['reason_text'] = trim(substr($cp['reason_text'],0,200));
			$cp['mailserver']='Notes';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		} else {        // No-named:
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(substr($c,0,1000));
			$cp['mailserver'] = 'unknown';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		}
		$cp['mailserver'] = 'Qmail';
		return $cp;
	}

	/**
	 * try to match reason found in the returned email with the defined reasons (see $reason_text)
	 *
	 * @param	string		$text: content of the returned email
	 * @return	integer		the error code.
	 */
	function extractReason($text) {
		$reason = -1;
		foreach ($this->reason_text as $case => $value) {
			if (preg_match('/'. $value .'/i',$text)) {
				return intval($case);
			}
		}
		return $reason;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.readmail.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.readmail.php']);
}
?>