<?php
namespace DirectMailTeam\DirectMail\Module;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
 *  (c) 2005-2006 Jan-Erik Revsbech <jer@moccompany.com>
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
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 *
 * @version 	$Id: class.tx_directmail_mailer_engine.php 30331 2010-02-22 22:27:07Z ivankartolo $
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Backend\Utility\IconUtility;

/**
 * Module Mailer-Engine for tx_directmail extension
 *
 */
class MailerEngine extends \TYPO3\CMS\Backend\Module\BaseScriptClass {
	var $extKey = 'direct_mail';
	var $TSconfPrefix = 'mod.web_modules.dmail.';
	// Internal
	var $params=array();
	var $perms_clause='';
	var $pageinfo='';
	var $sys_dmail_uid;
	var $pages_uid;
	var $id;
	var $implodedParams=array();
	var $userTable;		// If set a valid user table is around
	var $sys_language_uid = 0;
	var $allowedTables = array('tt_address','fe_users');
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';

	/**
	 * Initializing global variables
	 *
	 * @return	void		no return values: first initialisation of global variables
	 */
	function init()	{
		$this->MCONF = $GLOBALS['MCONF'];

		parent::init();

		$temp = BackendUtility::getModTSconfig($this->id,'mod.web_modules.dmail');
		$this->params = $temp['properties'];
		$this->implodedParams = BackendUtility::implodeTSParams($this->params);
		if ($this->params['userTable'] && is_array($GLOBALS["TCA"][$this->params['userTable']]))	{
			$this->userTable = $this->params['userTable'];
			$this->allowedTables[] = $this->userTable;
		}
		$this->MOD_MENU['dmail_mode'] = BackendUtility::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize backend user language
		if ($GLOBALS["LANG"]->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3='.$GLOBALS["TYPO3_DB"]->fullQuoteStr($GLOBALS["LANG"]->lang,'static_languages').
					BackendUtility::BEenableFields('sys_language').
					BackendUtility::deleteClause('sys_language').
					BackendUtility::deleteClause('static_languages')
				);
			while($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
			}
			$GLOBALS["TYPO3_DB"]->sql_free_result($res);
		}
			// load contextual help
		$this->cshTable = '_MOD_'.$this->MCONF['name'];
		if ($GLOBALS["BE_USER"]->uc['edit_showFieldHelp']){
			$GLOBALS["LANG"]->loadSingleTableDescription($this->cshTable);
		}
	}

	/**
	 * The main function.
	 *
	 * @return	void		no return value: update the global variable 'content'
	 */
	function main()	{
		$this->CMD = GeneralUtility::_GP('CMD');
		$this->pages_uid = intval(GeneralUtility::_GP('pages_uid'));
		$this->sys_dmail_uid = intval(GeneralUtility::_GP('sys_dmail_uid'));
		$this->pageinfo = BackendUtility::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($GLOBALS["BE_USER"]->user['admin'] && !$this->id))	{
			// Draw the header.
			$this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
			$this->doc->backPath = $GLOBALS["BACK_PATH"];
			$this->doc->setModuleTemplate('EXT:direct_mail/mod3/mod_template.html');
			$this->doc->form='<form action="" method="post" name="'.$this->formname.'" enctype="multipart/form-data">';

			// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid='.$this->sys_dmail_uid.'";
					}
				</script>
			';

			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = '.intval($this->id).';
				</script>
			';

			$markers = array(
				'FLASHMESSAGES' => '',
				'CONTENT' => '',
			);

			$docHeaderButtons = array(
				'PAGEPATH' => $GLOBALS["LANG"]->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
				'SHORTCUT' => '',
				'CSH' => BackendUtility::cshItem($this->cshTable, '', $GLOBALS["BACK_PATH"])
			);
				// shortcut icon
			if ($GLOBALS["BE_USER"]->mayMakeShortcut()) {
				$docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
			}

			$module = $this->pageinfo['module'];
			if (!$module)	{
				$pidrec=BackendUtility::getRecord('pages',intval($this->pageinfo['pid']));
				$module=$pidrec['module'];
			}
					// Render content:
			if ($module == 'dmail') {

				if (GeneralUtility::_GP('cmd') == 'delete') {
					$this->deleteDMail(GeneralUtility::_GP('uid'));
				}

					// Direct mail module
				if ($this->pageinfo['doktype'] == 254 && $this->pageinfo['module'] == 'dmail') {
					$markers['CONTENT'] = '<h1>' . $GLOBALS['LANG']->getLL('header_mailer') . '</h1>'
					. $this->cmd_cronMonitor() . $this->cmd_mailerengine();
				} elseif ($this->id != 0) {
					/** @var $flashMessage FlashMessage */
					$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
						$GLOBALS['LANG']->getLL('dmail_noRegular'),
						$GLOBALS['LANG']->getLL('dmail_newsletters'),
						FlashMessage::WARNING
					);
					$markers['FLASHMESSAGES'] = $flashMessage->render();
				}

			} else {
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$GLOBALS['LANG']->getLL('select_folder'),
					$GLOBALS['LANG']->getLL('header_mailer'),
					FlashMessage::WARNING
				);
				$markers['FLASHMESSAGES'] = $flashMessage->render();
			}

			$this->content = $this->doc->startPage($GLOBALS["LANG"]->getLL('title'));
			$this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());

		} else {
			// If no access or if ID == zero

			$this->doc = GeneralUtility::makeInstance('mediumDoc');
			$this->doc->backPath = $GLOBALS["BACK_PATH"];

			$this->content.=$this->doc->startPage($GLOBALS["LANG"]->getLL('title'));
			$this->content.=$this->doc->header($GLOBALS["LANG"]->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void		no return value: print out the global variable 'content'
	 */
	function printContent()	{
		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * delete existing dmail record
	 *
	 * @param int $uid: record uid to be deleted
	 * @return void
	 */
	function deleteDMail($uid) {
		$table = 'sys_dmail';
		if ($GLOBALS["TCA"][$table]['ctrl']['delete']) {
			$res = $GLOBALS["TYPO3_DB"]->exec_UPDATEquery(
				$table,
				'uid = '.$uid,
				array($GLOBALS["TCA"][$table]['ctrl']['delete'] => 1)
			);
			$GLOBALS["TYPO3_DB"]->sql_free_result($res);
		}
	}

	/**
	 * Monitor the cronjob.
	 *
	 * @return	string		status of the cronjob in HTML Tableformat
	 */
	function cmd_cronMonitor() {
		$content = '';
		$mailerStatus = 0;
		$lastExecutionTime = 0;
		$logContent = "";


			// seconds
		$cronInterval = $GLOBALS["TYPO3_CONF_VARS"]['EXTCONF']['direct_mail']['cronInt'] * 60;
		$lastCronjobShouldBeNewThan = (time() - $cronInterval);

		$filename = PATH_site . 'typo3temp/tx_directmail_dmailer_log.txt';
		if (file_exists($filename)) {
			$logContent = file_get_contents($filename);
			$lastExecutionTime = substr($logContent, 0, 10);
		}

		/*
		 * status:
		 * 	1 = ok
		 * 	0 = check
		 * 	-1 = cron stopped
		 */

			// cron running or error (die function in dmailer_log)
		if (file_exists(PATH_site.'typo3temp/tx_directmail_cron.lock')) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*','sys_dmail_maillog','response_type = 0', 'tstamp DESC');
			$lastSend = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS["TYPO3_DB"]->sql_free_result($res);

			if (($lastSend['tstamp'] < time()) && ($lastSend['tstamp'] > $lastCronjobShouldBeNewThan)) {
					// cron is sending
				$mailerStatus = 1;
			} else {
					// there's lock file but cron is not sending
				$mailerStatus = -1;
			}
			// cron is idle or no cron
		} elseif (strpos($logContent, 'error')) {
				// error in log file
			$mailerStatus = -1;
			$error = substr($logContent, strpos($logContent, 'error') + 7);
		} else if (!strlen($logContent) || ($lastExecutionTime < $lastCronjobShouldBeNewThan)) {
				// cron is not set or not running
			$mailerStatus = 0;
		} else {
				// last run of cron is in the interval
			$mailerStatus = 1;
		}


		$currentDate = ' / '. $GLOBALS["LANG"]->getLL('dmail_mailerengine_current_time') . ' '.BackendUtility::datetime(time()).'<br />';
		$lastRun = '<br />' . $GLOBALS["LANG"]->getLL('dmail_mailerengine_cron_lastrun') . ($lastExecutionTime ? BackendUtility::datetime($lastExecutionTime) : '-') . $currentDate;
		switch ($mailerStatus) {
			case -1:
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$GLOBALS['LANG']->getLL('dmail_mailerengine_cron_warning') . ': ' . ($error ? $error : $GLOBALS['LANG']->getLL('dmail_mailerengine_cron_warning_msg')) . $lastRun,
					$GLOBALS['LANG']->getLL('dmail_mailerengine_cron_status'),
					FlashMessage::ERROR
				);
			break;
			case 0:
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$GLOBALS['LANG']->getLL('dmail_mailerengine_cron_caution') . ': ' . $GLOBALS['LANG']->getLL('dmail_mailerengine_cron_caution_msg') . $lastRun,
					$GLOBALS['LANG']->getLL('dmail_mailerengine_cron_status'),
					FlashMessage::WARNING
				);
			break;
			case 1:
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$GLOBALS['LANG']->getLL('dmail_mailerengine_cron_ok') . ': ' . $GLOBALS['LANG']->getLL('dmail_mailerengine_cron_ok_msg') . $lastRun,
					$GLOBALS['LANG']->getLL('dmail_mailerengine_cron_status'),
					FlashMessage::OK
				);
			break;
		}
		return $flashMessage->render();
	}

	/**
	 * Shows the status of the mailer engine. TODO: Should really only show some entries, or provide a browsing interface.
	 *
	 * @return	string		List of the mailing status
	 */
	function cmd_mailerengine() {
		$invokeMessage = "";

			// enable manual invocation of mailer engine; enabled by default
		$enableTrigger = ! (isset($this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger'] ) && $this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger'] );
		if ($enableTrigger && GeneralUtility::_GP('invokeMailerEngine')) {
			/** @var $flashMessage FlashMessage */
			$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				'<strong>' . $GLOBALS["LANG"]->getLL('dmail_mailerengine_log') . '</strong><br />' . nl2br($this->invokeMEngine()),
				$GLOBALS['LANG']->getLL('dmail_mailerengine_invoked'),
				FlashMessage::INFO
			);
			$invokeMessage = $flashMessage->render();
		}

		// Invoke engine
		if ($enableTrigger) {
			$out = '<p>' . $GLOBALS["LANG"]->getLL('dmail_mailerengine_manual_explain') . '<br /><br />&nbsp;&nbsp;<a class="t3-link" href="index.php?id='.$this->id.'&invokeMailerEngine=1"><strong>' . $GLOBALS["LANG"]->getLL('dmail_mailerengine_invoke_now') . '</strong></a></p>';
			$invokeMessage .= $this->doc->spacer(20);
			$invokeMessage .= $this->doc->section(BackendUtility::cshItem($this->cshTable,'mailerengine_invoke',$GLOBALS["BACK_PATH"]).$GLOBALS["LANG"]->getLL('dmail_mailerengine_manual_invoke'), $out, 1, 1, 0, TRUE);
			$invokeMessage .= $this->doc->spacer(20);
		}

		// Display mailer engine status
		$res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
			'uid,pid,subject,scheduled,scheduled_begin,scheduled_end',
			'sys_dmail',
			'pid='.intval($this->id).
				' AND scheduled>0'.
				BackendUtility::deleteClause('sys_dmail'),
			'',
			'scheduled DESC'
		);

		$out = '<tr class="t3-row-header">
				<td>'.'&nbsp;'.'</td>
				<td><b>'.$GLOBALS["LANG"]->getLL('dmail_mailerengine_subject') . '&nbsp;&nbsp;'.'</b></td>
				<td><b>'.$GLOBALS["LANG"]->getLL('dmail_mailerengine_scheduled') . '&nbsp;&nbsp;'.'</b></td>
				<td><b>'.$GLOBALS["LANG"]->getLL('dmail_mailerengine_delivery_begun') . '&nbsp;&nbsp;'.'</b></td>
				<td><b>'.$GLOBALS["LANG"]->getLL('dmail_mailerengine_delivery_ended') . '&nbsp;&nbsp;'.'</b></td>
				<td style="text-align: center;"><b>'."&nbsp;" . $GLOBALS["LANG"]->getLL('dmail_mailerengine_number_sent') . '&nbsp;'.'</b></td>
				<td style="text-align: center;"><b>'."&nbsp;" . $GLOBALS["LANG"]->getLL('dmail_mailerengine_delete') . '&nbsp;'.'</b></td>
			</tr>';

		while($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))	{
			$countres = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
				'count(*)',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=0'.
					' AND html_sent>0'
				);
			list($count) = $GLOBALS["TYPO3_DB"]->sql_fetch_row($countres);
			$out.='<tr class="db_list_normal">
						<td>'.IconUtility::getSpriteIconForRecord('sys_dmail',$row).'</td>
						<td>'.$this->linkDMail_record(htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['subject'],100)).'&nbsp;&nbsp;',$row['uid']).'</td>
						<td>'.BackendUtility::datetime($row['scheduled']).'&nbsp;&nbsp;'.'</td>
						<td>'.($row['scheduled_begin']?BackendUtility::datetime($row['scheduled_begin']):'').'&nbsp;&nbsp;'.'</td>
						<td>'.($row['scheduled_end']?BackendUtility::datetime($row['scheduled_end']):'').'&nbsp;&nbsp;'.'</td>
						<td style="text-align: center;">'.($count?$count:'&nbsp;').'</td>
						<td style="text-align: center;">'.$this->deleteLink($row['uid']).'</td>
					</tr>';
		}

		$out = $invokeMessage . '<table class="typo3-dblist">'.$out.'</table>';
		return $this->doc->section(BackendUtility::cshItem($this->cshTable,'mailerengine_status',$GLOBALS["BACK_PATH"]).$GLOBALS["LANG"]->getLL('dmail_mailerengine_status'),$out,1,1, 0, TRUE);
	}

	/**
	 * create delete link with trash icon
	 *
	 * @param	int		$uid: uid of the record
	 * @return	string	link with the trash icon
	 */
	function deleteLink($uid) {
		$icon = IconUtility::getSpriteIcon('actions-edit-delete');
		$dmail = BackendUtility::getRecord('sys_dmail', $uid);
		if (!$dmail['scheduled_begin']) {
			return '<a href="index.php?id='.$this->id.'&cmd=delete&uid='.$uid.'">'.$icon.'</a>';
		}
		return "";
	}

	/**
	 * wrapping a string with a link
	 *
	 * @param	string		$str: string to be wrapped
	 * @param	integer		$uid: uid of the record
	 * @return	string		wrapped string as a link
	 */
	function linkDMail_record($str,$uid)	{
		return $str;
		//TODO: Link to detail page for the new queue
		#return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct">'.$str.'</a>';
	}

	/**
	 * invoking the mail engine
	 *
	 * @return	string		log from the mailer class
	 * @see		Dmailer::start
	 * @see		Dmailer::runcron
	 */
	function invokeMEngine()	{
		// TODO: remove htmlmail
		/** @var $htmlmail Dmailer */
		$htmlmail = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Dmailer');
		$htmlmail->nonCron = 1;
		$htmlmail->start();
		$htmlmail->runcron();
		return implode(LF,$htmlmail->logArray);
	}

}

?>