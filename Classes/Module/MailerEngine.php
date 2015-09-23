<?php
namespace DirectMailTeam\DirectMail\Module;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use DirectMailTeam\DirectMail\DirectMailUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;

/**
 * Module Mailer-Engine for tx_directmail extension
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail

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
	// If set a valid user table is around
	var $userTable;
	var $sys_language_uid = 0;
	var $allowedTables = array('tt_address','fe_users');
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';

	/**
	 * IconFactory for skinning
	 * @var \TYPO3\CMS\Core\Imaging\IconFactory
	 */
	protected $iconFactory;

	/**
	 * Initializing global variables
	 *
	 * @return	void
	 */
	function init() {
		$this->MCONF = $GLOBALS['MCONF'];

		parent::init();

		// initialize IconFactory
		$this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);

		$temp = BackendUtility::getModTSconfig($this->id,'mod.web_modules.dmail');
		if (!is_array($temp['properties'])) {
			$temp['properties'] = array();
		}
		$this->params = $temp['properties'];
		$this->implodedParams = DirectMailUtility::implodeTSParams($this->params);
		if ($this->params['userTable'] && is_array($GLOBALS["TCA"][$this->params['userTable']]))	{
			$this->userTable = $this->params['userTable'];
			$this->allowedTables[] = $this->userTable;
		}
		$this->MOD_MENU['dmail_mode'] = BackendUtility::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize backend user language
		if ($this->getLanguageService()->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
			$res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3=' . $GLOBALS["TYPO3_DB"]->fullQuoteStr($this->getLanguageService()->lang,'static_languages') .
					BackendUtility::BEenableFields('sys_language') .
					BackendUtility::deleteClause('sys_language') .
					BackendUtility::deleteClause('static_languages')
				);
			while(($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
				$this->sys_language_uid = $row['uid'];
			}
			$GLOBALS["TYPO3_DB"]->sql_free_result($res);
		}
			// load contextual help
		$this->cshTable = '_MOD_' . $this->MCONF['name'];
		if ($GLOBALS["BE_USER"]->uc['edit_showFieldHelp']){
			$this->getLanguageService()->loadSingleTableDescription($this->cshTable);
		}
	}

	/**
	 * The main function.
	 *
	 * @return	void
	 */
	function main() {
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
			$this->doc->form='<form action="" method="post" name="' . $this->formname . '" enctype="multipart/form-data">';

			// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid=' . $this->sys_dmail_uid . '";
					}
				</script>
			';

			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = ' . intval($this->id) . ';
				</script>
			';

			$markers = array(
				'FLASHMESSAGES' => '',
				'CONTENT' => '',
			);

			$docHeaderButtons = array(
				'PAGEPATH' => $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.php:labels.path') . ': ' . GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
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
					$markers['CONTENT'] = '<h1>' . $this->getLanguageService()->getLL('header_mailer') . '</h1>' .
					$this->cmd_cronMonitor() . $this->cmd_mailerengine();
				} elseif ($this->id != 0) {
					/* @var $flashMessage FlashMessage */
					$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
						$this->getLanguageService()->getLL('dmail_noRegular'),
						$this->getLanguageService()->getLL('dmail_newsletters'),
						FlashMessage::WARNING
					);
					$markers['FLASHMESSAGES'] = $flashMessage->render();
				}

			} else {
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$this->getLanguageService()->getLL('select_folder'),
					$this->getLanguageService()->getLL('header_mailer'),
					FlashMessage::WARNING
				);
				$markers['FLASHMESSAGES'] = $flashMessage->render();
			}

			$this->content = $this->doc->startPage($this->getLanguageService()->getLL('title'));
			$this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());

		} else {
			// If no access or if ID == zero

			$this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\MediumDocumentTemplate');
			$this->doc->backPath = $GLOBALS["BACK_PATH"];

			$this->content.=$this->doc->startPage($this->getLanguageService()->getLL('title'));
			$this->content.=$this->doc->header($this->getLanguageService()->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent() {
		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Delete existing dmail record
	 *
	 * @param int $uid Record uid to be deleted
	 *
	 * @return void
	 */
	function deleteDMail($uid) {
		$table = 'sys_dmail';
		if ($GLOBALS["TCA"][$table]['ctrl']['delete']) {
			$res = $GLOBALS["TYPO3_DB"]->exec_UPDATEquery(
				$table,
				'uid = ' . $uid,
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
		if (file_exists(PATH_site . 'typo3temp/tx_directmail_cron.lock')) {
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
		} elseif (!strlen($logContent) || ($lastExecutionTime < $lastCronjobShouldBeNewThan)) {
				// cron is not set or not running
			$mailerStatus = 0;
		} else {
				// last run of cron is in the interval
			$mailerStatus = 1;
		}


		$currentDate = ' / ' . $this->getLanguageService()->getLL('dmail_mailerengine_current_time') . ' ' . BackendUtility::datetime(time()) . '<br />';
		$lastRun = '<br />' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_lastrun') . ($lastExecutionTime ? BackendUtility::datetime($lastExecutionTime) : '-') . $currentDate;
		switch ($mailerStatus) {
			case -1:
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$this->getLanguageService()->getLL('dmail_mailerengine_cron_warning') . ': ' . ($error ? $error : $this->getLanguageService()->getLL('dmail_mailerengine_cron_warning_msg')) . $lastRun,
					$this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
					FlashMessage::ERROR
				);
				break;
			case 0:
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$this->getLanguageService()->getLL('dmail_mailerengine_cron_caution') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_caution_msg') . $lastRun,
					$this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
					FlashMessage::WARNING
				);
				break;
			case 1:
				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
					$this->getLanguageService()->getLL('dmail_mailerengine_cron_ok') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_ok_msg') . $lastRun,
					$this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
					FlashMessage::OK
				);
				break;
			default:
		}
		return $flashMessage->render();
	}

	/**
	 * Shows the status of the mailer engine.
	 * TODO: Should really only show some entries, or provide a browsing interface.
	 *
	 * @return	string		List of the mailing status
	 */
	function cmd_mailerengine() {
		$invokeMessage = "";

			// enable manual invocation of mailer engine; enabled by default
		$enableTrigger = ! (isset($this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger'] ) && $this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger'] );
		if ($enableTrigger && GeneralUtility::_GP('invokeMailerEngine')) {
			/* @var $flashMessage FlashMessage */
			$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
				'<strong>' . $this->getLanguageService()->getLL('dmail_mailerengine_log') . '</strong><br />' . nl2br($this->invokeMEngine()),
				$this->getLanguageService()->getLL('dmail_mailerengine_invoked'),
				FlashMessage::INFO
			);
			$invokeMessage = $flashMessage->render();
		}

		// Invoke engine
		if ($enableTrigger) {
			$out = '<p>' . $this->getLanguageService()->getLL('dmail_mailerengine_manual_explain') . '<br /><br /><a class="t3-link" href="' . BackendUtility::getModuleUrl('txdirectmailM1_txdirectmailM5') . '&id=' . $this->id . '&invokeMailerEngine=1"><strong>' . $this->getLanguageService()->getLL('dmail_mailerengine_invoke_now') . '</strong></a></p>';
			$invokeMessage .= $this->doc->spacer(20);
			$invokeMessage .= $this->doc->section(BackendUtility::cshItem($this->cshTable,'mailerengine_invoke',$GLOBALS["BACK_PATH"]) . $this->getLanguageService()->getLL('dmail_mailerengine_manual_invoke'), $out, 1, 1, 0, TRUE);
			$invokeMessage .= $this->doc->spacer(20);
		}

		// Display mailer engine status
		$res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
			'uid,pid,subject,scheduled,scheduled_begin,scheduled_end',
			'sys_dmail',
			'pid=' . intval($this->id) .
				' AND scheduled>0' .
				BackendUtility::deleteClause('sys_dmail'),
			'',
			'scheduled DESC'
		);

		$out = '<tr class="t3-row-header">
				<td>&nbsp;</td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_subject') . '&nbsp;&nbsp;</b></td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_scheduled') . '&nbsp;&nbsp;</b></td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_delivery_begun') . '&nbsp;&nbsp;</b></td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_delivery_ended') . '&nbsp;&nbsp;</b></td>
				<td style="text-align: center;"><b>&nbsp;' . $this->getLanguageService()->getLL('dmail_mailerengine_number_sent') . '&nbsp;</b></td>
				<td style="text-align: center;"><b>&nbsp;' . $this->getLanguageService()->getLL('dmail_mailerengine_delete') . '&nbsp;</b></td>
			</tr>';

		while(($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
			$countres = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
				'count(*)',
				'sys_dmail_maillog',
				'mid=' . intval($row['uid']) .
					' AND response_type=0' .
					' AND html_sent>0'
				);
			list($count) = $GLOBALS["TYPO3_DB"]->sql_fetch_row($countres);
			$out .='<tr class="db_list_normal">
						<td>' . $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render() . '</td>
						<td>' . $this->linkDMail_record(htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['subject'],100)) . '&nbsp;&nbsp;',$row['uid']) . '</td>
						<td>' . BackendUtility::datetime($row['scheduled']) . '&nbsp;&nbsp;</td>
						<td>' . ($row['scheduled_begin']?BackendUtility::datetime($row['scheduled_begin']):'') . '&nbsp;&nbsp;</td>
						<td>' . ($row['scheduled_end']?BackendUtility::datetime($row['scheduled_end']):'') . '&nbsp;&nbsp;</td>
						<td style="text-align: center;">' . ($count?$count:'&nbsp;') . '</td>
						<td style="text-align: center;">' . $this->deleteLink($row['uid']) . '</td>
					</tr>';
		}

		$out = $invokeMessage . '<table class="table table-striped table-hover">' . $out . '</table>';
		return $this->doc->section(BackendUtility::cshItem($this->cshTable,'mailerengine_status',$GLOBALS["BACK_PATH"]) . $this->getLanguageService()->getLL('dmail_mailerengine_status'),$out,1,1, 0, TRUE);
	}

	/**
	 * Create delete link with trash icon
	 *
	 * @param int $uid Uid of the record
	 *
	 * @return string Link with the trash icon
	 */
	function deleteLink($uid) {
		$icon = $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL);
		$dmail = BackendUtility::getRecord('sys_dmail', $uid);
		if (!$dmail['scheduled_begin']) {
			return '<a href="' . BackendUtility::getModuleUrl('txdirectmailM1_txdirectmailM5') . '&id=' . $this->id . '&cmd=delete&uid=' . $uid . '">' . $icon . '</a>';
		}
		return "";
	}

	/**
	 * Wrapping a string with a link
	 *
	 * @param string $str String to be wrapped
	 * @param int $uid Uid of the record
	 *
	 * @return string wrapped string as a link
	 */
	function linkDMail_record($str,$uid) {
		return $str;
		//TODO: Link to detail page for the new queue
		#return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct">'.$str.'</a>';
	}

	/**
	 * Invoking the mail engine
	 *
	 * @return	string Log from the mailer class
	 * @see		Dmailer::start
	 * @see		Dmailer::runcron
	 */
	function invokeMEngine() {
		// TODO: remove htmlmail
		/* @var $htmlmail \DirectMailTeam\DirectMail\Dmailer */
		$htmlmail = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Dmailer');
		$htmlmail->nonCron = 1;
		$htmlmail->start();
		$htmlmail->runcron();
		return implode(LF,$htmlmail->logArray);
	}

	/**
	 * Returns LanguageService
	 *
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}

}

?>
