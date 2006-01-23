<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 1999-2003 Kasper Skårhøj (kasper@typo3.com)
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
 * Extension of the t3lib_readmail class for the purposes of the Direct mail extension.
 *
 * @author	Kasper Skårhøj <kasper@typo3.com>
 * @author	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * Analysis of return mail reason is enhanced by checking more possible reason texts.
 * Tested on mailing list of approx. 1500 members with most domains in México and reason text in English or Spanish.
 */

require_once (PATH_t3lib.'class.t3lib_readmail.php');

class readmail extends t3lib_readmail { 

	/**
	* The getMessage method is modified to avoid breaking the message when it contains a Content-Type: message/delivery-status
	* 
	*/
	function getMessage($mailParts) {
		if ( ereg('Content-Type: message/delivery-status', substr($mailParts['CONTENT'],0,5000)) ) {		//Don't break it, we're only looking for a reason
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
	* @param       string          message body/text
	* @return      array           key/value pairs with analysis result. Eg. "reason", "content", "reason_text", "mailserver" etc.
	*/
	function analyseReturnError($c) {
		$cp=array();
		if ( strstr($c,'--- Below this line is a copy of the message.') || strstr($c,'------ This is a copy of the message, including all the headers.') ) {               // QMAIL
			if ( strstr($c,'--- Below this line is a copy of the message.') ) {
				$parts = explode('--- Below this line is a copy of the message.',$c,2);		// Splits by the QMAIL divider
			} else {
				$parts = explode('------ This is a copy of the message, including all the headers.',$c,2);	// Splits by the QMAIL divider
			}
			$cp['content'] = trim($parts[0]);
			$parts = explode('>:',$cp['content'],2);
			$cp['reason_text'] = trim($parts[1])?trim($parts[1]):$cp['content'];
			$cp['mailserver'] = 'Qmail';
			die($cp['content']);
			if (eregi('over quota|quota exceeded|Quota Exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full|mailbox lleno',$cp['reason_text'])) {
				$cp['reason'] = 551;
			} elseif (eregi('no mailbox|account does not exist|User unknown|User is unknown|unknown user|Unknown local part|unrouteable address|does not have an account here|No such user|account has been disabled or discontinued|user disabled|invalid recipient|Invalid Recipient|mailbox unavailable|550 5.1.1 Recipient|unknown or illegal alias|is unknown at host|is not a valid mailbox|no mailbox here by that name|we do not relay|5.7.1 Unable to relay|Cuenta no activa|inactive user|User is inactive|Mailaddress is administratively disabled',$cp['reason_text']))	{
				$cp['reason'] = 550;	// 550 Invalid recipient
			} elseif (eregi('t find any host named|unrouteable mail domain|not reached for any host after a long failure period|Domain invalid|host lookup did not complete: retry timeout exceeded',$cp['reason_text'])) {
				$cp['reason'] = 552;	// Bad host
			} elseif (eregi('Error in Header|Header Error|invalid Message|Invalid structure|header line format error',$cp['reason_text'])) {
				$cp['reason'] = 554;
			} else {
				$cp['reason'] = -1;
			}
		} elseif ( strstr($c, 'The Postfix program') )     {               // Postfix
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
		} elseif ( strstr($c, 'Your message cannot be delivered to the following recipients:') ) {        // whoever this is...
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(strstr($cp['content'],'Your message cannot be delivered to the following recipients:'));
			$cp['reason_text']=trim(substr($cp['reason_text'],0,500));
			$cp['mailserver']='unknown';
			if (eregi('Not found in directory|Unknown Recipient|Delivery failed 550|Receiver not found|account has been disabled or discontinued|User not listed|recipient problem|User unknown|recipient name is not recognized|we do not relay|invalid recipient|550 Requested action not taken|unknown or illegal alias|not listed in public Name & Address Book|is unknown at host|is not a valid mailbox|unknown user|User is unknown|rejected address',$cp['reason_text'])) {
				$cp['reason']=550;	// 550 Invalid maibox
			} elseif (eregi('over quota|quota exceeded|Quota Exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full',$cp['reason_text']))	{
				$cp['reason']=551;	// 551 Mailbox full
			} elseif (eregi('Error in Header|Header Error|invalid Message|header line format error',$cp['reason_text']))	{
				$cp['reason']=554;
			} elseif (eregi('t find any host named|unrouteable mail domain|no es posible conectar correctamente',$cp['reason_text'])) {
				$cp['reason']=552;	// Bad host
			} else {
				$cp['reason']=-1;
			}
		} elseif ( strstr($c, 'Diagnostic-Code: X-Notes') ) {        // Lotus Notes
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(strstr($cp['content'],'Diagnostic-Code: X-Notes'));
			$cp['reason_text'] = trim(substr($cp['reason_text'],0,200));
			$cp['mailserver']='Notes';
			if (eregi('not listed in public Name & Address Book|not listed in Domino Directory|Domino Directory entry does not|Not found in directory|Unknown Recipient|Delivery failed 550|Receiver not found|account has been disabled or discontinued|User not listed|recipient problem|User unknown|recipient name is not recognized|we do not relay|invalid recipient|550 Requested action not taken|unknown or illegal alias|is unknown at host|is not a valid mailbox|unknown user|User is unknown',$cp['reason_text'])) {
				$cp['reason']=550;	// 550 Invalid maibox
			} elseif (eregi('over quota|quota exceeded|Quota Exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full',$cp['reason_text']))	{
				$cp['reason']=551;	// 551 Mailbox full
			} elseif (eregi('Error in Header|Header Error|invalid Message|header line format error',$cp['reason_text']))	{
				$cp['reason']=554;
			} elseif (eregi('t find any host named|unrouteable mail domain|no es posible conectar correctamente',$cp['reason_text'])) {
				$cp['reason']=552;	// Bad host
			} else {
				$cp['reason']=-1;
			}
		} else {        // No-named:
			$cp['content']=trim($c);
			$cp['reason_text']=trim(substr($c,0,1000));
			$cp['mailserver']='unknown';
			if (eregi('Unknown Recipient|Delivery failed 550|Receiver not found|Status: 5.1.1|account has been disabled or discontinued|User not listed|No such user|recipient problem|User unknown|recipient name is not recognized|we do not relay|invalid recipient|550 Requested action not taken|unknown or illegal alias|not listed in public Name & Address Book|is unknown at host|is not a valid mailbox|unknown user|User is unknown|destination addresses were unknown',$cp['reason_text'])) {
				$cp['reason']=550;	// 550 Invalid maibox
			} elseif (eregi('over quota|quota exceeded|Quota Exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full|mailbox lleno|User mailbox exceeds allowed size',$cp['reason_text']))	{
				$cp['reason']=551;	// 551 Mailbox full
			} elseif (eregi('Error in Header|Header Error|invalid Message|header line format error',$cp['reason_text']))	{
				$cp['reason']=554;
			} elseif (eregi('t find any host named|Domain invalid|unrouteable mail domain|no es posible conectar correctamente',$cp['reason_text'])) {
				$cp['reason']=552;	// Bad host
			} else {
				$cp['reason']=-1;
			}
		}
		return $cp;     
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.readmail.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.readmail.php']);
}
?>