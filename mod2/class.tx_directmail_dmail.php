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
 * @subpackage	tx_directmail
 *
 * @version 	$Id$
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *  109: class tx_directmail_dmail extends t3lib_SCbase
 *  143:     function init()
 *  204:     function printContent()
 *  214:     function main()
 *  366:     function createDMail()
 *  460:     function moduleContent()
 *  482:     function createDMail_quick($indata)
 *  582:     function showSteps($step, $stepTotal = 5)
 *  596:     function mailModule_main()
 *  772:     function JSbottom($formname='forms[0]')
 *  810:     function cmd_finalmail($row)
 *  858:     function cmd_send_mail($row)
 *  969:     function sendTestMailToTable($idLists,$table,$htmlmail)
 *  997:     function cmd_testmail($row)
 * 1071:     function cmd_displayMailGroup_test($result)
 * 1091:     function fetchRecordsListValues($listArr,$table,$fields='uid,name,email')
 * 1120:     function getRecordList($listArr,$table,$dim=0,$editLinkFlag=1)
 * 1162:     function cmd_compileMailGroup($group_uid)
 * 1271:     function getRecursiveSelect($id,$perms_clause)
 * 1288:     function cleanPlainList($plainlist)
 * 1304:     function update_specialQuery($mailGroup)
 * 1365:     function getIdList($table,$pidList,$group_uid,$cat)
 * 1456:     function getStaticIdList($table,$uid)
 * 1515:     function getSpecialQueryIdList($table,$group)
 * 1543:     function getMailGroups($list,$parsedGroups)
 * 1577:     function rearrangeCsvValues($lines)
 * 1647:     function rearrangePlainMails($plainMails)
 * 1666:     function makeCategoriesForm()
 * 1755:     function makeCategories($table,$row)
 * 1796:     function getRecordOverlay($table,$row,$sys_language_content,$OLmode='')
 * 1858:     function makeFormInternal($boxID,$totalBox)
 * 1880:     function makeFormExternal($boxID,$totalBox)
 * 1915:     function makeFormQuickMail($boxID,$totalBox)
 * 1938:     function makeListDMail($boxID,$totalBox)
 * 1991:     function cmd_quickmail()
 * 2016:     function cmd_news ()
 * 2059:     function linkDMail_record($str,$uid)
 * 2073:     function formatTable($tableLines,$cellParams,$header,$cellcmd=array(),$tableParams='border="0" cellpadding="2" cellspacing="3"')
 * 2101:     function setURLs($row)
 * 2141:     function getPageCharSet($pageId)
 * 2158:     function getUrlBase($domainUid)
 * 2189:     function addUserPass($url)
 * 2206:     function cmd_fetch($row,$embed=FALSE)
 * 2324:     function directMail_defaultView($row)
 * 2360:     function fName($name)
 *
 * TOTAL FUNCTIONS: 44
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_tstemplate.php');
require_once(PATH_t3lib.'class.t3lib_timetrack.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.mailselect.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.dmailer.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/calendar/class.tx_directmail_calendarlib.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_static.php');

/**
 * Direct mail Module of the tx_directmail extension for sending newsletter
 *
 */
class tx_directmail_dmail extends t3lib_SCbase {
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
	var $categories;
	var $id;
	var $urlbase;
	var $back;
	var $noView;
	var $url_plain;
	var $url_html;
	var $mode;
	var $implodedParams=array();
	var $userTable;		// If set a valid user table is around
	var $sys_language_uid = 0;
	var $error='';
	var $allowedTables = array('tt_address','fe_users');
	var $queryGenerator;
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';

	/**
	 * first initialization of global variables
	 *
	 * @return	void		...
	 */
	function init()	{
		global $LANG,$BACK_PATH,$TCA,$TYPO3_CONF_VARS,$TYPO3_DB;

		$this->MCONF = $GLOBALS['MCONF'];

		$this->include_once[]=PATH_t3lib.'class.t3lib_tcemain.php';
//		$this->include_once[]=PATH_t3lib.'class.t3lib_pagetree.php';

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
		
		// check if the right domain shoud be set
		if (!$this->params['use_domain']) {
			$rootLine = t3lib_BEfunc::BEgetRootLine($this->id);
			if ($rootLine)  {
				$parts = parse_url(t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
				if (t3lib_BEfunc::getDomainStartPage($parts['host'],$parts['path']))    {
					$preUrl_temp = t3lib_BEfunc::firstDomainRecord($rootLine);
					$domain = t3lib_BEfunc::getRecordsByField('sys_domain','domainName',$preUrl_temp,' AND hidden=0','','sorting');
					if (is_array($domain)) {
						reset($domain);
						$dom = current($domain);
						$this->params['use_domain'] = $dom['uid'];
					}
				}
			}
		}
		
		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize the TS template
		$GLOBALS['TT'] = new t3lib_timeTrack;
		$this->tmpl = t3lib_div::makeInstance('t3lib_TStemplate');
		$this->tmpl->init();

			// initialize the page selector
		$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		$this->sys_page->init(true);

			// initialize the query generator
		$this->queryGenerator = t3lib_div::makeInstance('mailSelect');

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
	 * Prints out the module HTML
	 *
	 * @return	void		...
	 */
	function printContent()	{
		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * The main function. Set CSS and JS
	 *
	 * @return	void		...
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
					a {text-decoration: none;}
					#page-header {font-weight: bold;}
					.box {margin: 0 0 1em 1em;}
					div.toggleTitle:hover {background-color: #cfcbc7 !important;}
					div.toggleTitle a {width: 100%; position: relative; top: -3px; display:block;}
					div.toggleTitle a img {position: relative; top:4px;}
					.toggleTitle {font-weight:bold; border: 1px solid #898989; width: 15em; background-color: #e3dfdb;}
					.toggleBox {border: 1px solid #aaaaaa; background-color: '.$this->doc->bgColor4.'; padding: 1em; width:50em;}
					.toggleBox h3 {background-color: '.$this->doc->bgColor4.';}
					tr.bgColor4{background-color: '.$this->doc->bgColor4.';}
					div.step_box {margin-left: 5em;}
					div.step_box span {font-size: 22pt; font-weight: bold; font-family: verdana; color: white;}
					div.step_box span.black {color: black}
					input.next {position: absolute; top: 55px; left: 350px;}
					input.back {position: absolute; top: 55px; left: 300px;}
					input.disabled {background: #ccc;}
					}
					';

			// JavaScript
			if(t3lib_div::inList('send_mail_final,send_mass',$this->CMD))
				$this->doc->JScode .= tx_directmail_calendarlib::includeLib($this->params['calConf.']);

			$this->doc->JScode .= '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid='.$this->sys_dmail_uid.'";
					}
					function toggleDisplay(toggleId, e, countBox) { //
						if (!e) {
							e = window.event;
						}
						if (!document.getElementById) {
							return false;
						}

						prefix = toggleId.split("-");
						for (i=1; i<=countBox; i++){
							newToggleId = prefix[0]+"-"+i;
							body = document.getElementById(newToggleId);
							image = document.getElementById(toggleId + "_toggle");
							if (newToggleId != toggleId){
								if (body.style.display == "block"){
									body.style.display = "none";
									if (image) {
										image.src = "'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/button_right.gif', '', 1).'";
									}
								}
							}
						}

						var body = document.getElementById(toggleId);
						if (!body) {
							return false;
						}
						var image = document.getElementById(toggleId + "_toggle");
						if (body.style.display == "none") {
							body.style.display = "block";
							if (image) {
								image.src = "'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/button_down.gif', '', 1).'";
							}
						} else {
							body.style.display = "none";
							if (image) {
								image.src = "'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/button_right.gif', '', 1).'";
							}
						}
						if (e) {
							// Stop the event from propagating, which
							// would cause the regular HREF link to
							// be followed, ruining our hard work.
							e.cancelBubble = true;
							if (e.stopPropagation) {
								e.stopPropagation();
							}
						}
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
			$this->content.=$this->doc->section('',$headerSection,1,0,0,TRUE);
			$this->content.=$this->doc->spacer(5);

			$module = $this->pageinfo['module'];
			if (!$module)	{
				$pidrec=t3lib_BEfunc::getRecord('pages',intval($this->pageinfo['pid']));
				$module=$pidrec['module'];
			}
			if ($module == 'dmail') {
					// Render content:
				$this->moduleContent();
			} else {
				$this->content.=$this->doc->section($LANG->getLL('header_directmail'), $LANG->getLL('select_folder'), 1, 1, 0 , TRUE);
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
	 * Creates a directmail entry in th DB.
	 * Used only for internal and external page
	 *
	 * @return	string		Error or warning message produced during the process
	 */
	function createDMail()	{
		global $TCA, $TYPO3_CONF_VARS;

		$createMailFrom_UID = t3lib_div::_GP('createMailFrom_UID');	// Internal page
		$createMailFrom_URL = t3lib_div::_GP('createMailFrom_URL');	// External URL subject
		if ($createMailFrom_UID || $createMailFrom_URL)	{
				// Set default values:
			$dmail = array();
			$dmail['sys_dmail']['NEW'] = array (
				'from_email'		=> $this->params['from_email'],
				'from_name'		=> $this->params['from_name'],
				'replyto_email'		=> $this->params['replyto_email'],
				'replyto_name'		=> $this->params['replyto_name'],
				'return_path'		=> $this->params['return_path'],
				'priority'		=> $this->params['priority'],
				'use_domain'		=> $this->params['use_domain'],
				'use_rdct'		=> $this->params['use_rdct'],
				'long_link_mode'	=> $this->params['long_link_mode'],
				'organisation'		=> $this->params['organisation'],
				'authcode_fieldList'	=> $this->params['authcode_fieldList']
				);

			$dmail['sys_dmail']['NEW']['sendOptions'] = $TCA['sys_dmail']['columns']['sendOptions']['config']['default'];
			$dmail['sys_dmail']['NEW']['long_link_rdct_url'] = tx_directmail_static::getUrlBase($this->params['use_domain']);

				// If params set, set default values:
			if (isset($this->params['sendOptions']))	$dmail['sys_dmail']['NEW']['sendOptions'] = $this->params['sendOptions'];
			if (isset($this->params['includeMedia'])) 	$dmail['sys_dmail']['NEW']['includeMedia'] = $this->params['includeMedia'];
			if (isset($this->params['flowedFormat'])) 	$dmail['sys_dmail']['NEW']['flowedFormat'] = $this->params['flowedFormat'];
			if (isset($this->params['HTMLParams']))		$dmail['sys_dmail']['NEW']['HTMLParams'] = $this->params['HTMLParams'];
			if (isset($this->params['plainParams']))	$dmail['sys_dmail']['NEW']['plainParams'] = $this->params['plainParams'];
			if (isset($this->params['direct_mail_encoding']))	$dmail['sys_dmail']['NEW']['encoding'] = $this->params['direct_mail_encoding'];

			if (t3lib_div::testInt($createMailFrom_UID))	{
				$createFromMailRec = t3lib_BEfunc::getRecord ('pages',$createMailFrom_UID);
				if (t3lib_div::inList($TYPO3_CONF_VARS['FE']['content_doktypes'],$createFromMailRec['doktype']))	{
					$dmail['sys_dmail']['NEW']['subject'] = $createFromMailRec['title'];
					$dmail['sys_dmail']['NEW']['type'] = 0;
					$dmail['sys_dmail']['NEW']['page'] = $createFromMailRec['uid'];
					$dmail['sys_dmail']['NEW']['charset'] = $this->getPageCharSet($createFromMailRec['uid']);
					$dmail['sys_dmail']['NEW']['pid'] = $this->pageinfo['uid'];
				}
			} else {
				$dmail['sys_dmail']['NEW']['subject'] = $createMailFrom_URL;
				$dmail['sys_dmail']['NEW']['type'] = 1;

				$dmail['sys_dmail']['NEW']['plainParams'] = t3lib_div::_GP('createMailFrom_plainUrl');
				$urlParts = @parse_url($dmail['sys_dmail']['NEW']['plainParams']);
				if (!$dmail['sys_dmail']['NEW']['plainParams'] || $urlParts===FALSE || !$urlParts['host']) {
						// No plain text url
					$dmail['sys_dmail']['NEW']['plainParams'] = '';
					$dmail['sys_dmail']['NEW']['sendOptions']&=254;
				}
				$dmail['sys_dmail']['NEW']['HTMLParams'] = t3lib_div::_GP('createMailFrom_HTMLUrl');
				$urlParts = @parse_url($dmail['sys_dmail']['NEW']['HTMLParams']);
				if (!$dmail['sys_dmail']['NEW']['HTMLParams'] || $urlParts===FALSE || !$urlParts['host']) {
						// No html url
					$dmail['sys_dmail']['NEW']['HTMLParams'] = '';
					$dmail['sys_dmail']['NEW']['sendOptions']&=253;
				}

				$dmail['sys_dmail']['NEW']['pid'] = $this->pageinfo['uid'];
			}

			if ($dmail['sys_dmail']['NEW']['pid'] && $dmail['sys_dmail']['NEW']['sendOptions']) {
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values=0;
				$tce->start($dmail,Array());
				$tce->process_datamap();
				$this->sys_dmail_uid = $tce->substNEWwithIDs['NEW'];

				if (t3lib_div::_GP('fetchAtOnce'))	{
						// Read new record (necessary because TCEmain sets default field values)
					$dmailRec = t3lib_BEfunc::getRecord ('sys_dmail',$this->sys_dmail_uid);
						// Set up URLs from record data for fetch command
					$this->setURLs($dmailRec);
					$theOutput .= $this->cmd_fetch($dmailRec,TRUE);
				}
			} else {
				if (!$dmail['sys_dmail']['NEW']['sendOptions']) {
					$this->error = 'no_valid_url';
				}
			}

		}

		return $theOutput;
	}

	/**
	 * start of the whole module process
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
	 * Creates a directmail entry in th DB.
	 * used only for quickmail.
	 *
	 * @param	array		$indata: quickmail data (quickmail content, etc.)
	 * @return	string		error or warning message produced during the process
	 */
	function createDMail_quick($indata)	{
		global $TCA, $TYPO3_CONF_VARS, $LANG, $TYPO3_DB;

				// Set default values:
			$dmail = array();
			$dmail['sys_dmail']['NEW'] = array (
				'from_email'		=> $indata['senderEmail'],
				'from_name'		=> $indata['senderName'],
				'replyto_email'		=> $this->params['replyto_email'],
				'replyto_name'		=> $this->params['replyto_name'],
				'return_path'		=> $this->params['return_path'],
				'priority'		=> $this->params['priority'],
				'use_domain'		=> $this->params['use_domain'],
				'use_rdct'		=> $this->params['use_rdct'],
				'long_link_mode'	=> $this->params['long_link_mode'],
				'organisation'		=> $this->params['organisation'],
				'authcode_fieldList'	=> $this->params['authcode_fieldList'],
				'plainParams'		=> ''
				);

			$dmail['sys_dmail']['NEW']['sendOptions'] = 1;		//always plaintext
			$dmail['sys_dmail']['NEW']['long_link_rdct_url'] = tx_directmail_static::getUrlBase($this->params['use_domain']);

				// If params set, set default values:
			if (isset($this->params['includeMedia'])) 	$dmail['sys_dmail']['NEW']['includeMedia'] = $this->params['includeMedia'];
			if (isset($this->params['flowedFormat'])) 	$dmail['sys_dmail']['NEW']['flowedFormat'] = $this->params['flowedFormat'];
			if (isset($this->params['direct_mail_encoding']))	$dmail['sys_dmail']['NEW']['encoding'] = $this->params['direct_mail_encoding'];

				$dmail['sys_dmail']['NEW']['subject'] = $indata['subject'];
				$dmail['sys_dmail']['NEW']['type'] = 1;
				$dmail['sys_dmail']['NEW']['pid'] = $this->pageinfo['uid'];
				$dmail['sys_dmail']['NEW']['charset'] = isset($this->params['quick_mail_charset'])? $this->params['quick_mail_charset'] : 'iso-8859-1';

			if ($dmail['sys_dmail']['NEW']['pid'] && $dmail['sys_dmail']['NEW']['sendOptions']) {
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values=0;
				$tce->start($dmail,Array());
				$tce->process_datamap();
				$this->sys_dmail_uid = $tce->substNEWwithIDs['NEW'];

				$row = t3lib_BEfunc::getRecord('sys_dmail',intval($this->sys_dmail_uid));
				//link in the mail
				$message = '<!--DMAILER_SECTION_BOUNDARY_--> '.$indata['message'].' <!--DMAILER_SECTION_BOUNDARY_END-->';
				if (trim($this->params['use_rdct'])) {
					$message = t3lib_div::substUrlsInPlainText($message,$this->params['long_link_mode']?'all':'76',tx_directmail_static::getUrlBase($this->params['use_domain']));
				}
				if ($indata['breakLines'])	{
					$message = wordwrap($message,76,"\n");
				}
				//fetch functions
					// Compile the mail
				$htmlmail = t3lib_div::makeInstance('dmailer');
				$htmlmail->nonCron = 1;
				$htmlmail->start();
				$htmlmail->charset = $row['charset'];
				$htmlmail->useBase64();
				$htmlmail->addPlain($message);
				if (!$message || !$htmlmail->theParts['plain']['content']) {
					$errorMsg .= '<br /><strong>' . $LANG->getLL('dmail_no_plain_content') . '</strong>';
				} elseif (!strstr(base64_decode($htmlmail->theParts['plain']['content']),'<!--DMAILER_SECTION_BOUNDARY')) {
					$warningMsg .= '<br /><strong>' . $LANG->getLL('dmail_no_plain_boundaries') . '</strong>';
				}

				if (!$errorMsg) {
						// Update the record:
					$htmlmail->theParts['messageid'] = $htmlmail->messageid;
					$mailContent = serialize($htmlmail->theParts);
					$updateFields = array(
						'issent' => 0,
						'charset' => $htmlmail->charset,
						'mailContent' => $mailContent,
						'renderedSize' => strlen($mailContent),
						'long_link_rdct_url' => $this->urlbase
						);
					$TYPO3_DB->exec_UPDATEquery(
						'sys_dmail',
						'uid='.intval($this->sys_dmail_uid),
						$updateFields
						);

					if ($warningMsg)	{
						$theOutput .= $this->doc->section($LANG->getLL('dmail_warning'), $warningMsg.'<br /><br />');
					}
				}
				/* end fetch function*/
			} else {
				if (!$dmail['sys_dmail']['NEW']['sendOptions']) {
					$this->error = 'no_valid_url';
				}
			}

		return $theOutput;
	}

	/**
	 * showing steps number on top of every page
	 *
	 * @param	integer		$step: current step
	 * @param	integer		$stepTotal: total step
	 * @return	string		HTML
	 */
	function showSteps($step, $stepTotal = 5){
		$out = '<div class="step_box">';
		for($i=1; $i<=$stepTotal; $i++){
			$class = ($i==$step)?'black':'white';
			$out.='<span class="'.$class.'">&nbsp;'.$i.'&nbsp;</span>';
		}
		return $out.'</div><br />';
	}

	/**
	 * Function mailModule main()
	 *
	 * @return	string		HTML (steps)
	 */
	function mailModule_main()	{
		global $LANG, $TYPO3_DB;

		if(intval($this->sys_dmail_uid)){
			$row = t3lib_BEfunc::getRecord('sys_dmail',intval($this->sys_dmail_uid));
			if($row){
				$this->setURLs($row);
			}
		}

		$imgSrc = t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/button_down.gif');
		if(t3lib_div::_GP('update_cats')){
			$this->CMD = 'cats';
		}
		
		if(t3lib_div::_GP('mailingMode_simple')){
			$this->CMD = 'send_mail_test';
		}

		if(t3lib_div::_GP('back')){
				//CMD move 1 step back
			switch (t3lib_div::_GP('currentCMD')) {
				case 'info':
					$this->CMD = '';
					break;
					
				case 'cats':
					$this->CMD = 'info';
					break;
					
				case 'send_test':
				case 'send_mail_test':
					if(($this->CMD == 'send_mass') && ($GLOBALS['BE_USER']->userTS['tx_directmail.']['hideSteps'] == 'cat')){
						$this->CMD = 'info';	
					} else {
						$this->CMD = 'cats';
					}
					break;
					
				case 'send_mail_final':
				case 'send_mass':
					$this->CMD = 'send_test';
					break;
			}
		}
		
		if($GLOBALS['BE_USER']->userTS['tx_directmail.']['hideSteps'] && ($GLOBALS['BE_USER']->userTS['tx_directmail.']['hideSteps'] == 'cat')){
			$totalSteps = 4;
			if(($this->CMD == 'info') && ($GLOBALS['BE_USER']->userTS['tx_directmail.']['hideSteps'] == 'cat')){
				$nextCMD = 'send_test';
			}
		}else{
			$totalSteps = 5;
			 if($this->CMD == 'info')
				$nextCMD = 'cats';
		}

		switch ($this->CMD) {
			case 'info':
				//greyed out next-button if fetching is not successful (on error)
				$fetchError = true;
				//create dmail and fetch
				$quickmail = t3lib_div::_GP('quickmail');

				if((t3lib_div::_GP('createMailFrom_UID') || t3lib_div::_GP('createMailFrom_URL')) && !$quickmail['send']){
					//internal or external page
					$fetchMsg = $this->createDMail();
					$fetchError = ((strstr($fetchMsg,$LANG->getLL('dmail_error')) === false) ? false : true);
					$row = t3lib_BEfunc::getRecord('sys_dmail',intval($this->sys_dmail_uid));
					$nextCMD?$nextCMD:'cats';
					$theOutput.= t3lib_div::testInt(t3lib_div::_GP('createMailFrom_UID'))?'<input type="hidden" name="CMD" value="'.$nextCMD.'">':'<input type="hidden" name="CMD" value="send_test">';
				} elseif ($quickmail['send']){
					$fetchMsg = $this->createDMail_quick($quickmail);
					$fetchError = ((strstr($fetchMsg,$LANG->getLL('dmail_error')) === false) ? false : true);
					$row = t3lib_BEfunc::getRecord('sys_dmail',$this->sys_dmail_uid);
					$theOutput.= '<input type="hidden" name="CMD" value="send_test">';
				}else {
					//existing dmail
					if($row){
						if(($row['type'] == '1') && ((empty($row['HTMLParams'])) || (empty($row['plainParams'])))){
							//it's a quickmail
							$fetchError = false;
							$theOutput.= '<input type="hidden" name="CMD" value="send_test">';
						} else {
							$fetchMsg = $this->cmd_fetch($row);
							$fetchError = ((strstr($fetchMsg,$LANG->getLL('dmail_error')) === false) ? false : true);
							$theOutput.= ($row['type']==0)?'<input type="hidden" name="CMD" value="'.$nextCMD.'">':'<input type="hidden" name="CMD" value="send_test">';
						}
					}
				}

				$theOutput .= $this->showSteps(2,$totalSteps);
				$theOutput .= $fetchMsg ? $fetchMsg : $LANG->getLL('dmail_wiz2_fetch_success');

				$theOutput.= '<br /><div id="box-1" class="toggleBox">';
				$theOutput.= $row ? $this->directMail_defaultView($row): '';
				$theOutput.= '</div></div>';

				$theOutput = $this->doc->section($LANG->getLL('dmail_wiz2_detail'),$theOutput,1,1,0, TRUE);
				$theOutput.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput.= !empty($row['page'])?'<input type="hidden" name="pages_uid" value="'.$row['page'].'">':'';
				$theOutput.= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';
				$theOutput.= '<input type="submit" value="'.$LANG->getLL('dmail_wiz_next').'" '.($fetchError?'disabled class="next disabled"':' class="next"').'>';
				$theOutput.= '<input type="submit" class="back" value="'.$LANG->getLL('dmail_wiz_back').'" name="back">';
				break;

			case 'cats':
				//shows category if content-based cat
				$theOutput.= $this->showSteps(3,$totalSteps);
				$theOutput.= '<div id="box-1" class="toggleBox">';
				$theOutput.= $this->makeCategoriesForm($row);
				$theOutput.= '</div></div>';

				$theOutput = $this->doc->section($LANG->getLL('dmail_wiz3_cats'),$theOutput,1,1,0, TRUE);

				$theOutput.= '<input type="hidden" name="CMD" value="send_test">';
				$theOutput.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput.= '<input type="hidden" name="pages_uid" value="'.t3lib_div::_GP('pages_uid').'">';
				$theOutput.= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';
				$theOutput.= '<input class="next" type="submit" value="'.$LANG->getLL('dmail_wiz_next').'">';
				$theOutput.= '<input type="submit" class="back" value="'.$LANG->getLL('dmail_wiz_back').'" name="back">';
				break;

			case 'send_test':
			case 'send_mail_test':
				//send test mail
				$theOutput.= $this->showSteps((4-(5-$totalSteps)),$totalSteps);
				if($this->CMD == 'send_mail_test'){
					$theOutput.=$this->cmd_send_mail($row);
				}
				$theOutput.= '<br /><div id="box-1" class="toggleBox">';
				$theOutput.= $this->cmd_testmail($row);
				$theOutput.= '</div></div>';

				$theOutput = $this->doc->section($LANG->getLL('dmail_wiz4_testmail'),$theOutput,1,1,0, TRUE);

				$theOutput.= '<input type="hidden" name="CMD" value="send_mass">';
				$theOutput.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput.= '<input type="hidden" name="pages_uid" value="'.t3lib_div::_GP('pages_uid').'">';
				$theOutput.= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';
				$theOutput.= '<input class="next" type="submit" value="'.$LANG->getLL('dmail_wiz_next').'">';
				$theOutput.= '<input type="submit" class="back" value="'.$LANG->getLL('dmail_wiz_back').'" name="back">';
				break;

			case 'send_mail_final':
			case 'send_mass':

				$theOutput.= $this->showSteps((5-(5-$totalSteps)),$totalSteps);
				if($this->CMD=='send_mail_final'){
					$mailgroup_uid = t3lib_div::_GP('mailgroup_uid');
					if(!empty($mailgroup_uid)){
						$theOutput.= $this->cmd_send_mail($row);
						$theOutput = $this->doc->section($LANG->getLL('dmail_wiz5_sendmass'),$theOutput,1,1,0, TRUE);
						break;
					} else {
						$theOutput .= 'no recipient';
					}
				}
				//send mass, show calendar
				$theOutput.= '<br /><div id="box-1" class="toggleBox">';
				$theOutput.= $this->cmd_finalmail($row);
				$theOutput.= '</div></div>';

				$theOutput = $this->doc->section($LANG->getLL('dmail_wiz5_sendmass'),$theOutput,1,1,0, TRUE);

				$theOutput.= '<input type="hidden" name="CMD" value="send_mail_final">';
				$theOutput.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput.= '<input type="hidden" name="pages_uid" value="'.t3lib_div::_GP('pages_uid').'">';
				$theOutput.= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';
				if($this->CMD =='send_mass'){
					$theOutput.= '<input type="submit" class="back" value="'.$LANG->getLL('dmail_wiz_back').'" name="back">';
				}
				
				break;

			default:
				//choose source newsletter
				$showTabs = array('int','ext','quick','dmail');
				foreach(t3lib_div::trimExplode(',',$GLOBALS['BE_USER']->userTS['tx_directmail.']['hideTabs']) as $hideTabs){
					$showTabs = t3lib_div::removeArrayEntryByValue($showTabs, $hideTabs);
				}
				$theOutput.= $this->showSteps(1,$totalSteps);
				$theOutput.= '<p>'.$LANG->getLL('dmail_wiz1_select_nl_source').'</p><br />';
				$i=1;
				$countTabs = count($showTabs);
				foreach($showTabs as $showTab){
					$open = false;
					if (!$GLOBALS['BE_USER']->userTS['tx_directmail.']['defaultTab']) {
						$GLOBALS['BE_USER']->userTS['tx_directmail.']['defaultTab'] = 'dmail';
					}
					if ( $GLOBALS['BE_USER']->userTS['tx_directmail.']['defaultTab'] == $showTab){
						$open = TRUE;
					}
					switch ($showTab) {
						case 'int':
							$theOutput.= $this->makeFormInternal('box-'.$i,$countTabs,$open);
							break;
						case 'ext':
							$theOutput.= $this->makeFormExternal('box-'.$i,$countTabs,$open);
							break;
						case 'quick':
							$theOutput.= $this->makeFormQuickMail('box-'.$i,$countTabs,$open);
							break;
						case 'dmail':
							$theOutput.= $this->makeListDMail('box-'.$i,$countTabs,$open);
							break;
						default:
							break;
					}
					$i++;
				}
				$theOutput = $this->doc->section($LANG->getLL('dmail_wiz1_new_newsletter'),$theOutput,1,1,0, TRUE);
				$theOutput.= '<input type="hidden" name="CMD" value="info">';
				break;
		}

		return $theOutput;
	}

	/**
	 * print out Javascript for field evaluation
	 *
	 * @param	string		$formname: name of the form
	 * @return	string		HTML with JS script
	 */
	function JSbottom($formname='forms[0]')	{
		if ($this->extJSCODE)	{
			$out.='
			<script language="javascript" type="text/javascript">
				function typo3FormFieldGet() {
					var sendDateTime = document.forms[0]["send_mail_datetime_hr"].value.split(" ");
					var sendHour = sendDateTime[0].split(":");
					var sendDate = sendDateTime[1].split("-");

					document.forms[0]["send_mail_datetime"].value = new Date(sendDate[2],(sendDate[1]-1),sendDate[0],sendHour[0],sendHour[1],00).getTime()/1000;
				}
			</script>
			<script language="javascript" type="text/javascript">'.$this->extJSCODE.'</script>';
			return $out;
		}
	}

	/**
	 * shows the final steps of the process. Show recipient list and calendar library
	 *
	 * @param	array		$row: directmail record
	 * @return	string		HTML
	 */
	function cmd_finalmail($row)	{
		global $TCA, $LANG, $TYPO3_DB, $TBE_TEMPLATE;

		/**
		 * Hook for cmd_finalmail
		 * insert a link to open extended importer
		 */
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'])) {
			$hookObjectsArr = array();
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
			foreach($hookObjectsArr as $hookObj)    {
				if (method_exists($hookObj, 'cmd_finalmail')) {
					$hookContents = $hookObj->cmd_finalmail($this);
					$hookSelectDisabled = $hookObj->selectDisabled;     	
				}
			}
		}

			// Mail groups
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,pid,title',
			'sys_dmail_group',
			'pid='.intval($this->id).
				t3lib_BEfunc::deleteClause('sys_dmail_group'),
			'',
			$TYPO3_DB->stripOrderBy($TCA['sys_dmail_group']['ctrl']['default_sortby'])
			);
		$opt = array();
		$opt[] = '<option></option>';
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{

			$result = $this->cmd_compileMailGroup(intval($row['uid']));
			$count=0;
			$idLists = $result['queryInfo']['id_lists'];
			if (is_array($idLists['tt_address']))	$count+=count($idLists['tt_address']);
			if (is_array($idLists['fe_users']))	$count+=count($idLists['fe_users']);
			if (is_array($idLists['PLAINLIST']))	$count+=count($idLists['PLAINLIST']);
			if (is_array($idLists[$this->userTable]))	$count+=count($idLists[$this->userTable]);
			
			
			$opt[] = '<option value="'.$row['uid'].'">'.htmlspecialchars($row['title'].' (#'.$count.')').'</option>';
		}
		// added disabled. see hook
		$select = '<select name="mailgroup_uid" '.($hookSelectDisabled ? 'disabled' : '').'>'.implode(chr(10),$opt).'</select>';

			// Set up form:
		$msg="";
		$msg.= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'" />';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_final" />';
		$msg.= $LANG->getLL('schedule_mailgroup') . ' '.$select.'<br /><br />';
		
		// put content from hook
		$msg .= $hookContents;
		
		
		$msg.= $LANG->getLL('schedule_time') .
			' <input type="text" id="send_mail_datetime_hr" name="send_mail_datetime_hr'.'" onChange="typo3FormFieldGet();"'.$TBE_TEMPLATE->formWidth(20).'>'.
			tx_directmail_calendarlib::getInputButton ('send_mail_datetime_hr').
			'<input type="hidden" value="'.time().'" name="send_mail_datetime" /><br />';
		
		$this->extJSCODE .= '
		
		document.forms[0]["send_mail_datetime_hr"].value = showLocalDate(document.forms[0]["send_mail_datetime"].value);
		
		function showLocalDate(timestamp)
		{
			var dt = new Date(timestamp * 1000);
			var hour;
			var minute;
			
			if (dt.getHours() < 9) {
				hour = "0"+dt.getHours();
			} else {
				hour = dt.getHours();
			}
			
			if (dt.getMinutes() < 9) {
				minute = "0"+dt.getMinutes();
			} else {
				minute = dt.getMinutes();
			}
			return hour+":"+minute+" "+dt.getDate()+"-"+(dt.getMonth()+1)+"-"+dt.getFullYear();
		}
		
		';
		
		$msg .= '<br/><input type="checkbox" name="testmail" value="1">'.$LANG->getLL('schedule_testmail');
		$msg.= '<br /><br /><input type="Submit" name="mailingMode_mailGroup" value="' . $LANG->getLL('schedule_send_all') . '" onClick="typo3FormFieldGet();" />';
		$msg.=$this->JSbottom();

		$theOutput.= $this->doc->section($LANG->getLL('schedule_select_mailgroup'),fw($msg), 1, 1, 0, TRUE);
		$theOutput.= $this->doc->spacer(20);

		$this->noView=1;
		return $theOutput;
	}

	/**
	 * sending the mail.
	 * if it's a test mail, then will be sent directly.
	 * if it's a mass-send mail, only update the DB record. the dmailer script will send it.
	 *
	 * @param	array		$row: directmal DB record
	 * @return	string		Messages if the mail is sent or planned to sent
	 */
	function cmd_send_mail($row)	{
		global $LANG, $TYPO3_DB;

			// Preparing mailer
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->nonCron = 1;
		$htmlmail->start();
		$htmlmail->dmailer_prepare($row);

		$sentFlag=false;
		if (t3lib_div::_GP('mailingMode_simple'))	{
				// setting Testmail flag
			$htmlmail->testmail = $this->params['testmail'];
				
				// Fixing addresses:
			$addresses = t3lib_div::_GP('SET');
			$addressList = $addresses['dmail_test_email'] ? $addresses['dmail_test_email'] : $this->MOD_SETTINGS['dmail_test_email'];
			$addresses = split(chr(10).'|,|;',$addressList);
			reset($addresses);
			while(list($key,$val)=each($addresses))	{
				$addresses[$key]=trim($val);
				if (!strstr($addresses[$key],'@'))	{
					unset($addresses[$key]);
				}
			}
			$hash = array_flip($addresses);
			$addresses = array_keys($hash);
			$addressList = implode(',', $addresses);

			if ($addressList)	{
					// Sending the same mail to lots of recipients
				$htmlmail->dmailer_sendSimple($addressList);
				$sentFlag=true;
				$theOutput.= $this->doc->section($LANG->getLL('send_sending'),fw($LANG->getLL('send_was_sent'). '<br /><br />' . $LANG->getLL('send_recipients') . '<br />'.$addressList), 1, 1, 0, TRUE);
				$this->noView=1;
			}
		} else {	// extended, personalized emails.
			if ($this->CMD=='send_mail_test')	{
					// setting Testmail flag
				$htmlmail->testmail = $this->params['testmail'];
				
				if (t3lib_div::_GP('tt_address_uid'))	{
					$res = $TYPO3_DB->exec_SELECTquery(
						'tt_address.*',
						'tt_address LEFT JOIN pages ON pages.uid=tt_address.pid',
						'tt_address.uid='.intval(t3lib_div::_GP('tt_address_uid')).
							' AND '.$this->perms_clause.
							t3lib_BEfunc::deleteClause('pages').
							t3lib_BEfunc::BEenableFields('tt_address').
							t3lib_BEfunc::deleteClause('tt_address')
						);
					if ($recipRow = $TYPO3_DB->sql_fetch_assoc($res))	{
						$recipRow = dmailer::convertFields($recipRow);
						$recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories('tt_address',$recipRow['uid']);
						$htmlmail->dmailer_sendAdvanced($recipRow,'t');
						$sentFlag=true;
						$theOutput.= $this->doc->section($LANG->getLL('send_sending'),fw(sprintf($LANG->getLL('send_was_sent_to_name'), $recipRow['name'].htmlspecialchars(' <'.$recipRow['email'].'>'))), 1, 1, 0, TRUE);
						$this->noView=1;
					}
				} elseif (t3lib_div::_GP('sys_dmail_group_uid'))	{
					$result = $this->cmd_compileMailGroup(t3lib_div::_GP('sys_dmail_group_uid'));

					$idLists = $result['queryInfo']['id_lists'];
					$sendFlag=0;
					$sendFlag+=$this->sendTestMailToTable($idLists,'tt_address',$htmlmail);
					$sendFlag+=$this->sendTestMailToTable($idLists,'fe_users',$htmlmail);
					$sendFlag+=$this->sendTestMailToTable($idLists,'PLAINLIST',$htmlmail);
					$sendFlag+=$this->sendTestMailToTable($idLists,$this->userTable,$htmlmail);
					$theOutput.= $this->doc->section($LANG->getLL('send_sending'),fw(sprintf($LANG->getLL('send_was_sent_to_number'), $sendFlag)), 1, 1, 0, TRUE);
					$this->noView=1;
				}
			} else {
				$mailgroup_uid = t3lib_div::_GP('mailgroup_uid');
				if (t3lib_div::_GP('mailingMode_mailGroup') && $this->sys_dmail_uid && intval($mailgroup_uid))	{
						// Update the record:
					$result = $this->cmd_compileMailGroup(intval($mailgroup_uid));
					$query_info=$result['queryInfo'];

					$distributionTime = intval(t3lib_div::_GP('send_mail_datetime'));
					$distributionTime = $distributionTime<time() ? time() : $distributionTime;

					$updateFields = array(
						'scheduled' => $distributionTime,
						'query_info' => serialize($query_info)
						);
						
					if (t3lib_div::_GP('testmail')) {
						$dmail = t3lib_BEfunc::getRecord('sys_dmail',intval($this->sys_dmail_uid),'subject');
						
						$updateFields['subject'] = $this->params['testmail'].' '.$dmail['subject'];
						
						unset($dmail);
					}
						
					$TYPO3_DB->exec_UPDATEquery(
						'sys_dmail',
						'uid='.intval($this->sys_dmail_uid),
						$updateFields
						);

					$sentFlag=true;
					$theOutput.= $this->doc->section($LANG->getLL('send_was_scheduled'),fw($LANG->getLL('send_was_scheduled_for') . ' '.t3lib_BEfunc::datetime($distributionTime)), 1, 1, 0, TRUE);
					$this->noView=1;
				}
			}
		}

			// Setting flags:
		if ($sentFlag && $this->CMD=='send_mail_final')	{
				// Update the record:
			$TYPO3_DB->exec_UPDATEquery(
				'sys_dmail',
				'uid='.intval($this->sys_dmail_uid),
				array('issent' => 1)
				);
		}
		return $theOutput;
	}

	/**
	 * send mail to recipient based on table.
	 *
	 * @param	array		$idLists: list of recipient ID
	 * @param	string		$table: table name
	 * @param	object		$htmlmail: object of the dmailer script
	 * @return	integer		total of sent mail
	 */
	function sendTestMailToTable($idLists,$table,$htmlmail)	{
		$sentFlag=0;
		if (is_array($idLists[$table]))	{
			if ($table!='PLAINLIST')	{
				$recs = tx_directmail_static::fetchRecordsListValues($idLists[$table],$table,'*');
			} else {
				$recs = $idLists['PLAINLIST'];
			}
			reset($recs);
			while(list($k,$rec)=each($recs))	{
				$recipRow = $htmlmail->convertFields($rec);
				$recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories($table,$recipRow['uid']);
				$kc = substr($table,0,1);
				$returnCode = $htmlmail->dmailer_sendAdvanced($recipRow,$kc=='p'?'P':$kc);
				if ($returnCode) {
					$sentFlag++;
				}
			}
		}
		return $sentFlag;
	}

	/**
	 * show the step of sending a test mail
	 *
	 * @param	array		$row: directmail DB record
	 * @return	string		the HTML form
	 */
	function cmd_testmail($row)	{
		global $LANG, $BACK_PATH, $TYPO3_DB, $TBE_TEMPLATE;

		if ($this->params['test_tt_address_uids'])	{
			$intList = implode(',', t3lib_div::intExplode(',',$this->params['test_tt_address_uids']));
			$res = $TYPO3_DB->exec_SELECTquery(
				'tt_address.*',
				'tt_address LEFT JOIN pages ON tt_address.pid=pages.uid',
				'tt_address.uid IN ('.$intList.')'.
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::BEenableFields('tt_address').
					t3lib_BEfunc::deleteClause('tt_address')
				);
			$msg=$LANG->getLL('testmail_individual_msg') . '<br /><br />';
			while ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$requestURI = t3lib_div::getIndpEnv('REQUEST_URI').'&CMD=send_test&sys_dmail_uid='.$this->sys_dmail_uid.'&pages_uid='.$this->pages_uid;
				$msg.='<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[tt_address]['.$row['uid'].']=edit',$BACK_PATH,$requestURI).'">' .
						'<img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />' .
						'</a><a href="index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=send_mail_test&tt_address_uid='.$row['uid'].'">'.
						t3lib_iconWorks::getIconImage('tt_address', $row, $BACK_PATH, ' alt="'.htmlspecialchars($LANG->getLL('dmail_send')).'" title="'.htmlspecialchars($LANG->getLL('dmail_menuItems_testmail')).'" "width="18" height="16" style="margin: 0px 5px; vertical-align: top;"').
						htmlspecialchars($row['name'].' <'.$row['email'].'>'.($row['module_sys_dmail_html']?' html':'')).'</a><br />';
			}
			$theOutput.= $this->doc->section($LANG->getLL('testmail_individual'),fw($msg), 1, 1, 0, TRUE);
			$theOutput.= $this->doc->spacer(20);
		}

		if ($this->params['test_dmail_group_uids'])	{
			$intList = implode(',', t3lib_div::intExplode(',',$this->params['test_dmail_group_uids']));
			$res = $TYPO3_DB->exec_SELECTquery(
				'sys_dmail_group.*',
				'sys_dmail_group LEFT JOIN pages ON sys_dmail_group.pid=pages.uid',
				'sys_dmail_group.uid IN ('.$intList.')'.
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::deleteClause('sys_dmail_group')
				);
			$msg=$LANG->getLL('testmail_mailgroup_msg') . '<br /><br />';
			while ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$msg.='<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=send_mail_test&sys_dmail_group_uid='.$row['uid'].'">'.t3lib_iconWorks::getIconImage('sys_dmail_group', $row, $BACK_PATH, 'width="18" height="16" style="vertical-align: top;"').htmlspecialchars($row['title']).'</a><br />';
					// Members:
				$result = $this->cmd_compileMailGroup(intval($row['uid']));
				$msg.='<table border="0">
				<tr>
					<td style="width: 50px;"></td>
					<td>'.$this->cmd_displayMailGroup_test($result).'</td>
				</tr>
				</table>';
			}
			$theOutput.= $this->doc->section($LANG->getLL('testmail_mailgroup'),fw($msg), 1, 1, 0, TRUE);
			$theOutput.= $this->doc->spacer(20);
		}

		$msg='';
		$msg.= $LANG->getLL('testmail_simple_msg') . '<br /><br />';
		$msg.= '<input'.$TBE_TEMPLATE->formWidth().' type="text" name="SET[dmail_test_email]" value="'.$this->MOD_SETTINGS['dmail_test_email'].'" /><br /><br />';

		$msg.= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'" />';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_test" />';
		$msg.= '<input type="Submit" name="mailingMode_simple" value="' . $LANG->getLL('dmail_send') . '" />';

		$theOutput.= $this->doc->section($LANG->getLL('testmail_simple'),fw($msg), 1, 1, 0, TRUE);

		$this->noView=1;
		return $theOutput;
	}

	/**
	 * display the test mail group, which configured in the configuration module
	 *
	 * @param	array		$result: lists of the recipient IDs based on directmail DB record
	 * @return	string		list of the recipient (in HTML)
	 */
	function cmd_displayMailGroup_test($result)	{
		$count=0;
		$idLists = $result['queryInfo']['id_lists'];
		$out='';
		if (is_array($idLists['tt_address']))	{$out.=$this->getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');}
		if (is_array($idLists['fe_users']))	{$out.=$this->getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');}
		if (is_array($idLists['PLAINLIST']))	{$out.=$this->getRecordList($idLists['PLAINLIST'],'default',1);}
		if (is_array($idLists[$this->userTable]))	{$out.=$this->getRecordList(tx_directmail_static::fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable);}

		return $out;
	}

	/**
	 * show the recipient info and a link to edit it
	 *
	 * @param	array		$listArr: list of recipients ID
	 * @param	string		$table: table name
	 * @param	boolean		$dim: if set, icon will be shaded
	 * @param	boolean		$editLinkFlag: if set, edit link is showed
	 * @return	string		HTML, the table showing the recipient's info
	 */
	function getRecordList($listArr,$table,$dim=0,$editLinkFlag=1)	{
		global $LANG, $BACK_PATH;

		$count=0;
		$lines=array();
		$out='';
		if (is_array($listArr))	{
			$count=count($listArr);
			reset($listArr);
			while(list(,$row)=each($listArr)) {
				$tableIcon = '';
				$editLink = '';
				if ($row['uid']) {
					$tableIcon = '<td>'.t3lib_iconWorks::getIconImage($table,array(),$BACK_PATH,'title="'.($row['uid']?'uid: '.$row['uid']:'').'"',$dim).'</td>';
					if ($editLinkFlag) {
						$requestURI = t3lib_div::getIndpEnv('REQUEST_URI').'&CMD=send_test&sys_dmail_uid='.$this->sys_dmail_uid.'&pages_uid='.$this->pages_uid;
						$editLink = '<td><a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[tt_address]['.$row['uid'].']=edit',$BACK_PATH,$requestURI).'">' .
								'<img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="' . $LANG->getLL('dmail_edit') . '" width="12" height="12" style="margin:0px 5px; vertical-align:top;" title="' . $LANG->getLL('dmail_edit') . '" />' .
								'</a></td>';
					}
				}

				$lines[]='<tr bgcolor="'.$this->doc->bgColor4.'">
				'.$tableIcon.'
				'.$editLink.'
				<td nowrap> '.$row['email'].' </td>
				<td nowrap> '.$row['name'].' </td>
				</tr>';
			}
		}
		if (count($lines))	{
			$out= $LANG->getLL('dmail_number_records') . '<strong>'.$count.'</strong><br />';
			$out.='<table border="0" cellspacing="1" cellpadding="0">'.implode(chr(10),$lines).'</table>';
		}
		return $out;
	}

	/**
	 * get the recipient IDs given only the group ID
	 *
	 * @param	integer		$group_uid: recipient group ID
	 * @return	array		list of the recipient ID
	 */
	function cmd_compileMailGroup($group_uid) {

		$id_lists=array();
		if ($group_uid)	{
			$mailGroup=t3lib_BEfunc::getRecord('sys_dmail_group',$group_uid);
			if (is_array($mailGroup) && $mailGroup['pid']==$this->id)	{
				switch($mailGroup['type'])	{
				case 0:	// From pages
					$thePages = $mailGroup['pages'] ? $mailGroup['pages'] : $this->id;		// use current page if no else
					$pages = t3lib_div::intExplode(',',$thePages);	// Explode the pages
					reset($pages);
					$pageIdArray=array();
					while(list(,$pageUid)=each($pages))	{
						if ($pageUid>0)	{
							$pageinfo = t3lib_BEfunc::readPageAccess($pageUid,$this->perms_clause);
							if (is_array($pageinfo))	{
								$info['fromPages'][]=$pageinfo;
								$pageIdArray[]=$pageUid;
								if ($mailGroup['recursive'])	{
									$pageIdArray=array_merge($pageIdArray,tx_directmail_static::getRecursiveSelect($pageUid,$this->perms_clause));
								}
							}
						}
					}
						// Remove any duplicates
					$pageIdArray=array_unique($pageIdArray);
					$pidList = implode(',',$pageIdArray);
					$info['recursive']=$mailGroup['recursive'];

						// Make queries
					if ($pidList)	{
						$whichTables = intval($mailGroup['whichtables']);
						if ($whichTables&1)	{	// tt_address
							$id_lists['tt_address']=tx_directmail_static::getIdList('tt_address',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&2)	{	// fe_users
							$id_lists['fe_users']=tx_directmail_static::getIdList('fe_users',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($this->userTable && ($whichTables&4))	{	// user table
							$id_lists[$this->userTable]=tx_directmail_static::getIdList($this->userTable,$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&8)	{	// fe_groups
							if (!is_array($id_lists['fe_users'])) $id_lists['fe_users'] = array();
							$id_lists['fe_users'] = array_unique(array_merge($id_lists['fe_users'], tx_directmail_static::getIdList('fe_groups',$pidList,$group_uid,$mailGroup['select_categories'])));
						}
					}
					break;
				case 1: // List of mails
					if ($mailGroup['csv']==1)	{
						$recipients = tx_directmail_static::rearrangeCsvValues(tx_directmail_static::getCsvValues($mailGroup['list']), $this->fieldList);
					} else {
						$recipients = tx_directmail_static::rearrangePlainMails(array_unique(split('[[:space:],;]+',$mailGroup['list'])));
					}
					$id_lists['PLAINLIST'] = tx_directmail_static::cleanPlainList($recipients);
					break;
				case 2:	// Static MM list
					$id_lists['tt_address'] = tx_directmail_static::getStaticIdList('tt_address',$group_uid);
					$id_lists['fe_users'] = tx_directmail_static::getStaticIdList('fe_users',$group_uid);
					$id_lists['fe_users'] = array_unique(array_merge($id_lists['fe_users'],tx_directmail_static::getStaticIdList('fe_groups',$group_uid)));
					if ($this->userTable)	{
						$id_lists[$this->userTable] = tx_directmail_static::getStaticIdList($this->userTable,$group_uid);
					}
					break;
				case 3:	// Special query list
					$mailGroup = $this->update_SpecialQuery($mailGroup);
					$whichTables = intval($mailGroup['whichtables']);
					$table = '';
					if ($whichTables&1) {
						$table = 'tt_address';
					} elseif ($whichTables&2) {
						$table = 'fe_users';
					} elseif ($this->userTable && ($whichTables&4)) {
						$table = $this->userTable;
					}
					if ($table) {
						$id_lists[$table] = tx_directmail_static::getSpecialQueryIdList($this->queryGenerator,$table,$mailGroup);
					}
					break;
				case 4:	//
					$groups = array_unique(tx_directmail_static::getMailGroups($mailGroup['mail_groups'],array($mailGroup['uid']),$this->perms_clause));
					reset($groups);
					while(list(,$v)=each($groups))	{
						$collect=$this->cmd_compileMailGroup($v);
						if (is_array($collect['queryInfo']['id_lists'])) {
							$id_lists = array_merge_recursive($id_lists,$collect['queryInfo']['id_lists']);
						}
					}
					// Make unique entries
					if (is_array($id_lists['tt_address']))	$id_lists['tt_address'] = array_unique($id_lists['tt_address']);
					if (is_array($id_lists['fe_users']))	$id_lists['fe_users'] = array_unique($id_lists['fe_users']);
					if (is_array($id_lists[$this->userTable]) && $this->userTable)	$id_lists[$this->userTable] = array_unique($id_lists[$this->userTable]);
					if (is_array($id_lists['PLAINLIST']))	{$id_lists['PLAINLIST'] = tx_directmail_static::cleanPlainList($id_lists['PLAINLIST']);}
					break;
				}
				
				//TODO: add hook
				/**
				 * Hook for cmd_compileMailGroup
				 * manipulate the generated id_lists
				 */
				if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'])) {
					$hookObjectsArr = array();
					
					foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'] as $classRef) {
						$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
					}
					foreach($hookObjectsArr as $hookObj)    {
						if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
							$temp_lists = $hookObj->cmd_compileMailGroup_postProcess($id_lists, $this); 	
						}
					}
					
					unset ($id_lists);
					$id_lists = $temp_lists;
				}
				
			}
		}
		$outputArray = array(
			'queryInfo' => array('id_lists' => $id_lists)
			);
		return $outputArray;
	}

	/**
	 * update the mailgroup DB record
	 *
	 * @param	array		$mailGroup: mailgroup DB record
	 * @return	array		mailgroup DB record after updated
	 */
	function update_specialQuery($mailGroup) {
		global $LANG, $TYPO3_DB;

		$set = t3lib_div::_GP('SET');
		$queryTable = $set['queryTable'];
		$queryConfig = t3lib_div::_GP('dmail_queryConfig');
		$dmailUpdateQuery = t3lib_div::_GP('dmailUpdateQuery');

		$whichTables = intval($mailGroup['whichtables']);
		$table = '';
		if ($whichTables&1) {
			$table = 'tt_address';
		} elseif ($whichTables&2) {
			$table = 'fe_users';
		} elseif ($this->userTable && ($whichTables&4)) {
			$table = $this->userTable;
		}

		$this->MOD_SETTINGS['queryTable'] = $queryTable ? $queryTable : $table;
		$this->MOD_SETTINGS['queryConfig'] = $queryConfig ? serialize($queryConfig) : $mailGroup['query'];
		$this->MOD_SETTINGS['search_query_smallparts'] = 1;

		if ($this->MOD_SETTINGS['queryTable'] != $table) {
			$this->MOD_SETTINGS['queryConfig'] = '';
		}

		if ($this->MOD_SETTINGS['queryTable'] != $table || $this->MOD_SETTINGS['queryConfig'] != $mailGroup['query']) {
			$whichTables = 0;
			if ($this->MOD_SETTINGS['queryTable'] == 'tt_address') {
				$whichTables = 1;
			} elseif ($this->MOD_SETTINGS['queryTable'] == 'fe_users') {
				$whichTables = 2;
			} elseif ($this->MOD_SETTINGS['queryTable'] == $this->userTable) {
				$whichTables = 4;
			}
			$updateFields = array(
				'whichtables' => intval($whichTables),
				'query' => $this->MOD_SETTINGS['queryConfig']
			);
			$res_update = $TYPO3_DB->exec_UPDATEquery(
				'sys_dmail_group',
				'uid='.intval($mailGroup['uid']),
				$updateFields
				);
			$mailGroup = t3lib_BEfunc::getRecord('sys_dmail_group',$mailGroup['uid']);
		}
		return $mailGroup;
	}

	/**
	 * show the categories table for user to categorize the directmail content (TYPO3 content)
	 * @param	array		$row: the dmail row.
	 *
	 * @return	string		HTML form showing the categories
	 */
	function makeCategoriesForm($row){
		global $BACK_PATH, $TYPO3_DB, $LANG;
		$indata = t3lib_div::_GP('indata');
		if (is_array($indata['categories']))	{
			$data=array();
			reset($indata['categories']);

			while(list($recUid,$recValues)=each($indata['categories']))	{
				reset($recValues);
				$enabled = array();
				while(list($k,$b)=each($recValues)) {
					if ($b)	{
						$enabled[] = $k;
					}
				}
				$data['tt_content'][$recUid]['module_sys_dmail_category'] = implode(',',$enabled);
			}
			$tce = t3lib_div::makeInstance('t3lib_TCEmain');
			$tce->stripslashes_values=0;
			$tce->start($data,Array());
			$tce->process_datamap();
			
			//remove cache
			$tce->clear_cache('pages',t3lib_div::_GP('pages_uid'));
			$out = $this->cmd_fetch($row);
		}
        //[ToDo] Perhaps we should here check if TV is installed and fetch cotnent from that instead of the old Columns...
		$res = $TYPO3_DB->exec_SELECTquery(
			'colPos, CType, uid, pid, header, bodytext, module_sys_dmail_category',
			'tt_content',
			'pid='.intval($this->pages_uid).
				t3lib_BEfunc::deleteClause('tt_content').
				' AND NOT hidden',
			'',
			'colPos,sorting'
			);
		if (!$TYPO3_DB->sql_num_rows($res))	{
			$theOutput.= $this->doc->section($LANG->getLL('nl_cat'),$LANG->getLL('nl_cat_msg1'),1,1,0,TRUE);
		} else {
			$out='';
			$colPosVal=99;
			while($row=$TYPO3_DB->sql_fetch_assoc($res))	{
				$row_categories = '';
				$resCat = $TYPO3_DB->exec_SELECTquery(
					'uid_foreign',
					'sys_dmail_ttcontent_category_mm',
					'uid_local='.$row['uid']
					);
				while($rowCat=$TYPO3_DB->sql_fetch_assoc($resCat)) {
					$row_categories .= $rowCat['uid_foreign'].',';
				}
				$row_categories = t3lib_div::rm_endComma($row_categories);

				$out.='<tr><td colspan="3" style="height: 15px;"></td></tr>';
				if ($colPosVal!=$row['colPos'])	{
					$out.='<tr><td colspan="3" bgcolor="'.$this->doc->bgColor5.'">'.fw($LANG->getLL('nl_l_column').': <strong>'.t3lib_BEfunc::getProcessedValue('tt_content','colPos',$row['colPos']).'</strong>').'</td></tr>';
					$colPosVal=$row["colPos"];
				}
				$out.='<tr>';
				$out.='<td valign="top" width="75%">'.fw(t3lib_iconWorks::getIconImage("tt_content", $row, $BACK_PATH, 'width="18" height="16" title="'.htmlspecialchars(t3lib_BEfunc::getProcessedValue('tt_content','CType',$row['CType'])).'" style="vertical-align: top;"').
					$row['header'].'<br />'.t3lib_div::fixed_lgd(strip_tags($row['bodytext']),200).'<br />').'</td>';

				$out.='<td>  </td><td nowrap valign="top">';
				$out_check='';
				if ($row['module_sys_dmail_category']) {
					$out_check.='<font color="red"><strong>'.$LANG->getLL('nl_l_ONLY').'</strong></font>';
				} else {
					$out_check.='<font color="green"><strong>'.$LANG->getLL('nl_l_ALL').'</strong></font>';
				}
				$out_check.='<br />';

				$this->categories = tx_directmail_static::makeCategories('tt_content', $row, $this->sys_language_uid);
				reset($this->categories);
				while(list($pKey,$pVal)=each($this->categories))	{
					$out_check.='<input type="hidden" name="indata[categories]['.$row["uid"].']['.$pKey.']" value="0"><input type="checkbox" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="1"'.(t3lib_div::inList($row_categories,$pKey) ?' checked':'').'> '.$pVal.'<br />';
				}
				$out.=fw($out_check).'</td></tr>';
			}
			$out='<table border="0" cellpadding="0" cellspacing="0">'.$out.'</table>';
			$out.='<input type="hidden" name="pages_uid" value="'.$this->pages_uid.'"><input type="hidden" name="CMD" value="'.$this->CMD.'"><br /><input type="submit" name="update_cats" value="'.$LANG->getLL('nl_l_update').'">';
			$theOutput.= $this->doc->section($LANG->getLL('nl_cat').t3lib_BEfunc::cshItem($this->cshTable,'assign_categories',$BACK_PATH), $out, 1, 1, 0, TRUE);
		}
		return $theOutput;
	}

	/**
	 * makes box for internal page. (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox: total of all boxes
	 * @return	string		HTML with list of internal pages
	 */
	function makeFormInternal($boxID,$totalBox,$open=FALSE){
		global $BACK_PATH, $LANG;
		$imgSrc = t3lib_iconWorks::skinImg(
			$BACK_PATH,
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div class="box"><div class="toggleTitle">';
		$output.= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$LANG->getLL('dmail_wiz1_internal_page').'</a>';
		$output.= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';
		$output.= $this->cmd_news();
		$output.= '</div></div></div>';
		return $output;
	}

	/**
	 * make input form for external URL (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox:total of the boxes
	 * @return	string		HTML input form for inputing the external page information
	 */
	function makeFormExternal($boxID,$totalBox,$open=FALSE){
		global $BACK_PATH, $LANG, $TBE_TEMPLATE;
		$imgSrc = t3lib_iconWorks::skinImg(
			$BACK_PATH,
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div class="box"><div class="toggleTitle">';
		$output.= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$LANG->getLL('dmail_wiz1_external_page').'</a>';
		$output.= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';
					// Create
		$out =  $LANG->getLL('dmail_HTML_url') . '<br />
				<input type="text" value="http://" name="createMailFrom_HTMLUrl"'.$TBE_TEMPLATE->formWidth(40).' /><br />' .
				$LANG->getLL('dmail_plaintext_url') . '<br />
				<input type="text" value="http://" name="createMailFrom_plainUrl"'.$TBE_TEMPLATE->formWidth(40).' /><br />' .
				$LANG->getLL('dmail_subject') . '<br />' .
				'<input type="text" value="' . $LANG->getLL('dmail_write_subject') . '" name="createMailFrom_URL" onFocus="this.value=\'\';"'.$TBE_TEMPLATE->formWidth(40).' /><br />' .
				(($this->error == 'no_valid_url')?('<br /><b>'.$LANG->getLL('dmail_no_valid_url').'</b><br /><br />'):'') .
				'<input type="submit" value="'.$LANG->getLL("dmail_createMail").'" />
				<input type="hidden" name="fetchAtOnce" value="1">';
		$output.= '<h3>'.$LANG->getLL('dmail_dovsk_crFromUrl').t3lib_BEfunc::cshItem($this->cshTable,'create_directmail_from_url',$BACK_PATH).'</h3>';
		$output.= '<br />'.$out;


		$output.= '</div></div>';
		return $output;
	}

	/**
	 * makes input form for the quickmail (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox: total of the boxes
	 * @return	string		HTML input form for the quickmail
	 */
	function makeFormQuickMail($boxID,$totalBox,$open=FALSE){
		global $BACK_PATH, $LANG;
		$imgSrc = t3lib_iconWorks::skinImg(
			$BACK_PATH,
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div class="box"><div class="toggleTitle">';
		$output.= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$LANG->getLL('dmail_wiz1_quickmail').'</a>';
		$output.= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';
		$output.= '<h3>'.$LANG->getLL('dmail_wiz1_quickmail_header').'</h3>';
		$output.= $this->cmd_quickmail();
		$output.= '</div></div>';
		return $output;
	}

	/**
	 * list all direct mail, which have not been sent (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox: total of the boxes
	 * @return	string		HTML lists of all existing dmail records
	 */
	function makeListDMail($boxID,$totalBox,$open=FALSE){
		global $BACK_PATH, $LANG, $TYPO3_DB, $TCA;

		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,pid,subject,tstamp,issent,renderedsize,attachment,type',
			'sys_dmail',
			'pid = '.intval($this->id).
				' AND scheduled=0 AND issent=0'.t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			$TYPO3_DB->stripOrderBy($TCA['sys_dmail']['ctrl']['default_sortby'])
		);

		$tblLines = array();
		$tblLines[] = array(
			'',
			$LANG->getLL('nl_l_subject'),
			$LANG->getLL('nl_l_lastM'),
			$LANG->getLL('nl_l_sent'),
			$LANG->getLL('nl_l_size'),
			$LANG->getLL('nl_l_attach'),
			$LANG->getLL('nl_l_type')
		);
		while($row = $TYPO3_DB->sql_fetch_assoc($res)){
			$tblLines[] = array(
				t3lib_iconWorks::getIconImage('sys_dmail',$row, $BACK_PATH, ' style="vertical-align: top;"'),
				$this->linkDMail_record($row['subject'],$row['uid']),
				t3lib_BEfunc::date($row['tstamp']),
				($row['issent'] ? $LANG->getLL('dmail_yes') : $LANG->getLL('dmail_no')),
				($row['renderedsize'] ? t3lib_div::formatSize($row['renderedsize']) : ''),
				($row['attachment'] ? '<img '.t3lib_iconWorks::skinImg($BACK_PATH, t3lib_extMgm::extRelPath($this->extKey).'res/gfx/attach.gif', 'width="9" height="13"').' alt="'.htmlspecialchars($LANG->getLL('nl_l_attach')).'" title="'.htmlspecialchars($row['attachment']).'" width="9" height="13">' : ''),
				($row['type'] ? $LANG->getLL('nl_l_tUrl') : $LANG->getLL('nl_l_tPage'))
			);
		}

		$imgSrc = t3lib_iconWorks::skinImg(
			$BACK_PATH,
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div id="header" class="box"><div class="toggleTitle">';
		$output.= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$LANG->getLL('dmail_wiz1_list_dmail').'</a>';
		$output.= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';
		$output.= '<h3>'.$LANG->getLL('dmail_wiz1_list_header').'</h3>';
		$output.= tx_directmail_static::formatTable($tblLines,array(),1,array(1,1,1,0,0,1,0),'border="0" cellspacing="0" cellpadding="3"');
		$output.= '</div></div>';
		return $output;
	}

	/**
	 * show the quickmail input form (first step)
	 *
	 * @return	string		HTML input form
	 */
	function cmd_quickmail()	{
		global $BE_USER, $LANG;

		$theOutput='';
		$indata = t3lib_div::_GP('quickmail');
			// Set up form:
		$msg='';
		$msg.= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$msg.= $LANG->getLL('quickmail_sender_name') . '<br /><input type="text" name="quickmail[senderName]" value="'.($indata['senderName']?$indata['senderName']:$BE_USER->user['realName']).'"'.$this->doc->formWidth().' /><br />';
		$msg.= $LANG->getLL('quickmail_sender_email') . '<br /><input type="text" name="quickmail[senderEmail]" value="'.($indata['senderEmail']?$indata['senderEmail']:$BE_USER->user['email']).'"'.$this->doc->formWidth().' /><br />';
		$msg.= $LANG->getLL('dmail_subject') . '<br /><input type="text" name="quickmail[subject]" value="'.$indata['subject'].'"'.$this->doc->formWidth().' /><br />';
		$msg.= $LANG->getLL('quickmail_message') . '<br /><textarea rows="20" name="quickmail[message]"'.$this->doc->formWidthText().'>'.t3lib_div::formatForTextarea($indata['message']).'</textarea><br />';
		$msg.= $LANG->getLL('quickmail_break_lines') . ' <input type="checkbox" name="quickmail[breakLines]" value="1"'.($indata['breakLines']?' checked="checked"':'').' /><br /><br />';
		$msg.= '<input type="Submit" name="quickmail[send]" value="' . $LANG->getLL('dmail_wiz_next') . '" />';

		$theOutput.= '<h3>'.$LANG->getLL('dmail_menu_quickMail').'</h3><br />'.$msg;

		return $theOutput;
	}

	/**
	 * show the list of existing directmail records, which haven't been sent
	 *
	 * @return	string		HTML
	 */
	function cmd_news () {
		global $LANG, $TYPO3_DB, $BACK_PATH;
		
			// Here the list of subpages, news, is rendered
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,doktype,title,abstract',
			'pages',
			'pid='.intval($this->id).
				' AND doktype IN ('.$GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'].')'.
				' AND '.$this->perms_clause.
				t3lib_BEfunc::BEenableFields('pages').
				t3lib_BEfunc::deleteClause('pages'),
			'',
			'sorting'
			);
		if (!$TYPO3_DB->sql_num_rows($res))	{
			$theOutput.= $this->doc->section($LANG->getLL('nl_select'),$LANG->getLL('nl_select_msg1'),0,1);
		} else {
			$outLines = array();
			while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				
				$iconPreviewHTML = '<a href="#" onClick="'.t3lib_BEfunc::viewOnClick($row['uid'],$BACK_PATH,t3lib_BEfunc::BEgetRootLine($row['uid']),'','',$this->implodedParams['HTMLParams']).'"><img src="../res/gfx/preview_html.gif" width="16" height="16" alt="" style="vertical-align:top;" title="'.$LANG->getLL('nl_viewPage_HTML').'"/></a>';
				$iconPreviewText = '<a href="#" onClick="'.t3lib_BEfunc::viewOnClick($row['uid'],$BACK_PATH,t3lib_BEfunc::BEgetRootLine($row['uid']),'','',$this->implodedParams['plainParams']).'"><img src="../res/gfx/preview_txt.gif" width="16" height="16" alt="" style="vertical-align:top;" title="'.$LANG->getLL('nl_viewPage_TXT').'"/></a>';
				
				//switch
				switch ($this->params['sendOptions']) {
					case 1:
						$iconPreview = $iconPreviewText;
						break;
					case 2:
						$iconPreview = $iconPreviewHTML;
						break;
					case 3:
					default:
						$iconPreview = $iconPreviewHTML.'&nbsp;&nbsp;'.$iconPreviewText;
					break;
				}
				
				$outLines[] = array(
					'<a href="index.php?id='.$this->id.'&createMailFrom_UID='.$row['uid'].'&fetchAtOnce=1&CMD=info">'.t3lib_iconWorks::getIconImage('pages', $row, $BACK_PATH, ' title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row['uid'],$this->perms_clause,20)).'" style="vertical-align: top;"').$row['title'].'</a>',
					'<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[pages]['.$row['uid'].']=edit&edit_content=1',$BACK_PATH,"",1).'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" style="vertical-align:top;" title="'.$LANG->getLL("nl_editPage").'" /></a>',
					$iconPreview
					);
			}
			$out = tx_directmail_static::formatTable($outLines, array(), 0, array(1,1,1));
			$theOutput.= $this->doc->section($LANG->getLL('dmail_dovsk_crFromNL').t3lib_BEfunc::cshItem($this->cshTable,'select_newsletter',$BACK_PATH), $out, 1, 1, 0, TRUE);
		}
			// Create a new page
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section($LANG->getLL('nl_create').t3lib_BEfunc::cshItem($this->cshTable,'create_newsletter',$BACK_PATH),'<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[pages]['.$this->id.']=new&edit[tt_content][prev]=new',$BACK_PATH,'').'"><b>'.$LANG->getLL('nl_create_msg1').'</b></a>', 1, 1, 0, TRUE);
		return $theOutput;
	}

	/**
	 * wrap a string as a link
	 *
	 * @param	string		$str: String to be linked
	 * @param	integer		$uid: UID of the directmail record
	 * @return	string		the link
	 */
	function linkDMail_record($str,$uid)	{
		return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&CMD=info">'.$str.'</a>';
	}

	/**
	 * Set up URL variables for this $row.
	 *
	 * @param	array		$row: directmail DB record
	 * @return	void		set the global variable url_plain and url_html
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
	 * get the charset of a page
	 *
	 * @param	integer		$pageId: ID of a page
	 * @return	string		the charset of a page
	 */
	function getPageCharSet($pageId)	{
		global $TYPO3_CONF_VARS;

		$rootline = $this->sys_page->getRootLine($pageId);
		$this->tmpl->forceTemplateParsing = 1;
		$this->tmpl->start($rootline);
		$charSet = $this->tmpl->setup['config.']['metaCharset']?$this->tmpl->setup['config.']['metaCharset']:($TYPO3_CONF_VARS['BE']['forceCharset']?$TYPO3_CONF_VARS['BE']['forceCharset']:'iso-8859-1');

		return $charSet;
	}

	/**
	 * add username and password for a password secured page
	 * username and password are configured in the configuration module
	 *
	 * @param	string		$url: the URL
	 * @return	string		the new URL with username and password
	 */
	function addUserPass($url)	{
		$user = $this->params['http_username'];
		$pass = $this->params['http_password'];

		if ($user && $pass && substr($url,0,7)=='http://')	{
			$url = 'http://'.$user.':'.$pass.'@'.substr($url,7);
		}
		return $url;
	}

	/**
	 * fetch content of a page (only internal and external page)
	 *
	 * @param	array		directmail DB record
	 * @param	boolean		...
	 * @return	string		error or warning message during fetching the content
	 */
	function cmd_fetch($row,$embed=FALSE)	{
		global $TCA, $TYPO3_DB, $LANG;

		$theOutput = '';
		$errorMsg = '';
		$warningMsg = '';
		$content ='';
		$success = FALSE;

			// Make sure long_link_rdct_url is consistent with use_domain.
		$this->urlbase = tx_directmail_static::getUrlBase($row['use_domain']);
		$row['long_link_rdct_url'] = $this->urlbase;

			// Compile the mail
		$htmlmail = t3lib_div::makeInstance('dmailer');
		if($this->params['enable_jump_url']) {
			$htmlmail->jumperURL_prefix = $this->urlbase.'?id='.$row['page'].'&rid=###SYS_TABLE_NAME###_###USER_uid###&mid=###SYS_MAIL_ID###&aC=###SYS_AUTHCODE###&jumpurl=';
			$htmlmail->jumperURL_useId=1;
		}
		if($this->params['enable_mailto_jump_url']) {
			$htmlmail->jumperURL_useMailto=1;
		}

		$htmlmail->start();
		$htmlmail->charset = $row['charset'];
		$htmlmail->useBase64();
		$htmlmail->http_username = $this->params['http_username'];
		$htmlmail->http_password = $this->params['http_password'];
		$htmlmail->includeMedia = $row['includeMedia'];

		if ($this->url_plain) {
			$content = t3lib_div::getURL($this->addUserPass($this->url_plain));
			$htmlmail->addPlain($content);
			if (!$content || !$htmlmail->theParts['plain']['content']) {
				$errorMsg .= '<br /><strong>' . $LANG->getLL('dmail_no_plain_content') . '</strong>';
			} elseif (!strstr(base64_decode($htmlmail->theParts['plain']['content']),'<!--DMAILER_SECTION_BOUNDARY')) {
				$warningMsg .= '<br /><strong>' . $LANG->getLL('dmail_no_plain_boundaries') . '</strong>';
			}
		}
		if ($this->url_html) {
			$success = $htmlmail->addHTML($this->url_html);    // Username and password is added in htmlmail object
			if (!$row['charset']) {		// If no charset was set, we have an external page.
					// Try to auto-detect the charset of the message
				$matches = array();
				$res = preg_match('/<meta[\s]+http-equiv="Content-Type"[\s]+content="text\/html;[\s]+charset=([^"]+)"/m', $htmlmail->theParts['html_content'], $matches);
				if ($res==1) {
					$htmlmail->charset = $matches[1];
				} elseif (isset($this->params['direct_mail_charset'])) {
					$htmlmail->charset = $LANG->csConvObj->parse_charset($this->params['direct_mail_charset']);
				} else {
					$htmlmail->charset = 'iso-8859-1';
				}
				$htmlmail->useBase64();   // Reset content-type headers with new charset
			}
			if ($htmlmail->extractFramesInfo()) {
				$errorMsg .= '<br /><strong>' . $LANG->getLL('dmail_frames_not allowed') . '</strong>';
			} elseif (!$success || !$htmlmail->theParts['html']['content']) {
				$errorMsg .= '<br /><strong>' . $LANG->getLL('dmail_no_html_content') . '</strong>';
			} elseif (!strstr(base64_decode($htmlmail->theParts['html']['content']),'<!--DMAILER_SECTION_BOUNDARY')) {
				$warningMsg .= '<br /><strong>' . $LANG->getLL('dmail_no_html_boundaries') . '</strong>';
			}
		}

		$attachmentArr = t3lib_div::trimExplode(',', $row['attachment'],1);
		if (count($attachmentArr))	{
			t3lib_div::loadTCA('sys_dmail');
			$upath = $TCA['sys_dmail']['columns']['attachment']['config']['uploadfolder'];
			while(list(,$theName)=each($attachmentArr))	{
				$theFile = PATH_site.$upath.'/'.$theName;
				if (@is_file($theFile))	{
					$htmlmail->addAttachment($theFile, $theName);
				}
			}
		}

		if (!$errorMsg) {
				// Update the record:
			$htmlmail->theParts['messageid'] = $htmlmail->messageid;
			$mailContent = serialize($htmlmail->theParts);
			$updateFields = array(
				'issent' => 0,
				'charset' => $htmlmail->charset,
				'mailContent' => $mailContent,
				'renderedSize' => strlen($mailContent),
				'long_link_rdct_url' => $this->urlbase
				);
			$TYPO3_DB->exec_UPDATEquery(
				'sys_dmail',
				'uid='.intval($this->sys_dmail_uid),
				$updateFields
				);

			/*
				// Read again:
			$res = $TYPO3_DB->exec_SELECTquery(
				'*',
				'sys_dmail',
				'pid='.intval($this->id).
					' AND uid='.intval($this->sys_dmail_uid).
					t3lib_BEfunc::deleteClause('sys_dmail')
					);
			$row = $TYPO3_DB->sql_fetch_assoc($res);
			*/

			if ($warningMsg)	{
				$theOutput .= $this->doc->section($LANG->getLL('dmail_warning'), $warningMsg.'<br /><br />');
			}

		} else {
			$theOutput .= $this->doc->section($LANG->getLL('dmail_error'), $errorMsg.'<br /><br />'.($embed?'':$this->back));
			$this->noView = 1;
		}

		return $theOutput;
	}

	/**
	 * shows the infos of a directmail record
	 *
	 * @param	array		$row: directmail DB record
	 * @return	string		HTML
	 */
	function directMail_defaultView($row)	{
		global $LANG, $BE_USER, $BACK_PATH;

			// Render record:
		$dmailTitle=t3lib_iconWorks::getIconImage('sys_dmail',$row,$BACK_PATH,'style="vertical-align: top;"').$row['subject'];
		$out='';
		$Eparams='&edit[sys_dmail]['.$row['uid'].']=edit';
		$out .= '<tr><td colspan=3 bgColor="' . $this->doc->bgColor5 . '" valign=top>'.tx_directmail_static::fName('subject').' <b>'.t3lib_div::fixed_lgd($row['subject'],60).'  </b>'.'</td></tr>';
		$nameArr = explode(',','from_name,from_email,replyto_name,replyto_email,organisation,return_path,priority,attachment,type,page,sendOptions,includeMedia,flowedFormat,plainParams,HTMLParams,encoding,charset,issent,renderedsize');
		while(list(,$name)=each($nameArr))	{
			$out.='<tr><td bgColor="'.$this->doc->bgColor4.'">'.tx_directmail_static::fName($name).'</td><td bgColor="'.$this->doc->bgColor4.'">'.str_replace('Yes', $LANG->getLL('yes'),t3lib_BEfunc::getProcessedValue('sys_dmail',$name,$row[$name])).'</td></tr>';
		}
		$out='<table border="0" cellpadding="1" cellspacing="1" width="460" bgcolor="'.$this->doc->bgColor5.'">'.$out.'</table>';
		if (!$row['issent'])	{
			if ($BE_USER->check('tables_modify','sys_dmail')) {
				$retUrl = 'returnUrl='.rawurlencode(t3lib_div::linkThisScript(array('sys_dmail_uid' => $row['uid'], 'createMailFrom_UID' => '', 'createMailFrom_URL' => '')));
				$editOnClick = 'document.location=\''.$BACK_PATH.'alt_doc.php?'.$retUrl.$Eparams.'\'; return false;';
				$out.='<br /><a href="#" onClick="' .$editOnClick . '"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />'.'<b>'.$LANG->getLL('dmail_edit').'</b>'.'</a>';
			} else {
				$out.='<br /><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />'.'('.$LANG->getLL('dmail_noEdit_noPerms').')';
			}
		} else {
			$out.='<br /><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />'.'('.$LANG->getLL('dmail_noEdit_isSent').')';
		}

		$theOutput.= $this->doc->section($LANG->getLL('dmail_view').' '.$dmailTitle, $out, 1, 1, 0, TRUE);

		return $theOutput;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod2/class.tx_directmail_dmail.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod2/class.tx_directmail_dmail.php']);
}

?>