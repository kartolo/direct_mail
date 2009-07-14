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
 * @version		$Id$
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   80: class tx_directmail_statistics extends t3lib_SCbase
 *  107:     function init()
 *  149:     function printContent()
 *  159:     function main()
 *  243:     function moduleContent()
 *  263:     function mailModule_main()
 *  309:     function cmd_displayPageInfo()
 *  389:     function linkDMail_record($str,$uid)
 *  399:     function cmd_stats($row)
 * 1043:     function disableRecipients($arr,$table)
 * 1069:     function makeStatTempTableContent($mrow)
 * 1149:     function storeRecRec($recRec)
 * 1191:     function getQueryRows($queryArray,$key_field)
 * 1220:     function showWithPercent($pieces,$total)
 * 1239:     function formatTable($tableLines,$cellParams,$header,$cellcmd=array(),$tableParams='border="0" cellpadding="2" cellspacing="3"')
 * 1269:     function fetchRecordsListValues($listArr,$table,$fields='uid,name,email')
 * 1298:     function getRecordList($listArr,$table,$dim=0,$editLinkFlag=1)
 * 1338:     function setURLs($row)
 * 1378:     function getUrlBase($domainUid)
 * 1408:     function directMail_compactView($row)
 * 1463:     function fName($name)
 *
 * TOTAL FUNCTIONS: 20
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_page.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_static.php');

/**
 * Module Statistics of tx_directmail extension
 *
 */
class tx_directmail_statistics extends t3lib_SCbase {
	var $extKey = 'direct_mail';
	var $TSconfPrefix = 'mod.web_modules.dmail.';
	var $fieldList='uid,name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';
	// Internal
	var $modList='';
	var $params=array();
	var $perms_clause='';
	var $pageinfo='';
	var $sys_dmail_uid;
	var $CMD;
	var $pages_uid;
	var $id;
	var $urlbase;
	var $noView;
	var $url_plain;
	var $url_html;
	var $sys_language_uid = 0;
	var $allowedTables = array('tt_address','fe_users');
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';

	/**
	 * first initialization of global variables
	 *
	 * @return	void		no return values: initialize global variables
	 */
	function init()	{
		global $LANG,$BACK_PATH,$TCA,$TYPO3_CONF_VARS,$TYPO3_DB;

		$this->MCONF = $GLOBALS['MCONF'];

		parent::init();

		$this->modList = t3lib_BEfunc::getListOfBackendModules(array('dmail'),$this->perms_clause,$BACK_PATH);
		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize the page selector
		$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		$this->sys_page->init(true);

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
			$LANG->loadSingleTableDescription(q);
		}

		t3lib_div::loadTCA('sys_dmail');
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void		no return values: print out the global variable 'content'
	 */
	function printContent()	{
		$this->content.=$this->doc->endPage();
		echo $this->content;
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

			// Add CSS
			$this->doc->inDocStyles = '
					a.bubble {position:relative; z-index:24; color:#000; text-decoration:none}
					a.bubble:hover {z-index:25; background-color: #e6e8ea;}
					a.bubble span.help {display: none;}
					a.bubble:hover span.help {display:block; position:absolute; top:2em; left:2em; width:25em; border:1px solid #0cf; background-color:#cff; padding: 2px;}
					';

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

			$module = $this->pageinfo['module'];
			if (!$module)	{
				$pidrec=t3lib_BEfunc::getRecord('pages',intval($this->pageinfo['pid']));
				$module=$pidrec['module'];
			}
			
			$headerSection = $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],50);
			
			if ($module == 'dmail') {
					// Render content:

				$this->content.=$this->doc->startPage($LANG->getLL('stats_overview_header'));
				$this->content.=$this->doc->section('',$headerSection,1,0,0,TRUE);
				$this->content.=$this->doc->section($LANG->getLL('stats_overview_header').t3lib_BEfunc::cshItem($this->cshTable,'',$BACK_PATH), '', 1, 1, 0 , TRUE);
				$this->moduleContent();
			} else {
				$this->content.=$this->doc->startPage($LANG->getLL('stats_overview_header'));
				$this->content.=$this->doc->section('',$headerSection,1,0,0,TRUE);
				$this->content.=$this->doc->section($LANG->getLL('header_stat'), $LANG->getLL('select_folder'), 1, 1, 0 , TRUE);
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
	 * compiled content of the module
	 *
	 * @return	string		The compiled content of the module.
	 */
	function moduleContent() {
		global $TYPO3_CONF_VARS, $LANG;

		if ($this->pageinfo['doktype']==254 && $this->pageinfo['module']=='dmail')	{	// Direct mail module
			$theOutput.= $this->mailModule_main();
		} elseif ($this->id!=0) {
			$theOutput.= $this->doc->section($LANG->getLL('dmail_newsletters'),'<span class="typo3-red">'.$GLOBALS['LANG']->getLL('dmail_noRegular').'</span>',0,1);
		}

		if ($this->id!=0) {
			$theOutput.=$this->doc->spacer(10);
		}
		$this->content .= $theOutput;
	}

	/**
	 * Function mailModule main()
	 *
	 * @return	string		the compiled content of the module
	 */
	function mailModule_main()	{
		global $LANG, $TYPO3_DB, $TYPO3_CONF_VARS;

		if (!$this->sys_dmail_uid)	{
			$theOutput.= $this->cmd_displayPageInfo();
		} else {
				// Here the single dmail record is shown.
			$this->sys_dmail_uid = intval($this->sys_dmail_uid);
			$res = $TYPO3_DB->exec_SELECTquery(
				'*',
				'sys_dmail',
				'pid='.intval($this->id).
					' AND uid='.intval($this->sys_dmail_uid).
					t3lib_BEfunc::deleteClause('sys_dmail')
				);

			$this->noView = 0;

			if ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
					// Set URL data for commands
				$this->setURLs($row);

					// COMMAND:
				switch($this->CMD) {
					case 'stats':
						$theOutput .= $this->cmd_stats($row);
					break;
				default:
						// Hook for handling of custom direct mail commands:
					if (is_array($TYPO3_CONF_VARS['EXT']['directmail']['handledirectmailcmd-'.$this->CMD])) {
						foreach($TYPO3_CONF_VARS['EXT']['directmail']['handledirectmailcmd-'.$this->CMD] as $_funcRef) {
							$_params = array('pObj' => &$this);
							$theOutput .= t3lib_div::callUserFunction($_funcRef,$_params,$this);
						}
					}
				}
			}
		}
		return $theOutput;
	}

	/**
	 * shows the info of a page
	 *
	 * @return	string		The infopage of the sent newsletters
	 */
	function cmd_displayPageInfo()	{
		global $TCA, $LANG, $TYPO3_DB, $BACK_PATH;

			// Here the dmail list is rendered:
		$res = $TYPO3_DB->exec_SELECTquery(
			'*',
			'sys_dmail',
			'pid='.intval($this->id).
				' AND type=0'.
				' AND issent = 1'.
				t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			'scheduled DESC, scheduled_begin DESC'
			);

		if ($TYPO3_DB->sql_num_rows($res))	{
			$onClick = ' onClick="return confirm('.$LANG->JScharCode(sprintf($LANG->getLL('nl_l_warning'),$TYPO3_DB->sql_num_rows($res))).');"';
		} else {
			$onClick = '';
		}
		$page = t3lib_BEfunc::getRecord('pages',$this->pages_uid);
		$pageTitle = t3lib_iconWorks::getIconImage('pages',array(),$BACK_PATH,'style="vertical-align: top;"').$page['title'];
		$out="";

		if ($TYPO3_DB->sql_num_rows($res))	{
			$out.='<table border="0" cellpadding="0" cellspacing="0">';
				$out.='<tr>
					<td bgColor="'.$this->doc->bgColor5.'">'.fw('&nbsp;').'</td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('stats_overview_subject').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('stats_overview_scheduled').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('stats_overview_delivery_begun').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('stats_overview_delivery_ended').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('stats_overview_total_sent').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('stats_overview_status').'&nbsp;&nbsp;').'</b></td>
				</tr>';
			while($row = $TYPO3_DB->sql_fetch_assoc($res))	{

				$countRes = $TYPO3_DB->exec_SELECTquery(
					'count(*)',
					'sys_dmail_maillog',
					'mid = '.$row['uid'].' AND response_type=0'
				);
				list($count) = $TYPO3_DB->sql_fetch_row($countRes);

				$sent='';
				if(!empty($row['scheduled_begin'])){
					if(!empty($row['scheduled_end']))
						$sent = fw($LANG->getLL('stats_overview_sent'));
					else
						$sent = fw($LANG->getLL('stats_overview_sending'));
				} else {
					$sent = fw($LANG->getLL('stats_overview_queuing'));
				}

				$out.='<tr>
					<td>'.t3lib_iconWorks::getIconImage('sys_dmail',$row, $BACK_PATH, 'width="18" height="16" style="vertical-align: top;"').'</td>
					<td>'.$this->linkDMail_record(fw(t3lib_div::fixed_lgd($row['subject'],30).'  '),$row['uid']).'&nbsp;&nbsp;</td>
					<td>'.fw(t3lib_BEfunc::datetime($row["scheduled"]).'&nbsp;&nbsp;').'</td>
					<td>'.fw($row["scheduled_begin"]?t3lib_BEfunc::datetime($row["scheduled_begin"]):'&nbsp;').'&nbsp;&nbsp;</td>
					<td>'.fw($row["scheduled_end"]?t3lib_BEfunc::datetime($row["scheduled_end"]):'&nbsp;').'&nbsp;&nbsp;</td>
					<td>'.fw($count?$count:'&nbsp;').'</td>
					<td>'.$sent.'&nbsp;&nbsp;</td>
				</tr>';
			}
			$out.='</table>';
		}

		$theOutput.= $this->doc->section($LANG->getLL('stats_overview_choose'), $out , 1, 1, 0, TRUE);
		$theOutput.= $this->doc->spacer(20);

		return $theOutput;
	}

	/**
	 * wrap a string with a link
	 *
	 * @param	string		$str: string to be wrapped with a link
	 * @param	integer		$uid: record uid to be link
	 * @return	string		wrapped string as a link
	 */
	function linkDMail_record($str,$uid)	{
		return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct&CMD=stats">'.$str.'</a>';
	}

	/**
	 * get statistics from DB and compile them.
	 *
	 * @param	array		$row: DB record
	 * @return	string		statistics of a mail
	 */
	function cmd_stats($row)	{
		global $LANG, $BACK_PATH, $TYPO3_DB, $TBE_TEMPLATE;

		if (t3lib_div::_GP("recalcCache"))	{
			$this->makeStatTempTableContent($row);
		}
		$thisurl = 'index.php?id='.$this->id.'&sys_dmail_uid='.$row['uid'].'&CMD='.$this->CMD.'&recalcCache=1';
		$output.=t3lib_iconWorks::getIconImage('sys_dmail',$row,$BACK_PATH,'style="vertical-align: top;"').$row['subject'].'<br />';
		$output.=$this->directMail_compactView($row);

			// *****************************
			// Mail responses, general:
			// *****************************

		$mailingId = intval($row['uid']);
		$queryArray = array('response_type,count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId, 'response_type');
		$table = $this->getQueryRows($queryArray, 'response_type');

			// Plaintext/HTML
		$queryArray = array('html_sent,count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=0', 'html_sent');
		$table2 = $this->getQueryRows($queryArray, 'html_sent');

			// Unique responses, html
		$res = $TYPO3_DB->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=1', 'rid,rtbl', 'counter');
		$unique_html_responses = $TYPO3_DB->sql_num_rows($res);

			// Unique responses, Plain
		$res = $TYPO3_DB->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=2', 'rid,rtbl', 'counter');
		$unique_plain_responses = $TYPO3_DB->sql_num_rows($res);

			// Unique responses, pings
		$res = $TYPO3_DB->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=-1', 'rid,rtbl', 'counter');
		$unique_ping_responses = $TYPO3_DB->sql_num_rows($res);

		$tblLines = array();
		$tblLines[]=array('',$LANG->getLL('stats_total'),$LANG->getLL('stats_HTML'),$LANG->getLL('stats_plaintext'));

		$sent_total = intval($table['0']['counter']);
		$sent_html = intval($table2['3']['counter']+$table2['1']['counter']);
		$sent_plain = intval($table2['2']['counter']);
		$tblLines[]=array($LANG->getLL('stats_mails_sent'),$sent_total,$sent_html,$sent_plain);
		$tblLines[]=array($LANG->getLL('stats_mails_returned'),$this->showWithPercent($table['-127']['counter'],$sent_total));
		$tblLines[]=array($LANG->getLL('stats_HTML_mails_viewed'),'',$this->showWithPercent($unique_ping_responses,$sent_html));
		$tblLines[]=array($LANG->getLL('stats_unique_responses'),$this->showWithPercent($unique_html_responses+$unique_plain_responses,$sent_total),$this->showWithPercent($unique_html_responses,$sent_html),$this->showWithPercent($unique_plain_responses,$sent_plain?$sent_plain:$sent_html));

		$output.='<br /><strong>' . $LANG->getLL('stats_general_information') . '</strong>';
		$output.=tx_directmail_static::formatTable($tblLines,array('nowrap','nowrap align="right"','nowrap align="right"','nowrap align="right"'),1);

			// ******************
			// Links:
			// ******************

			// initialize $urlCounter
		$urlCounter = array(
			'total' => array(),
			'plain' => array(),
			'html' => array(),
		);
			// Most popular links, html:
		$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=1', 'url_id', 'counter');
		$htmlUrlsTable=$this->getQueryRows($queryArray,'url_id');

			// Most popular links, plain:
		$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=2', 'url_id', 'counter');
		$plainUrlsTable=$this->getQueryRows($queryArray,'url_id');


		// Find urls:
		$temp_unpackedMail = unserialize($row['mailContent']);
		// this array will include a unique list of all URLs that are used in the mailing
		$urlArr = array();

		$urlMd5Map = array();
		if (is_array($temp_unpackedMail['html']['hrefs'])) {
			foreach ($temp_unpackedMail['html']['hrefs'] as $k => $v) {
				$urlArr[$k] = html_entity_decode($v['absRef']);	// convert &amp; of query params back
				$urlMd5Map[md5($v['absRef'])] = $k;
			}
		}
		if (is_array($temp_unpackedMail['plain']['link_ids'])) {
			foreach ($temp_unpackedMail['plain']['link_ids'] as $k => $v) {
				$urlArr[intval(-$k)] = $v;
			}
		}

		// Traverse plain urls:
		$plainUrlsTable_mapped = array();
		foreach ($plainUrlsTable as $id => $c) {
			$url = $urlArr[intval($id)];
			if (isset($urlMd5Map[md5($url)])) {
				$plainUrlsTable_mapped[$urlMd5Map[md5($url)]] = $c;
			} else {
				$plainUrlsTable_mapped[$id] = $c;
			}
		}

		// Traverse html urls:
		$urlCounter['html'] = array();
		foreach ($htmlUrlsTable as $id => $c) {
			$urlCounter['html'][$id]['counter'] = $urlCounter['total'][$id]['counter'] = $c['counter'];
		}

		// Traverse plain urls:
		$urlCounter['plain'] = array();
		foreach ($plainUrlstable_mapped as $id => $c) {
			// Look up plain url in html urls
			$htmlLinkFound = FALSE;
			foreach ($urlCounter['html'] as $htmlId => $htmlLink) {
				if ($urlArr[$id] == $urlArr[$htmlId]) {
					$urlCounter['html'][$htmlId]['plainId'] = $id;
					$urlCounter['html'][$htmlId]['plainCounter'] = $c['counter'];
					$urlCounter['total'][$htmlId]['counter'] = $urlCounter['total'][$htmlId]['counter'] + $c['counter'];
					$htmlLinkFound = TRUE;
					break;
				}
			}
			if (!$htmlLinkFound) {
				$urlCounter['plain'][$id]['counter'] = $c['counter'];
				$urlCounter['total'][$id]['counter'] = $urlCounter['total'][$id]['counter'] + $c['counter'];
			}
		}

		$tblLines=array();
		$tblLines[]=array('',$LANG->getLL('stats_total'),$LANG->getLL('stats_HTML'),$LANG->getLL('stats_plaintext'));
		$tblLines[]=array($LANG->getLL('stats_total_responses'),$table['1']['counter']+$table['2']['counter'],$table['1']['counter']?$table['1']['counter']:'0',$table['2']['counter']?$table['2']['counter']:'0');
		$tblLines[]=array($LANG->getLL('stats_unique_responses'),$this->showWithPercent($unique_html_responses+$unique_plain_responses,$sent_total), $this->showWithPercent($unique_html_responses,$sent_html), $this->showWithPercent($unique_plain_responses,$sent_plain?$sent_plain:$sent_html));
		$tblLines[]=array($LANG->getLL('stats_links_clicked_per_respondent'),
			($unique_html_responses+$unique_plain_responses ? number_format(($table['1']['counter']+$table['2']['counter'])/($unique_html_responses+$unique_plain_responses),2) : '-'),
			($unique_html_responses  ? number_format(($table['1']['counter'])/($unique_html_responses),2)  : '-'),
			($unique_plain_responses ? number_format(($table['2']['counter'])/($unique_plain_responses),2) : '-')
		);

		$output.='<br /><strong>' . $LANG->getLL('stats_response') . '</strong>';
		$output.=tx_directmail_static::formatTable($tblLines,array('nowrap','nowrap align="right"','nowrap align="right"','nowrap align="right"'),1,array(0,0,0,0));

		arsort($urlCounter['total']);
		arsort($urlCounter['html']);
		arsort($urlCounter['plain']);
		reset($urlCounter['total']);

		$tblLines = array();
		$tblLines[] = array('',$LANG->getLL('stats_HTML_link_nr'),$LANG->getLL('stats_plaintext_link_nr'),$LANG->getLL('stats_total'),$LANG->getLL('stats_HTML'),$LANG->getLL('stats_plaintext'),'');

			// HTML mails
		if (t3lib_div::inList('2,3', $row['sendOptions'])) {
			$HTMLContent = base64_decode($temp_unpackedMail['html']['content']);

			$HTMLLinks = array();
			foreach ($temp_unpackedMail['html']['hrefs'] as $jumpurlId => $data) {
				$HTMLlinks[$jumpurlId] = array(
					'url'   => $data['ref'],
					'label' => ''
				);
			}

				// get body
			$tmp = explode('<body', $HTMLContent);
			$bodyPart = explode('<', $tmp[1]);

				// load all <a href="*" parts into $tempHref array, in a 2-dimensional array
				// where the lower level of the array contains two values, the URL and the unique ID (see $urlArr)
			foreach ($bodyPart as $k => $str) {
				if (eregi('a.href', $str)) {
					$tagAttr = t3lib_div::get_tag_attributes($bodyPart[$k]);
					if (strpos($str, '>') === strlen($str) - 1) {
						if ($tagAttr['href']{0} != '#') {
							list($url, $jumpurlId) = explode('jumpurl=', $tagAttr['href']);
							$url = $HTMLlinks[$jumpurlId]['url'];
							$label = $LANG->getLL('stats_img_link') . '<span title="'.$url.'">' . t3lib_div::fixed_lgd_cs(substr($url, 7), 40) . '</span>';
							$HTMLlinks[$jumpurlId]['label'] = $label;
						}
					} else {
						if ($tagAttr['href']{0} != '#') {
							list($url, $jumpurlId) = explode('jumpurl=', $tagAttr['href']);
							$wordPos = strpos($str, '>');
							$label = substr($str, $wordPos+1);
							$HTMLlinks[$jumpurlId]['label'] = $label;
						}
					}
				}
			}
		}

		foreach ($urlCounter['total'] as $id => $hits) {
				// $id is the jumpurl ID
			$id     = abs(intval($id));
			$url    = $HTMLlinks[$id]['url'];
				// a link to this host?
			$uParts = @parse_url($url);
			$urlstr = $this->getUrlStr($uParts);

			$label = $HTMLlinks[$id]['label'].' (' . ($urlstr ? $urlstr : '/') . ')';
			$img = '<a href="'.htmlspecialchars($url).'"><img '.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/zoom.gif', 'width="12" height="12"').' title="'.htmlspecialchars($url).'" /></a>';

			if (isset($urlCounter['html'][$id]['plainId']))	{
				$tblLines[] = array(
					$label,
					$id,
					$urlCounter['html'][$id]['plainId'],
					$urlCounter['total'][$id]['counter'],
					$urlCounter['html'][$id]['counter'],
					$urlCounter['html'][$id]['plainCounter'],
					$img
				);
			} else	{
				$html = (empty($urlCounter['html'][$id]['counter']) ? 0 : 1);
				$tblLines[] = array(
					$label,
					($html ? $id : '-'),
					($html ? '-' : abs($id)),
					($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$id]['counter']),
					$urlCounter['html'][$id]['counter'],
					$urlCounter['plain'][$id]['counter'],
					$img
				);
			}
		}


			// go through all links that were not clicked yet and that have a label
		$clickedLinks = array_keys($urlCounter['total']);
		foreach ($urlArr as $id => $link) {
			if (!in_array($id, $clickedLinks) && (isset($HTMLlinks['id']))) {
					// a link to this host?
				$uParts = @parse_url($link);
				$urlstr = $this->getUrlStr($uParts);

				$label = $HTMLlinks[$id]['label'] . ' (' . ($urlstr ? $urlstr : '/') . ')';
				$img = '<a href="' . htmlspecialchars($link) . '"><img ' . t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/zoom.gif', 'width="12" height="12"') . ' title="' . htmlspecialchars($link) . '" /></a>';
				$tblLines[] = array(
					$label,
					($html ? $id : '-'),
					($html ? '-' : abs($id)),
					($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$id]['counter']),
					$urlCounter['html'][$id]['counter'],
					$urlCounter['plain'][$id]['counter'],
					$img
				);
			}
		}

		if ($urlCounter['total']) {
			$output .= '<br /><strong>' . $LANG->getLL('stats_response_link') . '</strong>';
			$output .= tx_directmail_static::formatTable($tblLines, array('nowrap','nowrap width="50"','nowrap width="50"','nowrap align="right"','nowrap align="right"','nowrap align="right"','nowrap align="right"'),1,array(1,0,0,0,0,0,1));
		}




		// ******************
		// Returned mails
		// ******************

			//The icons:
		$listIcons = '<img '.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/list.gif', 'width="12" height="12" alt=""').' />';
		$csvIcons = '<img '.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/csv.gif', 'width="27" height="12" alt=""').' />';
		if(t3lib_extMgm::isLoaded('tt_address')){
			$iconPath = t3lib_extMgm::extRelPath('tt_address').'ext_icon__h.gif';
			$iconParam = 'width="18" height="16"' ;
		} else {
			$iconPath = 'gfx/button_hide.gif';
			$iconParam = 'width="11" height="10"';
		}
		$hideIcons = '<img '.t3lib_iconWorks::skinImg($BACK_PATH, $iconPath, $iconParam.' alt=""').' />';

			//icons mails returned
		$iconsMailReturned[]='<a href="'.$thisurl.'&returnList=1" class="bubble">' . $listIcons.'<span class="help">'.$LANG->getLL('stats_list_returned') . '</span></a>';
		$iconsMailReturned[]='<a href="'.$thisurl.'&returnDisable=1" class="bubble">' . $hideIcons.'<span class="help">'.$LANG->getLL('stats_disable_returned') . '</span></a>';
		$iconsMailReturned[]='<a href="'.$thisurl.'&returnCSV=1" class="bubble">' . $csvIcons.'<span class="help">'.$LANG->getLL('stats_CSV_returned') . '</span></a>';

			//icons unknown recip
		$iconsUnknownRecip[] ='<a href="'.$thisurl.'&unknownList=1" class="bubble">' . $listIcons.'<span class="help">'.$LANG->getLL('stats_list_returned_unknown_recipient') . '</span></a>';
		$iconsUnknownRecip[] ='<a href="'.$thisurl.'&unknownDisable=1" class="bubble">' . $hideIcons.'<span class="help">'.$LANG->getLL('stats_disable_returned_unknown_recipient') . '</span></a>';
		$iconsUnknownRecip[] ='<a href="'.$thisurl.'&unknownCSV=1" class="bubble">' . $csvIcons.'<span class="help">'.$LANG->getLL('stats_CSV_returned_unknown_recipient') . '</span></a>';

			//icons mailbox full
		$iconsMailbox[] ='<a href="'.$thisurl.'&fullList=1" class="bubble">' . $listIcons.'<span class="help">'.$LANG->getLL('stats_list_returned_mailbox_full') . '</span></a>';
		$iconsMailbox[] ='<a href="'.$thisurl.'&fullDisable=1" class="bubble">' . $hideIcons.'<span class="help">'.$LANG->getLL('stats_disable_returned_mailbox_full') . '</span></a>';
		$iconsMailbox[] ='<a href="'.$thisurl.'&fullCSV=1" class="bubble">' . $csvIcons.'<span class="help">'.$LANG->getLL('stats_CSV_returned_mailbox_full') . '</span></a>';

			//icons bad host
		$iconsBadhost[] ='<a href="'.$thisurl.'&badHostList=1" class="bubble">' . $listIcons.'<span class="help">'.$LANG->getLL('stats_list_returned_bad_host') . '</span></a>';
		$iconsBadhost[] ='<a href="'.$thisurl.'&badHostDisable=1" class="bubble">' . $hideIcons.'<span class="help">'.$LANG->getLL('stats_disable_returned_bad_host') . '</span></a>';
		$iconsBadhost[] ='<a href="'.$thisurl.'&badHostCSV=1" class="bubble">' . $csvIcons.'<span class="help">'.$LANG->getLL('stats_CSV_returned_bad_host') . '</span></a>';

			//icons bad header
		$iconsBadheader[] ='<a href="'.$thisurl.'&badHeaderList=1" class="bubble">' . $listIcons.'<span class="help">'.$LANG->getLL('stats_list_returned_bad_header') . '</span></a>';
		$iconsBadheader[] ='<a href="'.$thisurl.'&badHeaderDisable=1" class="bubble">' . $hideIcons.'<span class="help">'.$LANG->getLL('stats_disable_returned_bad_header') . '</span></a>';
		$iconsBadheader[] ='<a href="'.$thisurl.'&badHeaderCSV=1" class="bubble">' . $csvIcons.'<span class="help">'.$LANG->getLL('stats_CSV_returned_bad_header') . '</span></a>';

			//icons unknown reasons
			//TODO: link to show all reason
		$iconsUnknownReason[] ='<a href="'.$thisurl.'&reasonUnknownList=1" class="bubble">' . $listIcons.'<span class="help">'.$LANG->getLL('stats_list_returned_reason_unknown') . '</span></a>';
		$iconsUnknownReason[] ='<a href="'.$thisurl.'&reasonUnknownDisable=1" class="bubble">' . $hideIcons.'<span class="help">'.$LANG->getLL('stats_disable_returned_reason_unknown') . '</span></a>';
		$iconsUnknownReason[] ='<a href="'.$thisurl.'&reasonUnknownCSV=1" class="bubble">' . $csvIcons.'<span class="help">'.$LANG->getLL('stats_CSV_returned_reason_unknown') . '</span></a>';

			//Table with Icon
		$queryArray = array('count(*) as counter,return_code', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=-127', 'return_code');
		$table_ret = $this->getQueryRows($queryArray,'return_code');

		$tblLines=array();
		$tblLines[]=array('',$LANG->getLL('stats_count'),'');
		$tblLines[]=array($LANG->getLL('stats_total_mails_returned'), ($table['-127']['counter']?number_format(intval($table['-127']['counter'])):'0'), implode('&nbsp;&nbsp;',$iconsMailReturned));
		$tblLines[]=array($LANG->getLL('stats_recipient_unknown'), $this->showWithPercent($table_ret['550']['counter']+$table_ret['553']['counter'],$table['-127']['counter']), implode('&nbsp;&nbsp;',$iconsUnknownRecip));
		$tblLines[]=array($LANG->getLL('stats_mailbox_full'), $this->showWithPercent($table_ret['551']['counter'],$table['-127']['counter']), implode('&nbsp;&nbsp;',$iconsMailbox));
		$tblLines[]=array($LANG->getLL('stats_bad_host'), $this->showWithPercent($table_ret['552']['counter'],$table['-127']['counter']), implode('&nbsp;&nbsp;',$iconsBadhost));
		$tblLines[]=array($LANG->getLL('stats_error_in_header'), $this->showWithPercent($table_ret['554']['counter'],$table['-127']['counter']),implode('&nbsp;&nbsp;',$iconsBadheader));
		$tblLines[]=array($LANG->getLL('stats_reason_unkown'), $this->showWithPercent($table_ret['-1']['counter'],$table['-127']['counter']),implode('&nbsp;&nbsp;',$iconsUnknownReason));

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned') . '</strong>';
		$output.=tx_directmail_static::formatTable($tblLines,array('nowrap','nowrap align="right"',''),1,array(0,0,1));

			//Find all returned mail
		if (t3lib_div::_GP('returnList')||t3lib_div::_GP('returnDisable')||t3lib_div::_GP('returnCSV'))		{
			$res = $TYPO3_DB->exec_SELECTquery(
				'rid,rtbl',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=-127'
				);
			$idLists = array();
			while($rrow = $TYPO3_DB->sql_fetch_assoc($res))	{
				switch($rrow['rtbl'])	{
					case 't':
						$idLists['tt_address'][]=$rrow['rid'];
					break;
					case 'f':
						$idLists['fe_users'][]=$rrow['rid'];
					break;
					default:
						$idLists[$rrow['rtbl']][]=$rrow['rid'];
					break;
				}
			}

			if (t3lib_div::_GP('returnList'))	{
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address',$this->id,$this->doc->bgColor4);}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users',$this->id,$this->doc->bgColor4);}
			}
			if (t3lib_div::_GP('returnDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('returnCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

			//Find Unknown Recipient
		if (t3lib_div::_GP('unknownList')||t3lib_div::_GP('unknownDisable')||t3lib_div::_GP('unknownCSV'))		{
			$res = $TYPO3_DB->exec_SELECTquery(
				'rid,rtbl',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=-127'.
					' AND (return_code=550 OR return_code=553)'
				);
			$idLists = array();
			while($rrow = $TYPO3_DB->sql_fetch_assoc($res))	{
				switch($rrow['rtbl'])	{
					case 't':
						$idLists['tt_address'][]=$rrow['rid'];
					break;
					case 'f':
						$idLists['fe_users'][]=$rrow['rid'];
					break;
					default:
						$idLists[$rrow['rtbl']][]=$rrow['rid'];
					break;
				}
			}

			if (t3lib_div::_GP('unknownList'))	{
				if (is_array($idLists['tt_address'])) {
					$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address',$this->id,$this->doc->bgColor4);
				}
				if (is_array($idLists['fe_users'])) {
					$output.= '<br />' . $LANG->getLL('stats_website_users') . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users',$this->id,$this->doc->bgColor4);
				}
			}
			if (t3lib_div::_GP('unknownDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('unknownCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_unknown_recipient_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

			//Mailbox Full
		if (t3lib_div::_GP('fullList')||t3lib_div::_GP('fullDisable')||t3lib_div::_GP('fullCSV'))		{
			$res = $TYPO3_DB->exec_SELECTquery(
				'rid,rtbl',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=-127'.
					' AND return_code=551'
				);
			$idLists = array();
			while($rrow = $TYPO3_DB->sql_fetch_assoc($res))	{
				switch($rrow['rtbl'])	{
					case 't':
						$idLists['tt_address'][]=$rrow['rid'];
					break;
					case 'f':
						$idLists['fe_users'][]=$rrow['rid'];
					break;
					default:
						$idLists[$rrow['rtbl']][]=$rrow['rid'];
					break;
				}
			}

			if (t3lib_div::_GP('fullList'))	{
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address',$this->id,$this->doc->bgColor4);}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users',$this->id,$this->doc->bgColor4);}
			}
			if (t3lib_div::_GP('fullDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('fullCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_mailbox_full_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

			//find Bad Host
		if (t3lib_div::_GP('badHostList')||t3lib_div::_GP('badHostDisable')||t3lib_div::_GP('badHostCSV'))		{
			$res = $TYPO3_DB->exec_SELECTquery(
				'rid,rtbl',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=-127'.
					' AND return_code=552'
				);
			$idLists = array();
			while($rrow = $TYPO3_DB->sql_fetch_assoc($res))	{
				switch($rrow['rtbl'])	{
					case 't':
						$idLists['tt_address'][]=$rrow['rid'];
					break;
					case 'f':
						$idLists['fe_users'][]=$rrow['rid'];
					break;
					default:
						$idLists[$rrow['rtbl']][]=$rrow['rid'];
					break;
				}
			}

			if (t3lib_div::_GP('badHostList'))	{
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address',$this->id,$this->doc->bgColor4);}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users',$this->id,$this->doc->bgColor4);}
			}
			if (t3lib_div::_GP('badHostDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('badHostCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_bad_host_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

			//find Bad Header
		if (t3lib_div::_GP('badHeaderList')||t3lib_div::_GP('badHeaderDisable')||t3lib_div::_GP('badHeaderCSV'))		{
			$res = $TYPO3_DB->exec_SELECTquery(
				'rid,rtbl',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=-127'.
					' AND return_code=554'
				);
			$idLists = array();
			while($rrow = $TYPO3_DB->sql_fetch_assoc($res))	{
				switch($rrow['rtbl'])	{
					case 't':
						$idLists['tt_address'][]=$rrow['rid'];
					break;
					case 'f':
						$idLists['fe_users'][]=$rrow['rid'];
					break;
					default:
						$idLists[$rrow['rtbl']][]=$rrow['rid'];
					break;
				}
			}

			if (t3lib_div::_GP('badHeaderList'))	{
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address',$this->id,$this->doc->bgColor4);}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users',$this->id,$this->doc->bgColor4);}
			}

			if (t3lib_div::_GP('badHeaderDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('badHeaderCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_bad_header_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

			//find Unknown Reasons
			//TODO: list all reason
		if (t3lib_div::_GP('reasonUnknownList')||t3lib_div::_GP('reasonUnknownDisable')||t3lib_div::_GP('reasonUnknownCSV'))		{
			$res = $TYPO3_DB->exec_SELECTquery(
				'rid,rtbl',
				'sys_dmail_maillog',
				'mid='.intval($row['uid']).
					' AND response_type=-127'.
					' AND return_code=-1'
				);
			$idLists = array();
			while($rrow = $TYPO3_DB->sql_fetch_assoc($res))	{
				switch($rrow['rtbl'])	{
					case 't':
						$idLists['tt_address'][]=$rrow['rid'];
					break;
					case 'f':
						$idLists['fe_users'][]=$rrow['rid'];
					break;
					default:
						$idLists[$rrow['rtbl']][]=$rrow['rid'];
					break;
				}
			}

			if (t3lib_div::_GP('reasonUnknownList'))	{
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address',$this->id,$this->doc->bgColor4);}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users',$this->id,$this->doc->bgColor4);}
			}
			if (t3lib_div::_GP('reasonUnknownDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('reasonUnknownCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_reason_unknown_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

		/**
		 * Hook for cmd_stats_postProcess
		 * insert a link to open extended importer
		 */
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats'])) {
			$hookObjectsArr = array();
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
			foreach($hookObjectsArr as $hookObj)    {
				if (method_exists($hookObj, 'cmd_stats_postProcess')) {
					$output .= $hookObj->cmd_stats_postProcess($row, $this);
				}
			}
		}
		
		$this->noView=1;
		$theOutput.= $this->doc->section($LANG->getLL('stats_direct_mail'),$output, 1, 1, 0, TRUE);
		$link = '<a href="'.$thisurl.'">' . $LANG->getLL('stats_recalculate_stats') . '</a>';
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section($LANG->getLL('stats_recalculate_cached_data'), $link, 1, 1, 0, TRUE);
		return $theOutput;
	}

	/**
	 * generates a string for the URL
	 *
	 * @param	array	$uParts	the parts of the URL
	 * @return	string	the URL string
	 */
	function getUrlStr($uParts) {
		$urlstr = '';
		if (is_array($uParts) && t3lib_div::getIndpEnv('TYPO3_HOST_ONLY') == $uParts['host']) {
			$m = array();
			// do we have an id?
			if (preg_match('/(?:^|&)id=([0-9a-z_]+)/', $uParts['query'], $m)) {
				if (t3lib_div::testInt($m[1])) {
					$uid = intval($m[1]);
				} else {
					$uid = $this->sys_page->getPageIdFromAlias($m[1]);
				}
				$temp_root_line = $this->sys_page->getRootLine($uid);
				$temp_page = array_shift($temp_root_line);
				// array_shift reverses the array (rootline has numeric index in the wrong order!)
				$temp_root_line = array_reverse($temp_root_line);
				$query = preg_replace('/(?:^|&)id=([0-9a-z_]+)/', '', $uParts['query']);
				$urlstr = t3lib_div::fixed_lgd_cs($temp_page['title'], 50) . t3lib_div::fixed_lgd_cs(($query ? ' / ' . $query : ''), 20);
			} else {
				$urlstr = $uParts['path'] . ($uParts['query'] ? '?' . $uParts['query'] : '');
				$urlstr = t3lib_div::fixed_lgd_cs($urlstr, 60);
			}
		} else {
			$urlstr = $uParts['host'] . $uParts['path'] . ($uParts['query'] ? '?' . $uParts['query'] : '');
			$urlstr = t3lib_div::fixed_lgd_cs($urlstr, 60);
		}
		return $urlstr;
	}
	
	/**
	 * set disable=1 to all record in an array
	 *
	 * @param	array		$arr: DB records
	 * @param	string		$table: table name
	 * @return	integer		total of disabled records
	 */
	function disableRecipients($arr,$table)	{
		if ($GLOBALS['TCA'][$table])	{
			$fields_values=array();
			$enField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];
			if ($enField)	{
				$fields_values[$enField]=1;
				$count=count($arr);
				$uidList = array_keys($arr);
				if (count($uidList))	{
					$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						$table,
						'uid IN ('.implode(',',$GLOBALS['TYPO3_DB']->cleanIntArray($uidList)).')',
						$fields_values
						);
				}
			}
		}
		return intval($count);
	}

	/**
	 * write the statistic to a temporary table
	 *
	 * @param	array		$mrow: DB mail records
	 * @return	void		no return value: call storeRecRec function
	 */
	function makeStatTempTableContent($mrow)	{
		global $TYPO3_DB;

		// Remove old:
		$TYPO3_DB->exec_DELETEquery(
			'cache_sys_dmail_stat',
			'mid='.intval($mrow['uid'])
			);

		$res = $TYPO3_DB->exec_SELECTquery(
			'rid,rtbl,tstamp,response_type,url_id,html_sent,size',
			'sys_dmail_maillog',
			'mid='.intval($mrow['uid']),
			'',
			'rtbl,rid,tstamp'
			);

		$currentRec = '';
		$recRec = '';
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$thisRecPointer=$row['rtbl'].$row['rid'];
			if ($thisRecPointer!=$currentRec)	{
				$this->storeRecRec($recRec);
//				debug($thisRecPointer);
				$recRec=array(
					'mid'=>intval($mrow['uid']),
					'rid'=>intval($row['rid']),
					'rtbl'=>$row['rtbl'],
					'pings'=>array(),
					'plain_links'=>array(),
					'html_links'=>array(),
					'response'=>array(),
					'links'=>array()
					);
				$currentRec=$thisRecPointer;
			}
			switch ($row['response_type'])	{
			case '-1':
				$recRec['pings'][]=$row['tstamp'];
				$recRec['response'][]=$row['tstamp'];
				break;
			case '0':
				$recRec['recieved_html']=$row['html_sent']&1;
				$recRec['recieved_plain']=$row['html_sent']&2;
				$recRec['size']=$row['size'];
				$recRec['tstamp']=$row['tstamp'];
				break;
			case '1':
			case '2':
				$recRec[($row['response_type']==1?'html_links':'plain_links')][] = $row['tstamp'];
				$recRec['links'][]=$row['tstamp'];
				if (!$recRec['firstlink'])	{
					$recRec['firstlink']=$row['url_id'];
					$recRec['firstlink_time']=intval(@max($recRec['pings']));
					$recRec['firstlink_time']= $recRec['firstlink_time'] ? $row['tstamp']-$recRec['firstlink_time'] : 0;
				} elseif (!$recRec['secondlink'])	{
					$recRec['secondlink']=$row['url_id'];
					$recRec['secondlink_time']=intval(@max($recRec['pings']));
					$recRec['secondlink_time']= $recRec['secondlink_time'] ? $row['tstamp']-$recRec['secondlink_time'] : 0;
				} elseif (!$recRec['thirdlink'])	{
					$recRec['thirdlink']=$row['url_id'];
					$recRec['thirdlink_time']=intval(@max($recRec['pings']));
					$recRec['thirdlink_time']= $recRec['thirdlink_time'] ? $row['tstamp']-$recRec['thirdlink_time'] : 0;
				}
				$recRec['response'][]=$row['tstamp'];
				break;
			case '-127':
				$recRec['returned']=1;
				break;
			}
		}
		$this->storeRecRec($recRec);
	}

	/**
	 * insert statistic to a temporary table
	 *
	 * @param	array		$recRec: statistic array
	 * @return	void		no return value: write the array to a table
	 */
	function storeRecRec($recRec)	{
		if (is_array($recRec))	{
			$recRec['pings_first'] = intval(@min($recRec['pings']));
			$recRec['pings_last'] = intval(@max($recRec['pings']));
			$recRec['pings'] = count($recRec['pings']);

			$recRec['html_links_first'] = intval(@min($recRec['html_links']));
			$recRec['html_links_last'] = intval(@max($recRec['html_links']));
			$recRec['html_links'] = count($recRec['html_links']);

			$recRec['plain_links_first'] = intval(@min($recRec['plain_links']));
			$recRec['plain_links_last'] = intval(@max($recRec['plain_links']));
			$recRec['plain_links'] = count($recRec['plain_links']);

			$recRec['links_first'] = intval(@min($recRec['links']));
			$recRec['links_last'] = intval(@max($recRec['links']));
			$recRec['links'] = count($recRec['links']);

			$recRec['response_first'] = t3lib_div::intInRange(intval(@min($recRec['response']))-$recRec['tstamp'],0);
			$recRec['response_last'] = t3lib_div::intInRange(intval(@max($recRec['response']))-$recRec['tstamp'],0);
			$recRec['response'] = count($recRec['response']);

			$recRec['time_firstping'] = t3lib_div::intInRange($recRec['pings_first']-$recRec['tstamp'],0);
			$recRec['time_lastping'] = t3lib_div::intInRange($recRec['pings_last']-$recRec['tstamp'],0);

			$recRec['time_first_link'] = t3lib_div::intInRange($recRec['links_first']-$recRec['tstamp'],0);
			$recRec['time_last_link'] = t3lib_div::intInRange($recRec['links_last']-$recRec['tstamp'],0);

			$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery(
				'cache_sys_dmail_stat',
				$recRec
				);
		}
	}

	/**
	 * make a select query
	 *
	 * @param	array		$queryArray: part of select-statement in an array
	 * @param	string		$key_field: DB fieldname to be the array keys
	 * @return	array		result of the Select-query
	 */
	function getQueryRows($queryArray,$key_field)	{
		global $TYPO3_DB;

		$res = $TYPO3_DB->exec_SELECTquery(
			$queryArray[0],
			$queryArray[1],
			$queryArray[2],
			$queryArray[3],
			$queryArray[4],
			$queryArray[5]
			);
		$lines = array();
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			if ($key_field)	{
				$lines[$row[$key_field]] = $row;
			} else {
				$lines[] = $row;
			}
		}
		return $lines;
	}

	/**
	 * make a percent from the given parameters
	 *
	 * @param	integer		$pieces: number of pieces
	 * @param	integer		$total: total of pieces
	 * @return	string		show number of pieces and the percent
	 */
	function showWithPercent($pieces,$total) {
		$total = intval($total);
		$str = $pieces?number_format(intval($pieces)):'0';
		if ($total) {
			$str.= ' / '.number_format(($pieces/$total*100),2).'%';
		}
		return $str;
	}

	/**
	 * Set up URL variables for this $row.
	 *
	 * @param	array		$row: DB records
	 * @return	void		no return values: update the global variables 'url_plain' and 'url_html'
	 */
	function setURLs($row)	{
			// Finding the domain to use
		$this->urlbase = tx_directmail_static::getUrlBase($row['use_domain']);

			// Finding the url to fetch content from
		switch((string)$row['type'])	{
			case 1:
				$this->url_html = $row['HTMLParams'];
				$this->url_plain = $row['plainParams'];
				break;
			default:
				$this->url_html = $this->urlbase.'?id='.$row['page'].$row['HTMLParams'];
				$this->url_plain = $this->urlbase.'?id='.$row['page'].$row['plainParams'];
				break;
		}

		if (!($row['sendOptions']&1) || !$this->url_plain)	{	// plain
			$this->url_plain='';
		} else {
			$urlParts = @parse_url($this->url_plain);
			if (!$urlParts['scheme'])	{
				$this->url_plain='http://'.$this->url_plain;
			}
		}
		if (!($row['sendOptions']&2) || !$this->url_html)	{	// html
			$this->url_html='';
		} else {
			$urlParts = @parse_url($this->url_html);
			if (!$urlParts['scheme'])	{
				$this->url_html='http://'.$this->url_html;
			}
		}
	}

	/**
	 * show the compact information of a direct mail record
	 *
	 * @param	array		$row: direct mail record
	 * @return	string		the compact infos of the direct mail record
	 */
	function directMail_compactView($row)	{
		global $LANG, $BE_USER, $BACK_PATH;

			// Render record:
		if ($row['type'])	{
			$dmailData = $row['plainParams'].', '.$row['HTMLParams'];
		} else {
			$page = t3lib_BEfunc::getRecord('pages',$row['page'],'title');
			$dmailData = $row['page'].', '.htmlspecialchars($page['title']);

			$dmail_info = tx_directmail_static::fName('plainParams').' '.htmlspecialchars($row['plainParams'].chr(10).tx_directmail_static::fName('HTMLParams').$row['HTMLParams']).'; '.chr(10);
		}
		$dmail_info .= $LANG->getLL('view_media').' '.t3lib_BEfunc::getProcessedValue('sys_dmail','includeMedia',$row['includeMedia']).'; '.chr(10).
			$LANG->getLL('view_flowed').' '.t3lib_BEfunc::getProcessedValue('sys_dmail','flowedFormat',$row['flowedFormat']);
		$dmail_info = '<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/zoom2.gif','width="12" height="12"').' title="'.$dmail_info.'">';

		$from_info = $LANG->getLL('view_replyto').' '.htmlspecialchars($row['replyto_name'].' <'.$row['replyto_email'].'>').'; '.chr(10).
			tx_directmail_static::fName('organisation').' '.htmlspecialchars($row['organisation']).'; '.chr(10).
			tx_directmail_static::fName('return_path').' '.htmlspecialchars($row['return_path']);
		$from_info = '<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/zoom2.gif','width="12" height="12"').' title="'.$from_info.'">';

		$mail_info = tx_directmail_static::fName('priority').' '.t3lib_BEfunc::getProcessedValue('sys_dmail','priority',$row['priority']).'; '.chr(10).
			tx_directmail_static::fName('encoding').' '.t3lib_BEfunc::getProcessedValue('sys_dmail','encoding',$row['encoding']).'; '.chr(10).
			tx_directmail_static::fName('charset').' '.t3lib_BEfunc::getProcessedValue('sys_dmail','charset',$row['charset']);
		$mail_info = '<img'.t3lib_iconWorks::skinImg($BACK_PATH,'gfx/zoom2.gif','width="12" height="12"').' title="'.$mail_info.'">';

		$delBegin = fw($row["scheduled_begin"]?t3lib_BEfunc::datetime($row["scheduled_begin"]):'-');
		$delEnd = fw($row["scheduled_end"]?t3lib_BEfunc::datetime($row["scheduled_begin"]):'-');

		//count total recipient from the query_info
		$totalRecip = 0;
		$id_lists = unserialize($row['query_info']);
		foreach( $id_lists['id_lists'] as $table => $idArray){
			$totalRecip += count($idArray);
		}
		$sentRecip = $GLOBALS['TYPO3_DB']->sql_num_rows($GLOBALS['TYPO3_DB']->exec_SELECTquery('*','sys_dmail_maillog','mid='.$row['uid'].' AND response_type = 0','','rid ASC'));

		$out = '<table border="0" cellpadding="1" cellspacing="1" width="460">';
		$out .= '<tr bgColor="'.$this->doc->bgColor4.'"><td>'.$LANG->getLL('view_from').'</td><td>'.htmlspecialchars($row['from_name'].' <'.$row['from_email'].'>').'</td><td>'.$from_info.'</td></tr>';
		$out .= '<tr bgColor="'.$this->doc->bgColor4.'"><td>'.$LANG->getLL('view_dmail').'</td><td>'.t3lib_BEfunc::getProcessedValue('sys_dmail','type',$row['type']).': '.$dmailData.'</td><td>'.$dmail_info.'</td></tr>';
		$out .= '<tr bgColor="'.$this->doc->bgColor4.'"><td>'.$LANG->getLL('view_mail').'</td><td>'.t3lib_BEfunc::getProcessedValue('sys_dmail','sendOptions',$row['sendOptions']).($row['attachment']?'; ':'').t3lib_BEfunc::getProcessedValue('sys_dmail','attachment',$row['attachment']).'</td><td>'.$mail_info.'</td></tr>';
		$out .= '<tr bgColor="'.$this->doc->bgColor4.'"><td>'.$LANG->getLL('view_delivery_begin_end').'</td><td>'.$delBegin.' / '.$delEnd.'</td><td>&nbsp;</td></tr>';
		$out .= '<tr bgColor="'.$this->doc->bgColor4.'"><td>'.$LANG->getLL('view_recipient_total_sent').'</td><td>'.$totalRecip.' / '.$sentRecip.'</td><td>&nbsp;</td></tr>';
		$out .= '</table>';
		$out .= $this->doc->spacer(5);

		return $out;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod4/class.tx_directmail_statistics.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod4/class.tx_directmail_statistics.php']);
}

?>
