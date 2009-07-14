<?php
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
 * @version 	$Id$
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   67: class tx_directmail_mailer_engine extends t3lib_SCbase
 *   90:     function init()
 *  136:     function main()
 *  214:     function printContent()
 *  224:     function moduleContent()
 *  245:     function cmd_cronMonitor()
 *  326:     function cmd_mailerengine()
 *  396:     function linkDMail_record($str,$uid)
 *  409:     function invokeMEngine()
 *
 * TOTAL FUNCTIONS: 8
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */
require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.dmailer.php');

/**
 * Module Mailer-Engine for tx_directmail extension
 *
 */
class tx_directmail_mailer_engine extends t3lib_SCbase {
	var $extKey = 'direct_mail';
	var $TSconfPrefix = 'mod.web_modules.dmail.';
	// Internal
	var $modList='';
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
		global $LANG,$BACK_PATH,$TCA,$TYPO3_CONF_VARS,$TYPO3_DB;

		$this->MCONF = $GLOBALS['MCONF'];

		parent::init();

		$this->modList = t3lib_BEfunc::getListOfBackendModules(array('dmail'),$this->perms_clause,$BACK_PATH);
		$temp = t3lib_BEfunc::getModTSconfig($this->id,'mod.web_modules.dmail');
		$this->params = $temp['properties'];
		$this->implodedParams = t3lib_BEfunc::implodeTSParams($this->params);
		if ($this->params['userTable'] && is_array($TCA[$this->params['userTable']]))	{
			$this->userTable = $this->params['userTable'];
			t3lib_div::loadTCA($this->userTable);
			$this->allowedTables[] = $this->userTable;
		}
		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize backend user language
		if ($LANG->lang && t3lib_extMgm::isLoaded('static_info_tables')) {
			$res = $TYPO3_DB->exec_SELECTquery(
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3='.$TYPO3_DB->fullQuoteStr($LANG->lang,'static_languages').
					t3lib_BEfunc::BEenableFields('sys_language').
					t3lib_BEfunc::deleteClause('sys_language').
					t3lib_BEfunc::deleteClause('static_languages')
				);
			while($row = $TYPO3_DB->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
			}
		}
			// load contextual help
		$this->cshTable = '_MOD_'.$this->MCONF['name'];
		if ($BE_USER->uc['edit_showFieldHelp']){
			$LANG->loadSingleTableDescription($this->cshTable);
		}

		t3lib_div::loadTCA('sys_dmail');
	}

	/**
	 * The main function.
	 *
	 * @return	void		no return value: update the global variable 'content'
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA,$TYPO3_CONF_VARS;

		$this->CMD = t3lib_div::_GP('CMD');
		$this->pages_uid=t3lib_div::_GP('pages_uid');
		$this->sys_dmail_uid=t3lib_div::_GP('sys_dmail_uid');
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{

			// Draw the header.
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $BACK_PATH;
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

			$headerSection = $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],50);
			
			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->section('',$headerSection,1,0,0,TRUE);
			$this->content.=$this->doc->spacer(5);

			$module = $this->pageinfo['module'];

			if (!$module)	{
				$pidrec=t3lib_BEfunc::getRecord('pages',intval($this->pageinfo['pid']));
				$module=$pidrec['module'];
			}
			if ($module == 'dmail') {
					// Render content:
				$this->content.=$this->doc->section($LANG->getLL('header_mailer').t3lib_BEfunc::cshItem($this->cshTable,'',$BACK_PATH), '', 1, 1, 0 , TRUE);
				$this->moduleContent();
			} else {
				$this->content.=$this->doc->section($LANG->getLL('header_mailer'), $LANG->getLL('select_folder'), 1, 1, 0 , TRUE);
			}

			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}
			$this->content.=$this->doc->spacer(10);

		} else {
			// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
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
	 * shows the module content
	 *
	 * @return	string		The compiled content of the module.
	 */
	function moduleContent() {
		global $TYPO3_CONF_VARS, $LANG;

		if (t3lib_div::_GP('cmd') == 'delete') {
			$this->deleteDMail(t3lib_div::_GP('uid'));
		}
		if ($this->pageinfo['doktype']==254 && $this->pageinfo['module']=='dmail')	{	// Direct mail module
			$theOutput.= $this->cmd_cronMonitor();
			$theOutput.= $this->cmd_mailerengine();
		} elseif ($this->id!=0) {
			$theOutput.= $this->doc->section($LANG->getLL('dmail_newsletters'),'<span class="typo3-red">'.$GLOBALS['LANG']->getLL('dmail_noRegular').'</span>',0,1);
		}

		if ($this->id!=0) {
			$theOutput.=$this->doc->spacer(10);
		}
		$this->content .= $theOutput;
	}

	/**
	 * delete existing dmail record
	 * 
	 * @param int $uid: record uid to be deleted
	 * @return void
	 */
	function deleteDMail($uid) {
		global $TCA, $TYPO3_DB;
		
		$table = 'sys_dmail';
		if ($TCA[$table]['ctrl']['delete']) {
			$TYPO3_DB->exec_UPDATEquery(
				$table,
				'uid = '.$uid,
				array($TCA[$table]['ctrl']['delete'] => 1)
			);
		}
		
		return;
	}
	
	/**
	 * Monitor the cronjob.
	 *
	 * @return	string		status of the cronjob in HTML Tableformat
	 */
	function cmd_cronMonitor(){
		global $TYPO3_CONF_VARS, $TYPO3_DB, $LANG;

		$cronInt = $TYPO3_CONF_VARS['EXTCONF']['direct_mail']['cronInt']*60;	//seconds
		$filename = PATH_site.'typo3temp/tx_directmail_dmailer_log.txt';
		if(file_exists($filename)){
			$fp = fopen($filename,'r');
			if(filesize($filename) > 0 )
				$logContent = fread($fp,filesize($filename));
		}
		$status = 0;

		/*
		 * status:
		 * 	1 = ok
		 * 	0 = check
		 * 	-1 = cron stopped
		 */

		if(file_exists(PATH_site.'typo3temp/tx_directmail_cron.lock')){
			//cron running or error (die function in dmailer_log)
			$res = $TYPO3_DB->exec_SELECTquery('*','sys_dmail_maillog','response_type = 0', 'tstamp DESC');
			$lastSend = $TYPO3_DB->sql_fetch_assoc($res);

			if( ($lastSend['tstamp'] < time()) && ($lastSend['tstamp']> time()-$cronInt) ){
				$status = 1;	//cron is sending
			} else {
				$status = -1;	//there's lock file but cron is not sending
			}
		} else {
			//cron is idle or no cron
			if(strpos($logContent,'error')){
				$status = -1;	//error in log file
				$error = substr($logContent,strpos($logContent,'error')+7);
			} elseif(substr($logContent,0,10) < time()-$cronInt){
				$status = 0;	//cron is not set or not running
			} else {
				$status = 1;	//last run of cron is in the interval
			}
		}
		if($fp)
			fclose($fp);

		switch ($status) {
			case -1:
				$out = '<tr>
							<td bgcolor="red" align="center" width="60"><b>'.$LANG->getLL('dmail_mailerengine_cron_warning').'</b></td>
							<td>'.($error?$error:$LANG->getLL('dmail_mailerengine_cron_warning_msg')).'</td>
						</tr>';
				break;
			case 0 :
				$out = '<tr>
							<td bgcolor="yellow" align="center" width="60"><b>'.$LANG->getLL('dmail_mailerengine_cron_caution').'</b></td>
							<td>'.$LANG->getLL('dmail_mailerengine_cron_caution_msg').'</td>
						</tr>';
				break;
			case 1:
				$out = '<tr>
							<td bgcolor="#00FF00" align="center" width="60"><b>'.$LANG->getLL('dmail_mailerengine_cron_ok').'</b></td>
							<td>'.$LANG->getLL('dmail_mailerengine_cron_ok_msg').'</td>
						</tr>';
				break;
			default:
				break;
		}
		$out.= '<tr>
					<td>&nbsp;</td>
					<td>'.$LANG->getLL('dmail_mailerengine_cron_lastrun').($logContent?t3lib_BEfunc::datetime(substr($logContent,0,10)):'-').'</td>
				</tr>';

		$out = '<table border="0" cellpadding="3" cellspacing="0">'.$out.'</table>';

		$theOutput = $this->doc->section($LANG->getLL('dmail_mailerengine_cron_status'),$out,1,1);
		return $theOutput;
	}

	/**
	 * Shows the status of the mailer engine. TODO: Should really only show some entries, or provide a browsing interface.
	 *
	 * @return	string		List of the mailing status
	 */
	function cmd_mailerengine() {
		global $LANG, $TYPO3_DB, $BACK_PATH;

			// enable manual invocation of mailer engine; enabled by default
		$enableTrigger = ! ( isset( $this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger'] ) && $this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger'] );

		if ( $enableTrigger && t3lib_div::_GP('invokeMailerEngine') )	{
			$out='<strong>' . $LANG->getLL('dmail_mailerengine_log') . '</strong><br /><font color=#666666>' . nl2br($this->invokeMEngine()) . '</font><br />';
			$theOutput.= $this->doc->section($LANG->getLL('dmail_mailerengine_invoked'), $out, 1);
			$theOutput.= $this->doc->spacer(20);
		}

		// Display mailer engine status
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,pid,subject,scheduled,scheduled_begin,scheduled_end',
			'sys_dmail',
			'pid='.intval($this->id).
				' AND scheduled>0'.
				t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			'scheduled DESC'
			);
		$out='';
		$out.='<tr>
						<td bgColor="'.$this->doc->bgColor5.'">'.'&nbsp;'.'</td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.$LANG->getLL('dmail_mailerengine_subject' . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.$LANG->getLL('dmail_mailerengine_scheduled' . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.$LANG->getLL('dmail_mailerengine_delivery_begun' . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.$LANG->getLL('dmail_mailerengine_delivery_ended' . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'."&nbsp;" . $LANG->getLL('dmail_mailerengine_number_sent') . '&nbsp;'.'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'."&nbsp;" . $LANG->getLL('dmail_mailerengine_delete' . '&nbsp;').'</b></td>
					</tr>';

		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$countres = $TYPO3_DB->exec_SELECTquery(
				'count(*)',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=0'
				);
			list($count) = $TYPO3_DB->sql_fetch_row($countres);
			$out.='<tr>
						<td>'.t3lib_iconWorks::getIconImage('sys_dmail',$row, $BACK_PATH, 'width="18" height="16" style="vertical-align: top;"').'</td>
						<td>'.$this->linkDMail_record(t3lib_div::fixed_lgd($row['subject'],100).'&nbsp;&nbsp;',$row['uid']).'</td>
						<td>'.t3lib_BEfunc::datetime($row['scheduled']).'&nbsp;&nbsp;'.'</td>
						<td>'.($row['scheduled_begin']?t3lib_BEfunc::datetime($row['scheduled_begin']):'').'&nbsp;&nbsp;'.'</td>
						<td>'.($row['scheduled_end']?t3lib_BEfunc::datetime($row['scheduled_end']):'').'&nbsp;&nbsp;'.'</td>
						<td align=right>'.fw($count?$count:'&nbsp;').'</td>
						<td align=center>'.$this->deleteLink($row['uid']).'</td>
					</tr>';
		}

		$out='<table border="0" cellpadding="0" cellspacing="0">'.$out.'</table>';
		$out.='<br />'. $LANG->getLL('dmail_mailerengine_current_time') . ' '.t3lib_BEfunc::datetime(time()).'<br />';
		$theOutput.= $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'mailerengine_status',$BACK_PATH).$LANG->getLL('dmail_mailerengine_status'),$out,1,1, 0, TRUE);

			// Invoke engine
		if ( $enableTrigger )	{
			$out=$LANG->getLL('dmail_mailerengine_manual_explain') . '&nbsp;&nbsp;<a href="index.php?id='.$this->id.'&invokeMailerEngine=1"><strong>' . $LANG->getLL('dmail_mailerengine_invoke_now') . '</strong></a>';
			$theOutput.= $this->doc->spacer(20);
			$theOutput.= $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'mailerengine_invoke',$BACK_PATH).$LANG->getLL('dmail_mailerengine_manual_invoke'), $out, 1, 1, 0, TRUE);
		}
		return $theOutput;
	}

	/**
	 * create delete link with trash icon
	 * 
	 * @param	int		$uid: uid of the record
	 * @return	string	link with the trash icon
	 */
	function deleteLink($uid) {
		global $BACK_PATH;
		
		$icon = '<img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/delete_record.gif').' />';
		$dmail = t3lib_BEfunc::getRecord('sys_dmail', $uid); 
		if (!$dmail['scheduled_begin']) {
			return '<a href="index.php?id='.$this->id.'&cmd=delete&uid='.$uid.'">'.$icon.'</a>';
		}
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
	 * @see		dmailer::start
	 * @see		dmailer::runcron
	 */
	function invokeMEngine()	{
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->nonCron = 1;
		$htmlmail->start();
		$htmlmail->runcron();
		return implode(chr(10),$htmlmail->logArray);
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod5/class.tx_directmail_mailer_engine.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod5/class.tx_directmail_mailer_engine.php']);
}

?>