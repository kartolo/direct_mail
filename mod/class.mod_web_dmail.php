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
 * @author	Kasper Skårhøj <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * $Id$
 */

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_tstemplate.php');
require_once (PATH_t3lib.'class.t3lib_page.php');
require_once(PATH_t3lib.'class.t3lib_timetrack.php');
require_once(t3lib_extMgm::extPath('direct_mail').'mod/class.mailselect.php');
require_once(t3lib_extMgm::extPath('direct_mail').'mod/class.dmailer.php');

class mod_web_dmail extends t3lib_SCbase {
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

	/**
	 * @return	[type]		...
	 */
	function init()	{
		global $LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS, $TYPO3_DB, $MCONF;
		
		$this->MCONF = $MCONF;
		
		$this->include_once[]=PATH_t3lib.'class.t3lib_tcemain.php';
		$this->include_once[]=PATH_t3lib.'class.t3lib_pagetree.php';
		$this->include_once[]=PATH_t3lib.'class.t3lib_readmail.php';
		
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
					t3lib_pageSelect::enableFields('sys_language').
					t3lib_pageSelect::enableFields('static_languages')
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
		$this->updatePageTS();
	}
	
	/**
	 * Prints out the module HTML
	 *
	 * @return	[type]		...
	 */
	function printContent()	{
		$this->content.=$this->doc->endPage();
		echo $this->content;
	}
	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	[type]		...
	 */
	function menuConfig()	{
		global $LANG,$TYPO3_CONF_VARS,$BE_USER;

		$this->MOD_MENU = Array (
			'dmail_mode' => Array (
				'news' => $LANG->getLL('dmail_menu_newsletters'),
				'direct' => $LANG->getLL('dmail_menu_direct_mails'),
				'recip' => $LANG->getLL('dmail_menu_list'),
				'mailerengine' => $LANG->getLL('dmail_menu_mailerengine'),
				'quick' => $LANG->getLL('dmail_menu_quickMail'),
				'convert' => $LANG->getLL('dmail_menu_convert_categories'),
				'conf' => $LANG->getLL('dmail_menu_conf'),
				)
			);
			
			// Hook for preprocessing of the content for formmails:
		if (is_array($TYPO3_CONF_VARS['EXT']['directmail']['append-functions'])) {
			foreach($TYPO3_CONF_VARS['EXT']['directmail']['append-functions'] as $_funcRef) {
				$_params = array();
				$temp = t3lib_div::callUserFunction($_funcRef,$_params,$this);
				$this->MOD_MENU['dmail_mode'] = $this->MOD_MENU['dmail_mode'] +$temp;
			}
		}
		parent::menuConfig();
	}	
	/**
	 * Creates a directmail entry in th DB.
	 * @return	[type]		...
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
			$dmail['sys_dmail']['NEW']['long_link_rdct_url'] = $this->getUrlBase($this->params['use_domain']);
			
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
				
					// Avoid parse_url warning at this stage
				error_reporting (E_ALL ^ E_NOTICE ^ E_WARNING);
				$dmail['sys_dmail']['NEW']['plainParams'] = t3lib_div::_GP('createMailFrom_plainUrl');
				$urlParts = parse_url($dmail['sys_dmail']['NEW']['plainParams']);
				if (!$dmail['sys_dmail']['NEW']['plainParams'] || !$urlParts || !$urlParts['host']) {
						// No plain text url
					$dmail['sys_dmail']['NEW']['plainParams'] = '';
					$dmail['sys_dmail']['NEW']['sendOptions']&=254;
				}
				$dmail['sys_dmail']['NEW']['HTMLParams'] = t3lib_div::_GP('createMailFrom_HTMLUrl');
				$urlParts = parse_url($dmail['sys_dmail']['NEW']['HTMLParams']);				
				if (!$dmail['sys_dmail']['NEW']['HTMLParams'] || !$urlParts || !$urlParts['host']) {
						// No html url
					$dmail['sys_dmail']['NEW']['HTMLParams'] = '';
					$dmail['sys_dmail']['NEW']['sendOptions']&=253;
				}
				error_reporting (E_ALL ^ E_NOTICE);
				
				$dmail['sys_dmail']['NEW']['pid'] = $this->pageinfo['uid'];
			}
			
			if ($dmail['sys_dmail']['NEW']['pid'] && $dmail['sys_dmail']['NEW']['sendOptions']) {
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values=0;
				$tce->start($dmail,Array());
				$tce->process_datamap();
				$this->sys_dmail_uid = $tce->substNEWwithIDs['NEW'];
			} else {
				if (!$dmail['sys_dmail']['NEW']['sendOptions']) {
					$this->error = 'no_valid_url';
				}
			}
		}
	}

	/**
	 * The main function.
	 *
	 * @return	void		...
	 */
	function main()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;
		
		$this->CMD = t3lib_div::_GP('CMD');
		$this->pages_uid=t3lib_div::_GP('pages_uid');
		$this->sys_dmail_uid=t3lib_div::_GP('sys_dmail_uid');
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;
		
		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{
		
			// Draw the header.
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="POST">';
			
			// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						window.location.href = URL;
					}
					function jumpToUrlD(URL)        {
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

			$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath'],0).'<br />'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],50);
			
			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			
			$module = $this->pageinfo['module'];
			if (!$module)	{
				$pidrec=t3lib_BEfunc::getRecord('pages',intval($this->pageinfo['pid']));
				$module=$pidrec['module'];
			}
			if ($module == 'dmail') {
				$this->content.=$this->doc->section('',$this->doc->funcMenu($headerSection, t3lib_BEfunc::getFuncMenu($this->id,'SET[dmail_mode]',$this->MOD_SETTINGS['dmail_mode'],$this->MOD_MENU['dmail_mode']).t3lib_BEfunc::cshItem($this->cshTable,'',$BACK_PATH)));

					// Render content:
				$this->createDMail();
				$this->moduleContent();
			} else {
				$this->content.=$this->doc->section($LANG->getLL('dmail_folders').t3lib_BEfunc::cshItem($this->cshTable,'folders',$BACK_PATH), $this->modList['list'], 1, 1, 0 , TRUE);
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
	 * @return	String		The compiled content of the module.
	 */
	function moduleContent() {
		global $TYPO3_CONF_VARS, $LANG;
		
		if (t3lib_div::inList($TYPO3_CONF_VARS['FE']['content_doktypes'],$this->pageinfo['doktype']))	{		// Regular page, show menu to create a direct mail from this page.
			if ($this->pageinfo['group_id']>0 || $this->pageinfo['hidden'])	{
				$theOutput.= $this->doc->section($LANG->getLL('dmail_newsletters'),'<span class="typo3-red">'.$LANG->getLL('dmail_noCreateAccess').'</span>',0,1);
			} else {
				if (is_array($this->modList['rows']))	{
					$isNewsletterPage=0;
					reset($this->modList['rows']);
					while(list(,$rData)=each($this->modList['rows']))	{
						if ($rData['uid']==$this->pageinfo['pid'])	{
							$isNewsletterPage=1;
						}
					}
				}
				if ($isNewsletterPage)	{
					header('Location: index.php?id='.$this->pageinfo['pid'].'&CMD=displayPageInfo&pages_uid='.$this->pageinfo['uid'].'&SET[dmail_mode]=news');
					exit;
				}
			}
		} elseif ($this->pageinfo['doktype']==254 && $this->pageinfo['module']=='dmail')	{	// Direct mail module
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
	 * @return	[type]		...
	 */
	function mailModule_main()	{
		global $LANG, $TYPO3_DB;
		
		//$theOutput.=$this->doc->divider(5);
		$mode = $this->MOD_SETTINGS['dmail_mode'];

		if (!$this->sys_dmail_uid || $mode!='direct')	{
				// COMMAND:
			switch($this->CMD) {
			case 'displayPageInfo':
				$theOutput.= $this->cmd_displayPageInfo();
				break;
			case 'displayUserInfo':
				$theOutput.= $this->cmd_displayUserInfo();
				break;
			case 'displayMailGroup':
				$result = $this->cmd_compileMailGroup(intval(t3lib_div::_GP('group_uid')));
				$theOutput.= $this->cmd_displayMailGroup($result);
				break;
			case 'displayImport':
				$theOutput.= $this->cmd_displayImport();
				break;
			default:
				$theOutput.= $this->cmd_default($mode);
				break;
			}
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
			$this->back = '<input type="Submit" value="' . $LANG->getLL('dmail_back') . '" onClick="jumpToUrlD(\'index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'\'); return false;" />';
			if ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
				
					// Finding the domain to use
				$this->urlbase = $this->getUrlBase($row['use_domain']);
				
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
					$urlParts = parse_url($this->url_plain);
					if (!$urlParts['scheme'])	{
						$this->url_plain='http://'.$this->url_plain;
					}
				}
				if (!($row['sendOptions']&2) || !$this->url_html)	{	// html
					$this->url_html='';
				} else {
					$urlParts = parse_url($this->url_html);
					if (!$urlParts['scheme'])	{
						$this->url_html='http://'.$this->url_html;
					}
				}

					// COMMAND:
				switch($this->CMD) {
				case 'fetch':
					$theOutput.=$this->cmd_fetch($row);
					$row = t3lib_BEfunc::getRecord('sys_dmail',$row['uid']);
					break;
				case 'prefetch':
					$theOutput .= $this->cmd_prefetch($row);
					break;
				case 'testmail':
					$theOutput .= $this->cmd_testmail($row);
					break;
				case 'finalmail':
					$theOutput.=$this->cmd_finalmail($row);
					break;
				case 'send_mail_test':
					$theOutput.=$this->cmd_send_mail($row);
					break;
				case 'send_mail_final':
					$theOutput.=$this->cmd_send_mail($row);
					$row = t3lib_BEfunc::getRecord('sys_dmail',$row['uid']);
					break;
				case 'stats':
					$theOutput .= $this->cmd_stats($row);
					break;
				}
				$theOutput = $this->directMail_optionsMenu($row, $this->CMD).$theOutput;
				if (!$this->noView)	{
					$theOutput .= $this->directMail_defaultView($row);
				}
			}
		}
		return $theOutput;
	}

	/**
	 * Compile the categories enables for this $row of this $table.
	 * From version 2.0 the categories are fetched from the db table sys_dmail_category and not page TSconfig.
	 *
	 * @return	void		No return value, updates $this->categories
	 */
	function makeCategories($table,$row) {
		global $TYPO3_DB;
		
		$mm_field = 'module_sys_dmail_category';
		if ($table == 'sys_dmail_group') {
			$mm_field = 'select_categories';
		}
		$this->categories = array();
		$pidList = '';
		$pageTSconfig = t3lib_BEfunc::getTCEFORM_TSconfig($table, $row);
		if (is_array($pageTSconfig[$mm_field])) {
			$pidList = $pageTSconfig[$mm_field]['PAGE_TSCONFIG_IDLIST'];
			if ($pidList) {
				$res = $TYPO3_DB->exec_SELECTquery(
					'*',
					'sys_dmail_category',
					'sys_dmail_category.pid IN (' . $TYPO3_DB->fullQuoteStr($pidList, 'sys_dmail_category') . ')'.
						' AND l18n_parent=0'.
						t3lib_pageSelect::enableFields('sys_dmail_category')
					);
				while($rowCat = $TYPO3_DB->sql_fetch_assoc($res)) {
					if($localizedRowCat = $this->getRecordOverlay('sys_dmail_category',$rowCat,$this->sys_language_uid,'')) {
						$this->categories[$localizedRowCat['uid']] = $localizedRowCat['category'];
					}
				}
			}
		}
		return;
	}
	
	/**
	 * Import from t3lib_page in order to eate backend version
	 * Creates language-overlay for records in general (where translation is found in records from the same table)
	 *
	 * @param	string		Table name
	 * @param	array		Record to overlay. Must containt uid, pid and $table]['ctrl']['languageField']
	 * @param	integer		Pointer to the sys_language uid for content on the site.
	 * @param	string		Overlay mode. If "hideNonTranslated" then records without translation will not be returned un-translated but unset (and return value is false)
	 * @return	mixed		Returns the input record, possibly overlaid with a translation. But if $OLmode is "hideNonTranslated" then it will return false if no translation is found.
	 */
	function getRecordOverlay($table,$row,$sys_language_content,$OLmode='')	{
		global $TCA, $TYPO3_DB;
		if ($row['uid']>0 && $row['pid']>0)	{
			if ($TCA[$table] && $TCA[$table]['ctrl']['languageField'] && $TCA[$table]['ctrl']['transOrigPointerField'])	{
				if (!$TCA[$table]['ctrl']['transOrigPointerTable'])	{
						// Will try to overlay a record only if the sys_language_content value is larger that zero.
					if ($sys_language_content>0)	{
							// Must be default language or [All], otherwise no overlaying:
						if ($row[$TCA[$table]['ctrl']['languageField']]<=0)	{
								// Select overlay record:
							$res = $TYPO3_DB->exec_SELECTquery(
								'*',
								$table,
								'pid='.intval($row['pid']).
									' AND '.$TCA[$table]['ctrl']['languageField'].'='.intval($sys_language_content).
									' AND '.$TCA[$table]['ctrl']['transOrigPointerField'].'='.intval($row['uid']).
									t3lib_pageSelect::enableFields($table),
								'',
								'',
								'1'
								);
							$olrow = $TYPO3_DB->sql_fetch_assoc($res);
							//$this->versionOL($table,$olrow);
							
								// Merge record content by traversing all fields:
							if (is_array($olrow))	{
								foreach($row as $fN => $fV)	{
									if ($fN!='uid' && $fN!='pid' && isset($olrow[$fN]))	{
										if ($TCA[$table]['l10n_mode'][$fN]!='exclude' && ($TCA[$table]['l10n_mode'][$fN]!='mergeIfNotBlank' || strcmp(trim($olrow[$fN]),'')))	{
											$row[$fN] = $olrow[$fN];
										}
									}
								}
							} elseif ($OLmode==='hideNonTranslated' && $row[$TCA[$table]['ctrl']['languageField']]==0)	{	// Unset, if non-translated records should be hidden. ONLY done if the source record really is default language and not [All] in which case it is allowed.
								unset($row);
							}

							// Otherwise, check if sys_language_content is different from the value of the record - that means a japanese site might try to display french content.
						} elseif ($sys_language_content!=$row[$TCA[$table]['ctrl']['languageField']])	{
							unset($row);
						}
					} else {
							// When default language is displayed, we never want to return a record carrying another language!:
						if ($row[$TCA[$table]['ctrl']['languageField']]>0)	{
							unset($row);
						}
					}
				}
			}
		}

		return $row;
	}

	// ********************
	// CMD functions
	// ********************

	/**
	 * Makes a wizardfor createing direct mail.
	 */
	function cmd_wizard() {
		
		return "In wizard mode";
	}
	
	/**
	 *
	 *  @return	String	The infopage
	 */
	function cmd_displayPageInfo()	{
		global $TCA, $LANG, $TYPO3_DB, $BACK_PATH;
		
			// Here the dmail list is rendered:
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,pid,subject,tstamp,issent,renderedsize,attachment,type',
			'sys_dmail',
			'pid='.intval($this->id).
				' AND type=0'.
				' AND page='.intval($this->pages_uid).
				t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			$TYPO3_DB->stripOrderBy($TCA['sys_dmail']['ctrl']['default_sortby'])
			);

		if ($TYPO3_DB->sql_num_rows($res))	{
			$onClick = ' onClick="return confirm('.$LANG->JScharCode(sprintf($LANG->getLL('nl_l_warning'),$TYPO3_DB->sql_num_rows($res))).');"';
		} else {
			$onClick = '';
		}
		$page = t3lib_BEfunc::getRecord('pages',$this->pages_uid);
		$pageTitle = t3lib_iconWorks::getIconImage('pages',$row,$BACK_PATH,'style="vertical-align: top;"').$page['title'];
		$out="";
		$out.='<a href="#" onClick="'.t3lib_BEfunc::viewOnClick($this->pages_uid,$BACK_PATH).'"><img '.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/zoom.gif', 'width="12" height="12"').' alt="" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" />'.$LANG->getLL("nl_viewPage").'</a><br />';
		$out.='<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[pages]['.$this->pages_uid.']=edit&edit_content=1',$BACK_PATH,"",1).'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("nl_editPage").'" />'.$LANG->getLL("nl_editPage").'</a><br />';
		$out.='<a href="index.php?id='.$this->id.'&createMailFrom_UID='.$this->pages_uid.'&SET[dmail_mode]=direct"'.$onClick.'><img '.t3lib_iconWorks::skinImg($BACK_PATH, t3lib_extMgm::extRelPath($this->extKey).'res/gfx/newmail.gif', 'width="18" height="16"').' width="18" height="16" style="vertical-align: top;" />'.$LANG->getLL("nl_createDmailFromPage").'</a><br />';				

		if ($TYPO3_DB->sql_num_rows($res))	{
			$out.='<br /><b>'.$LANG->getLL('nl_alreadyBasedOn').':</b><br /><br />';
			$out.='<table border="0" cellpadding="0" cellspacing="0">';
				$out.='<tr>
					<td bgColor="'.$this->doc->bgColor5.'">'.fw('&nbsp;').'</td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_subject').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_lastM').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_sent').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_size').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_attach').'&nbsp;&nbsp;').' </b></td>
					<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_type').'&nbsp;&nbsp;').'</b></td>
				</tr>';
			while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$out.='<tr>
					<td>'.t3lib_iconWorks::getIconImage('sys_dmail',$row, $BACK_PATH, 'width="18" height="16" style="vertical-align: top;"').'</td>
					<td>'.$this->linkDMail_record(fw(t3lib_div::fixed_lgd($row['subject'],30).'  '),$row['uid']).'</td>
					<td>'.fw(t3lib_BEfunc::date($row["tstamp"])."  ").'</td>
					<td>'.($row['issent'] ? fw($LANG->getLL('dmail_yes')) : fw($LANG->getLL('dmail_no'))).'</td>
					<td>'.($row['renderedsize'] ? fw(t3lib_div::formatSize($row['renderedsize']).'  ') : '').'</td>
					<td>'.($row['attachment'] ? '<img '.t3lib_iconWorks::skinImg($BACK_PATH, t3lib_extMgm::extRelPath($this->extKey).'res/gfx/attach.gif', 'width="9" height="13"').' alt="'.htmlspecialchars(fw($LANG->getLL('nl_l_attach'))).'" title="'.htmlspecialchars($row['attachment']).'" width="9" height="13">' : '').'</td>
					<td>'.fw($row['type'] ? $LANG->getLL('nl_l_tUrl') : $LANG->getLL('nl_l_tPage')).'</td>
				</tr>';
			}
			$out.='</table>';
		}

		$theOutput.= $this->doc->section($LANG->getLL('nl_info').' '.$pageTitle.t3lib_BEfunc::cshItem($this->cshTable,'page_info',$BACK_PATH), $out , 1, 1, 0, TRUE);
		$theOutput.= $this->doc->spacer(20);
		
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
			$theOutput.= $this->doc->section($LANG->getLL('nl_cat'),$LANG->getLL('nl_cat_msg1'));
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
				
				$this->makeCategories('tt_content', $row);
				reset($this->categories);
				while(list($pKey,$pVal)=each($this->categories))	{
					$out_check.='<input type="hidden" name="indata[categories]['.$row["uid"].']['.$pKey.']" value="0"><input type="checkbox" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="1"'.(t3lib_div::inList($row_categories,$pKey) ?' checked':'').'> '.$pVal.'<br />';
				}
				$out.=fw($out_check).'</td></tr>';
			}
			$out='<table border="0" cellpadding="0" cellspacing="0">'.$out.'</table>';
			$out.='<input type="hidden" name="pages_uid" value="'.$this->pages_uid.'"><input type="hidden" name="CMD" value="'.$this->CMD.'"><br /><input type="submit" value="'.$LANG->getLL('nl_l_update').'">';
			$theOutput.= $this->doc->section($LANG->getLL('nl_cat').t3lib_BEfunc::cshItem($this->cshTable,'assign_categories',$BACK_PATH), $out, 1, 1, 0, TRUE);
		}
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$str: ...
	 * @param	[type]		$sep: ...
	 * @return	[type]		...
	 */
	function getCsvValues($str,$sep=',')	{
		$fh=tmpfile();
		fwrite ($fh, trim($str));
		fseek ($fh,0);
		$lines=array();
		while ($data = fgetcsv ($fh, 1000, $sep)) {
			$lines[]=$data;
		}
		return $lines;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$lines: ...
	 * @return	[type]		...
	 */
	function rearrangeCsvValues($lines)	{
		$out=array();
		if (is_array($lines) && count($lines)>0)	{
			// Analyse if first line is fieldnames.
			// Required is it that every value is either 1) found in the list, fieldsList in this class (see top) 2) the value is empty (value omitted then) or 3) the field starts with "user_".
			// In addition fields may be prepended with "[code]". This is used if the incoming value is true in which case '+[value]' adds that number to the field value (accummulation) and '=[value]' overrides any existing value in the field
			$first = $lines[0];
			$fieldListArr = explode(',',$this->fieldList);
			reset($first);
			$fieldName=1;
			$fieldOrder=array();
			while(list(,$v)=each($first))	{
				list($fName,$fConf) = split("\[|\]",$v);
				$fName =trim($fName);
				$fConf =trim($fConf);
				$fieldOrder[]=array($fName,$fConf);
				if ($fName && substr($fName,0,5)!="user_" && !in_array($fName,$fieldListArr))	{$fieldName=0; break;}
			}
			// If not field list, then:
			if (!$fieldName)	{
				$fieldOrder = array(array('name'),array('email'));
			}

			// Re map values
			reset($lines);
			if ($fieldName)	{
				next($lines);	// Advance pointer if the first line was field names
			}
			$c=0;
			while(list(,$data)=each($lines))	{
				if (count($data)>1 || $data[0])	{	// Must be a line with content. This sorts out entries with one key which is empty. Those are empty lines.

					// Traverse fieldOrder and map values over
					reset($fieldOrder);
					while(list($kk,$fN)=each($fieldOrder))	{
						//print "Checking $kk::".t3lib_div::view_array($fN).'<br />';
						if ($fN[0])	{
							if ($fN[1])	{
								if (trim($data[$kk]))	{	// If is true
									if (substr($fN[1],0,1)=='=')	{
										$out[$c][$fN[0]]=trim(substr($fN[1],1));
									} elseif (substr($fN[1],0,1)=='+')	{
										$out[$c][$fN[0]]+=substr($fN[1],1);
									}
								}
							} else {
								$out[$c][$fN[0]]=$data[$kk];
							}
						}
					}
					$c++;
				}
			}
		}
		return $out;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$plainMails: ...
	 * @return	[type]		...
	 */
	function rearrangePlainMails($plainMails)	{
		$out=array();
		if (is_array($plainMails))	{
			reset($plainMails);
			$c=0;
			while(list(,$v)=each($plainMails))	{
				$out[$c]['email']=$v;
				$out[$c]['name']='';
				$c++;
			}
		}
		return $out;
	}

	/**
	 * Return all entries from $table where the $pid is in $pidList. If $cat is 0 or empty, then all entries (with pid $pid) is returned
	 * else only entires which are subscribing to the categories of the group with uid $group_uid is returned. 
	 * The relation between the recipients in $table and sys_dmail_categories is a true MM relation (Must be correctly defined in TCA).
	 *
	 * @param	[String]		$table: The table to select from
	 * @param	[String]		$pidList: The pidList
	 * @param	[String]		$fields: The fields to select
	 * @param	[int]			$group_uid: The groupUid.
	 * @param	[int]			$cat: The number of relations from sys_dmail_group to sysmail_categories
	 * @return	[string]		The resulting query.
	 */
	function makePidListQuery($table,$pidList,$fields,$group_uid,$cat)	{
		global $TCA, $TYPO3_DB;
		
		if ($table == 'fe_groups') {
			$switchTable = 'fe_users';
		} else {
			$switchTable = $table;
		}
			 // Direct Mail needs an email address!
		$emailIsNotNull = ' AND ' . $switchTable . '.email !=' . $TYPO3_DB->fullQuoteStr('', $switchTable);
		t3lib_div::loadTCA($switchTable);
		$mm_table = $TCA[$switchTable]['columns']['module_sys_dmail_category']['config']['MM'];
		$cat = intval($cat);
		if($cat < 1) {
			if ($table == 'fe_groups') {
				$query = $TYPO3_DB->SELECTquery(
					$fields,
					'fe_users, fe_groups',
					'fe_groups.pid IN ('.$pidList.')'.
						' AND fe_groups.uid IN(fe_users.usergroup)'.
						$emailIsNotNull.
						t3lib_pageSelect::enableFields($switchTable).
						t3lib_pageSelect::enableFields($table)
				);
			} else {
				$query = $TYPO3_DB->SELECTquery(
					$fields,
					$table,
					'pid IN ('.$pidList.')'.
						$emailIsNotNull.
						t3lib_pageSelect::enableFields($table)
				);
			}
		} else {
			if ($table == 'fe_groups') {
				$query = $TYPO3_DB->SELECTquery(
					'DISTINCT '.$switchTable.'.uid as noEntry,'.$fields,
					'sys_dmail_group, sys_dmail_group_category_mm as g_mm, '.$mm_table.' as mm_1, fe_groups LEFT JOIN '.$switchTable.' ON '.$switchTable.'.uid = mm_1.uid_local',
					'fe_groups.pid IN ('.$pidList.')'.
						' AND fe_groups.uid IN (fe_users.usergroup)'.
						' AND mm_1.uid_foreign=g_mm.uid_foreign'.
						' AND sys_dmail_group.uid=g_mm.uid_local'.
						' AND sys_dmail_group.uid='.intval($group_uid).
						$emailIsNotNull.
						t3lib_pageSelect::enableFields($table).
						t3lib_pageSelect::enableFields($switchTable)
				);
			} else {
				$query = $TYPO3_DB->SELECTquery(
					'DISTINCT '.$table.'.uid as noEntry,'.$fields,
					'sys_dmail_group, sys_dmail_group_category_mm as g_mm, '.$mm_table.' as mm_1 LEFT JOIN '.$table.' ON '.$table.'.uid = mm_1.uid_local',
					$table.'.pid IN ('.$pidList.')'.
						' AND mm_1.uid_foreign=g_mm.uid_foreign'.
						' AND sys_dmail_group.uid=g_mm.uid_local'.
						' AND sys_dmail_group.uid='.intval($group_uid).
						$emailIsNotNull.
						t3lib_pageSelect::enableFields($table)
				);
			}
		}
		return $query;
	}

	/**
	 * Get list of uids for a list of page uid's. See makePidListQuery for explanation.
	 *
	 * @param	[type]		$table: The table to select from
	 * @param	[type]		$pidList: Records must be in this CSV string.
	 * @param	[int]			$gropu_uid: See makePidListQuery
	 * @param	[int/String]$cat: See makePidListQuery
	 * @return	[String]		CVS list of uid's.
	 */
	function getIdList($table,$pidList,$group_uid,$cat)	{
		global $TYPO3_DB;
		if ($table == 'fe_groups') {
			$query = $this->makePidListQuery($table,$pidList,'fe_users.uid',$group_uid,$cat);
		} else {
			$query = $this->makePidListQuery($table,$pidList,$table.'.uid',$group_uid,$cat);
		}
		$res = $TYPO3_DB->sql(TYPO3_db,$query);
		$outArr = array();
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$outArr[] = $row['uid'];
		}
		return $outArr;
	}

	/**
	 * Returns a query for selecting user from a statuc direct mail group.
	 *
	 * @param	[String]		$table: The table to select from
	 * @param	[int]			$uid: The uid of the direct_mail group
	 * @param	[String]		$fields: The fields to select
	 * @return	[Strint]		The resulting query.
	 */
	function makeStaticListQuery($table,$uid,$fields) {
		global $TYPO3_DB;
		
		$emailIsNotNull = ' AND ' . $table . '.email !=' . $TYPO3_DB->fullQuoteStr('', $table);  // Direct Mail needs and email address!
		$query = $TYPO3_DB->SELECTquery(
			$fields,
			'sys_dmail_group, ' . $table . ' LEFT JOIN sys_dmail_group_mm ON sys_dmail_group_mm.uid_foreign='.$table.'.uid',
			'sys_dmail_group.uid = '.intval($uid).
				' AND sys_dmail_group_mm.uid_local=sys_dmail_group.uid'.
				' AND sys_dmail_group_mm.tablenames='.$TYPO3_DB->fullQuoteStr($table, $table).
				$emailIsNotNull.
				t3lib_pageSelect::enableFields($table).
				t3lib_pageSelect::enableFields('sys_dmail_group')
			);
		if ($table == 'fe_users') {
			$query .= ' UNION ';
			$query .= $TYPO3_DB->SELECTquery(
				$fields,
				'fe_users, fe_groups, sys_dmail_group LEFT JOIN sys_dmail_group_mm ON sys_dmail_group_mm.uid_local=sys_dmail_group.uid',
				'sys_dmail_group.uid='.intval($uid).
					' AND fe_groups.uid=sys_dmail_group_mm.uid_foreign'.
					' AND sys_dmail_group_mm.tablenames='.$TYPO3_DB->fullQuoteStr('fe_groups', 'fe_groups').
					' AND fe_groups.uid IN(fe_users.usergroup)'.
					$emailIsNotNull.
					t3lib_pageSelect::enableFields($table).
					t3lib_pageSelect::enableFields('fe_groups').
					t3lib_pageSelect::enableFields('sys_dmail_group')
				);
		}
		return $query;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$table: ...
	 * @param	[type]		$uid: ...
	 * @return	[type]		...
	 */
	function getStaticIdList($table,$uid)	{
		global $TYPO3_DB;
		
		$query = $this->makeStaticListQuery($table,$uid,$table.'.uid');
		$res = $TYPO3_DB->sql(TYPO3_db,$query);
		$outArr=array();
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$outArr[]=$row['uid'];
		}
		return $outArr;
	}
	
	/**
	 * Returns special query of mail group of such type
	 *
	 * @param	[String]		$table: The table to select from
	 * @param	[Array]			$group: The direct_mail group record
	 * @param	[String]		$fields: The fields to select
	 * @return	[Strint]		The resulting query.
	 */
	function makeSpecialQuery($table,$group,$fields) {
		global $TYPO3_DB;
		
		$query ='';
		if ($group['query']) {
			$this->queryGenerator->init('dmail_queryConfig', $table, (($fields == '*')?'':$fields));
			$this->queryGenerator->queryConfig = unserialize($group['query']);
			$query = $TYPO3_DB->SELECTquery(
				$fields,
				$table,
				$this->queryGenerator->getQuery($this->queryGenerator->queryConfig).
					t3lib_BEfunc::deleteClause($table)
			);
		}
		return $query;
	}
	
	/**
	 * Construct the array of uid's from $table selected by special query of mail group of such type
	 *
	 * @param	[String]		$table: The table to select from
	 * @param	[Array]			$group: The direct_mail group record
	 * @return	[Array]			$outArr: the array of uid's
	 */
	function getSpecialQueryIdList($table,$group)	{
		global $TYPO3_DB;
		
		$outArr = array();
		$query = $this->makeSpecialQuery($table,$group,$table.'.uid');
		if ($query) {
			$res = $TYPO3_DB->sql(TYPO3_db, $query);
			while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$outArr[] = $row['uid'];
			}
		}
		return $outArr;
	}
	
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$list: ...
	 * @param	[type]		$parsedGroups: ...
	 * @return	[type]		...
	 */
	function getMailGroups($list,$parsedGroups)	{
		$groupIdList = t3lib_div::intExplode(',',$list);
		$groups = array();

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'sys_dmail_group.*',
			'sys_dmail_group LEFT JOIN pages ON pages.uid=sys_dmail_group.pid',
			'sys_dmail_group.uid IN ('.implode(',',$groupIdList).')'.
				' AND '.$this->perms_clause.
				t3lib_BEfunc::deleteClause('pages').
				t3lib_pageSelect::enableFields('sys_dmail_group')
			);

		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			if ($row['type']==4)	{	// Other mail group...
				if (!in_array($row['uid'],$parsedGroups))	{
					$parsedGroups[]=$row['uid'];
					$groups=array_merge($groups,$this->getMailGroups($row['mail_groups'],$parsedGroups));
				}
			} else {
				$groups[]=$row['uid'];	// Normal mail group, just add to list
			}
		}
		return $groups;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$result: ...
	 * @return	[type]		...
	 */
	function cmd_displayMailGroup($result)	{
		global $LANG, $BACK_PATH;
		
		$count=0;
		$idLists = $result['queryInfo']['id_lists'];
		if (is_array($idLists['tt_address']))	$count+=count($idLists['tt_address']);
		if (is_array($idLists['fe_users']))	$count+=count($idLists['fe_users']);
		if (is_array($idLists['PLAINLIST']))	$count+=count($idLists['PLAINLIST']);
		if (is_array($idLists[$this->userTable]))	$count+=count($idLists[$this->userTable]);
		
		$group = t3lib_BEfunc::getRecord('sys_dmail_group',t3lib_div::_GP('group_uid'));
		$out=t3lib_iconWorks::getIconImage('sys_dmail_group',$group,$BACK_PATH,'style="vertical-align: top;"').$group['title'];
		
		$lCmd=t3lib_div::_GP('lCmd');
		
		$mainC = $LANG->getLL('mailgroup_recip_number') . ' <strong>'.$count.'</strong>';
		if (!$lCmd)	{
			$mainC.= '<br /><br /><a href="'.t3lib_div::linkThisScript(array('lCmd'=>'listall')).'">' . $LANG->getLL('mailgroup_list_all') . '</a>';
		}
		
		$theOutput.= $this->doc->section($LANG->getLL('mailgroup_recip_from').' '.$out,$mainC, 1, 1, 0, TRUE);
		$theOutput.= $this->doc->spacer(20);
		
		switch($lCmd)	{
		case 'listall':
			if (is_array($idLists['tt_address'])) {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_address'),$this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address'));
				$theOutput.= $this->doc->spacer(20);
			}
			if (is_array($idLists['fe_users'])) {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_fe_users'),$this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users'));
			$theOutput.= $this->doc->spacer(20);
			}
			if (is_array($idLists['PLAINLIST'])) {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_plain_list'),$this->getRecordList($idLists['PLAINLIST'],'default',1));
				$theOutput.= $this->doc->spacer(20);
			}
			if (is_array($idLists[$this->userTable])) {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_custom') . ' ' . $this->userTable,$this->getRecordList($this->fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable));
			}
			break;
		default:
			if (t3lib_div::_GP('csv'))	{
				$csvValue=t3lib_div::_GP('csv');
				if ($csvValue=='PLAINLIST')	{
					$this->downloadCSV($idLists['PLAINLIST']);
				} elseif (t3lib_div::inList('tt_address,fe_users,'.$this->userTable, $csvValue)) {
					$this->downloadCSV($this->fetchRecordsListValues($idLists[$csvValue],$csvValue,(($csvValue == 'fe_users') ? str_replace('phone','telephone',$this->fieldList) : $this->fieldList).',tstamp'));
				}
			} else {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_address'),$LANG->getLL('mailgroup_recip_number') . ' ' . (is_array($idLists['tt_address'])?count($idLists['tt_address']).'<br /><a href="'.t3lib_div::linkThisScript(array('csv'=>'tt_address')).'">' . $LANG->getLL('mailgroup_download') . '</a>':0));
				$theOutput.= $this->doc->spacer(20);
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_fe_users'),$LANG->getLL('mailgroup_recip_number') . ' ' .(is_array($idLists['fe_users'])?count($idLists['fe_users']).'<br /><a href="'.t3lib_div::linkThisScript(array('csv'=>'fe_users')).'">' . $LANG->getLL('mailgroup_download') . '</a>':0));
				$theOutput.= $this->doc->spacer(20);
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_plain_list'),$LANG->getLL('mailgroup_recip_number') . ' ' .(is_array($idLists['PLAINLIST'])?count($idLists['PLAINLIST']).'<br /><a href="'.t3lib_div::linkThisScript(array('csv'=>'PLAINLIST')).'">' . $LANG->getLL('mailgroup_download') . '</a>':0));
				$theOutput.= $this->doc->spacer(20);
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_custom'),$LANG->getLL('mailgroup_recip_number') . ' ' .(is_array($idLists[$this->userTable])?count($idLists[$this->userTable]).'<br /><a href="'.t3lib_div::linkThisScript(array('csv'=>$this->userTable)).'">' . $LANG->getLL('mailgroup_download') . '</a>':0));
			}
			if ($group['type'] == 3) {
				$theOutput .= $this->cmd_specialQuery($group);
			}
			break;
		}
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$idArr: ...
	 * @return	[type]		...
	 */
	function downloadCSV($idArr)	{
		$lines=array();
		if (is_array($idArr) && count($idArr))	{
			reset($idArr);
			$lines[]=t3lib_div::csvValues(array_keys(current($idArr)),',','');

			reset($idArr);
			while(list($i,$rec)=each($idArr))	{
				//			debug(t3lib_div::csvValues($rec),1);
				$lines[]=t3lib_div::csvValues($rec);
			}
		}

		$filename='DirectMail_export_'.date('dmy-Hi').'.csv';
		$mimeType = 'application/octet-stream';
		Header('Content-Type: '.$mimeType);
		Header('Content-Disposition: attachment; filename='.$filename);
		echo implode(chr(13).chr(10),$lines);
		exit;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$result: ...
	 * @return	[type]		...
	 */
	function cmd_displayMailGroup_test($result)	{
		$count=0;
		$idLists = $result['queryInfo']['id_lists'];
		$out='';
		if (is_array($idLists['tt_address']))	{$out.=$this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');}
		if (is_array($idLists['fe_users']))	{$out.=$this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');}
		if (is_array($idLists['PLAINLIST']))	{$out.=$this->getRecordList($idLists['PLAINLIST'],'default',1);}
		if (is_array($idLists[$this->userTable]))	{$out.=$this->getRecordList($this->fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable);}

		return $out;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$group_uid: ...
	 * @param	[type]		$makeIdLists: Set to 0 if you don't want the list of table ids to be collected but only the queries to be stored.
	 * @return	[type]		...
	 */
	function cmd_compileMailGroup($group_uid,$makeIdLists=1) {
		global $BACKPATH;
		
		$queries=array();
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
									$pageIdArray=array_merge($pageIdArray,$this->getRecursiveSelect($pageUid,$this->perms_clause));
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
							$queries['tt_address']=$this->makePidListQuery('tt_address',$pidList,'tt_address.*',$group_uid,$mailGroup['select_categories']);
							if ($makeIdLists)	$id_lists['tt_address']=$this->getIdList('tt_address',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&2)	{	// fe_users
							$queries['fe_users']=$this->makePidListQuery('fe_users',$pidList,'fe_users.*',$group_uid,$mailGroup['select_categories']);
							if ($makeIdLists)	$id_lists['fe_users']=$this->getIdList('fe_users',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($this->userTable && ($whichTables&4))	{	// user table
							$queries[$this->userTable]=$this->makePidListQuery($this->userTable,$pidList,$this->userTable.'*',$group_uid,$mailGroup['select_categories']);
							if ($makeIdLists)	$id_lists[$this->userTable]=$this->getIdList($this->userTable,$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&8)	{	// fe_groups
							$queries['fe_users'] .= ' UNION '.$this->makePidListQuery('fe_groups',$pidList,'fe_users.*',$group_uid,$mailGroup['select_categories']);
							if ($makeIdLists)	$id_lists['fe_users'] = array_merge($id_lists['fe_users'], $this->getIdList('fe_groups',$pidList,$group_uid,$mailGroup['select_categories']));
						}
					}
					break;
				case 1: // List of mails
					if ($mailGroup['csv']==1)	{
						$recipients = $this->rearrangeCsvValues($this->getCsvValues($mailGroup['list']));
					} else {
						$recipients = $this->rearrangePlainMails(array_unique(split('[[:space:],;]+',$mailGroup['list'])));
					}
					$id_lists['PLAINLIST'] = $this->cleanPlainList($recipients);
					break;
				case 2:	// Static MM list
					$queries['tt_address'] = $this->makeStaticListQuery('tt_address', $group_uid,'tt_address.*');
					if ($makeIdLists)	$id_lists['tt_address'] = $this->getStaticIdList('tt_address',$group_uid);
					$queries['fe_users'] = $this->makeStaticListQuery('fe_users', $group_uid,'fe_users.*');
					if ($makeIdLists)	$id_lists['fe_users'] = $this->getStaticIdList('fe_users',$group_uid);
					if ($this->userTable)	{
						$queries[$this->userTable] = $this->makeStaticListQuery($this->userTable,$group_uid,$this->userTable.'*');
						if ($makeIdLists)	$id_lists[$this->userTable] = $this->getStaticIdList($this->userTable,$group_uid);
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
						$queries[$table] = $this->makeSpecialQuery($table,$mailGroup,'*');
						if ($makeIdLists) {
							$id_lists[$table] = $this->getSpecialQueryIdList($table,$mailGroup);
						}
					}
					break;
				case 4:	//
					$groups = array_unique($this->getMailGroups($mailGroup['mail_groups'],array($mailGroup['uid'])));
					reset($groups);
					$queries=array();
					$id_lists=array();
					while(list(,$v)=each($groups))	{
						$collect=$this->cmd_compileMailGroup($v);
						if (is_array($collect['queryInfo']['queries']))	{
							$queries=t3lib_div::array_merge_recursive_overrule($queries,$collect['queryInfo']['queries']);
						}
						if (is_array($collect['queryInfo']['id_lists']))	{
							$id_lists=t3lib_div::array_merge_recursive_overrule($id_lists,$collect['queryInfo']['id_lists']);
						}
					}
					// Make unique entries
					if (is_array($id_lists['tt_address']))	$id_lists['tt_address'] = array_unique($id_lists['tt_address']);
					if (is_array($id_lists['fe_users']))	$id_lists['fe_users'] = array_unique($id_lists['fe_users']);
					if (is_array($id_lists[$this->userTable]) && $this->userTable)	$id_lists[$this->userTable] = array_unique($id_lists[$this->userTable]);
					if (is_array($id_lists['PLAINLIST']))	{$id_lists['PLAINLIST'] = $this->cleanPlainList($id_lists['PLAINLIST']);}
					break;
				}
			}
		}
		$outputArray = array(
			'queryInfo' => array('id_lists' => $id_lists, 'queries' => $queries)
			);
		return $outputArray;
	}
	
	function getRecursiveSelect($id,$perms_clause)  {
			// Finding tree and offer setting of values recursively.
		$tree = t3lib_div::makeInstance('t3lib_pageTree');
		$tree->init('AND '.$perms_clause);
		$tree->makeHTML=0;
		$tree->setRecs = 0;
		$getLevels=10000;
		$tree->getTree($id,$getLevels,'');
		return $tree->ids;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$plainlist: ...
	 * @return	[type]		...
	 */
	function cleanPlainList($plainlist)	{
		reset($plainlist);
		$emails=array();
		while(list($k,$v)=each($plainlist))	{
			if (in_array($v['email'],$emails))	{	unset($plainlist[$k]);	}
			$emails[]=$v['email'];
		}
		return $plainlist;
	}
	
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$dgUid: ...
	 * @return	[type]		...
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
	 * [Describe function...]
	 *
	 * @param	[type]		$dgUid: ...
	 * @return	[type]		...
	 */
	function cmd_specialQuery($mailGroup) {
		global $LANG;
		
		$this->queryGenerator->init('dmail_queryConfig',$this->MOD_SETTINGS['queryTable']);
		
		if ($this->MOD_SETTINGS['queryTable'] && $this->MOD_SETTINGS['queryConfig']) {
			$this->queryGenerator->queryConfig = unserialize($this->MOD_SETTINGS['queryConfig']);
			$out .= $this->queryGenerator->getSelectQuery();
			$out .= $this->doc->spacer(20);
		}
		
		$this->queryGenerator->noWrap='';
		$this->queryGenerator->allowedTables = $this->allowedTables;
		$tmpCode = $this->queryGenerator->makeSelectorTable($this->MOD_SETTINGS,'table,query');
		$tmpCode .= '<input type="hidden" name="CMD" value="displayMailGroup" /><input type="hidden" name="group_uid" value="'.$mailGroup['uid'].'" />';
		$tmpCode .= '<input type="submit" value="'.$LANG->getLL('dmail_updateQuery').'" />';
		$out .= $this->doc->section($LANG->getLL('dmail_makeQuery'),$tmpCode);
		
		$theOutput .= $this->doc->spacer(20);
		$theOutput .= $this->doc->section($LANG->getLL('dmail_query'),$out);
		
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$records: ...
	 * @param	[type]		$syncSelect: ...
	 * @param	[type]		$tstampFlag: ...
	 * @return	[type]		...
	 */
	function importRecords_sort($records,$syncSelect,$tstampFlag)	{
		reset($records);
		$kinds=array();
		while(list(,$recdata)=each($records))	{
			if ($syncSelect && !t3lib_div::testInt($syncSelect))	{
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid,tstamp',
					'tt_address',
					'pid='.intval($this->id).
						' AND '.$syncSelect.'="'.$GLOBALS['TYPO3_DB']->quoteStr($recdata[$syncSelect], 'tt_address').'"'
						.t3lib_BEfunc::deleteClause('tt_address'),
					'',
					'',
					'1'
				);
				if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					if ($tstampFlag)	{
						if ($row['tstamp']>intval($recdata['tstamp']))	{
							$kinds['newer_version_detected'][]=$recdata;
						} else {$kinds['update'][$row['uid']]=$recdata;}
					} else {$kinds['update'][$row['uid']]=$recdata;}
				} else {$kinds['insert'][]=$recdata;}	// Import if no row found
			} else {$kinds['insert'][]=$recdata;}
		}
		return $kinds;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$categorizedRecords: ...
	 * @param	[type]		$removeExisting: ...
	 * @return	[type]		...
	 */
	function importRecords($categorizedRecords,$removeExisting)	{
		$cmd = array();
		$data = array();
		if ($removeExisting)	{		// Deleting:
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'uid',
				'tt_address',
				'pid='.intval($this->id).
				t3lib_BEfunc::deleteClause('tt_address')
			);
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$cmd['tt_address'][$row['uid']]['delete'] = 1;
			}
		}
		if (is_array($categorizedRecords['insert'])) {
			reset($categorizedRecords['insert']);
			$c=0;
			while(list(,$rec)=each($categorizedRecords['insert']))	{
				$c++;
				$data['tt_address']['NEW'.$c] = $rec;
				$data['tt_address']['NEW'.$c]['pid'] = $this->id;
			}
		}
		if (is_array($categorizedRecords['update'])) {
			reset($categorizedRecords['update']);
			$c=0;
			while(list($rUid,$rec)=each($categorizedRecords['update']))	{
				$c++;
				$data['tt_address'][$rUid]=$rec;
			}
		}
		
		$tce = t3lib_div::makeInstance('t3lib_TCEmain');
		$tce->stripslashes_values=0;
		$tce->enableLogging=0;
		$tce->start($data,$cmd);
		$tce->process_datamap();
		$tce->process_cmdmap();
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$listArr: ...
	 * @param	[type]		$table: ...
	 * @param	[type]		$fields: ...
	 * @return	[type]		...
	 */
	function fetchRecordsListValues($listArr,$table,$fields='uid,name,email') {
		global $TYPO3_DB;
		
		$count = 0;
		$outListArr = array();
		if (is_array($listArr) && count($listArr))	{
			$idlist = implode(',',$listArr);
			$res = $TYPO3_DB->exec_SELECTquery(
				$fields,
				$table,
				'uid IN ('.$idlist.')'.
					t3lib_BEfunc::deleteClause($table)
				);
			while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$outListArr[$row['uid']] = $row;
			}
		}
		return $outListArr;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$listArr: ...
	 * @param	[type]		$table: ...
	 * @param	[type]		$dim: ...
	 * @param	[type]		$editLinkFlag: ...
	 * @return	[type]		...
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
						$editLink = '<td><a href="index.php?id='.$this->id.'&CMD=displayUserInfo&table='.$table.'&uid='.$row['uid'].'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="' . $LANG->getLL('dmail_edit') . '" width="12" height="12" style="margin:0px 5px; vertical-align:top;" title="' . $LANG->getLL('dmail_edit') . '" /></a></td>';
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
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function cmd_displayImport()	{
		global $LANG, $BACK_PATH;
		
		$indata = t3lib_div::_GP('CSV_IMPORT');
		if (is_array($indata))	{
			$records = $this->rearrangeCsvValues($this->getCsvValues($indata['csv'],$indata['sep']));
			$categorizedRecords = $this->importRecords_sort($records,$indata['syncSelect'],$indata['tstamp']);
			
			$theOutput.= $this->doc->section($LANG->getLL('mailgroup_import_insert'),$this->getRecordList($categorizedRecords['insert'],'tt_address',1));
			$theOutput.= $this->doc->spacer(20);
			$theOutput.= $this->doc->section($LANG->getLL('mailgroup_import_update'),$this->getRecordList($categorizedRecords['update'],'tt_address',1));
			$theOutput.= $this->doc->spacer(20);
			$theOutput.= $this->doc->section($LANG->getLL('mailgroup_import_no_update'),$this->getRecordList($categorizedRecords['newer_version_detected'],'tt_address',1));
			$theOutput.= $this->doc->spacer(20);
			
			if ($indata['doImport'])	{
				$this->importRecords($categorizedRecords,$indata['syncSelect']==-1?1:0);
			}
		}
		
		if (!is_array($indata) || $indata['test_only'])	{
			$importButton=is_array($indata) ? '<input type="submit" name="CSV_IMPORT[doImport]" value="' . $LANG->getLL('mailgroup_import_now') . '" />' : '';
				// Selector, mode
			if (!isset($indata['syncSelect']))	$indata['syncSelect']='email';
			$opt=array();
			$opt[]='<option value="email"'.($indata['syncSelect']=='email'?' selected="selected"':'').'>' . $LANG->getLL('mailgroup_import_update_rule_email') . '</option>';
			$opt[]='<option value="name"'.($indata['syncSelect']=='name'?' selected="selected"':'').'>' . $LANG->getLL('mailgroup_import_update_rule_name') . '</option>';
			$opt[]='<option value="uid"'.($indata['syncSelect']=='uid'?' selected="selected"':'').'>' . $LANG->getLL('mailgroup_import_update_rule_uid') . '</option>';
			$opt[]='<option value="phone"'.($indata['syncSelect']=='phone'?' selected="selected"':'').'>' . $LANG->getLL('mailgroup_import_update_rule_phone') . '</option>';
			$opt[]='<option value="0"'.($indata['syncSelect']=='0'?' selected="selected"':'').'>' . $LANG->getLL('mailgroup_import_update_rule_all') . '</option>';
			$opt[]='<option value="-1"'.($indata['syncSelect']=='-1'?' selected="selected"':'').'>' . $LANG->getLL('mailgroup_import_update_rule_all_delete') . '</option>';
			$selectSync='<select name="CSV_IMPORT[syncSelect]">'.implode('',$opt).'</select>';
			
				// Selector, sep
			if (!isset($indata['sep']))	$indata['sep']=',';
			$opt=array();
			$opt[]='<option value=","'.($indata['sep']==','?' selected="selected"':'').'>, (' . $LANG->getLL('mailgroup_import_separator_comma') . ')</option>';
			$opt[]='<option value=";"'.($indata['sep']==';'?' selected="selected"':'').'>; (' . $LANG->getLL('mailgroup_import_separator_semicolon') . ')</option>';
			$opt[]='<option value=":"'.($indata['sep']==':'?' selected="selected"':'').'>: (' . $LANG->getLL('mailgroup_import_separator_colon') . ')</option>';
			$sepSync='<select name="CSV_IMPORT[sep]">'.implode('',$opt).'</select>';
			
			$out=$LANG->getLL('mailgroup_import_explain') . '<br /><br />
			<textarea name="CSV_IMPORT[csv]" rows="25" wrap="off"'.$this->doc->formWidthText(48,'','off').'>'.t3lib_div::formatForTextarea($indata['csv']).'</textarea><br />
			<br />
						<strong>' . $LANG->getLL('mailgroup_import_rules') . '</strong><hr />
			' . $LANG->getLL('mailgroup_import_update_rule') . '<br />'.$selectSync.'
			<hr />
			<input type="checkbox" name="CSV_IMPORT[tstamp]" value="1"'.(($importButton && !$indata['tstamp'])?'':' checked="checked"').'/>&nbsp;' . $LANG->getLL('mailgroup_import_update_rule_tstamp') . '
			<hr />
			' . $LANG->getLL('mailgroup_import_separator') . '&nbsp;' . $sepSync.'
			<hr />
			<input type="submit" name="CSV_IMPORT[test_only]" value="' . $LANG->getLL('mailgroup_import_test') . '"> &nbsp; &nbsp; '.$importButton.'
			<input type="hidden" name="CMD" value="displayImport">
			';
		}
		$theOutput.= $this->doc->section($LANG->getLL('mailgroup_import').t3lib_BEfunc::cshItem($this->cshTable,'mailgroup_import',$BACK_PATH),$out, 1, 1, 0, TRUE);
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function cmd_displayUserInfo()	{
		global $TCA, $LANG, $TYPO3_DB, $BACK_PATH;
		$uid = intval(t3lib_div::_GP('uid'));
		$indata = t3lib_div::_GP('indata');
		$table=t3lib_div::_GP('table');
		t3lib_div::loadTCA($table);
		$mm_table = $TCA[$table]['columns']['module_sys_dmail_category']['config']['MM'];

		switch($table)	{
		case 'tt_address':
		case 'fe_users':
			if (is_array($indata))	{
				$data=array();
				if (is_array($indata['categories']))	{
					reset($indata['categories']);
					while(list($recUid,$recValues)=each($indata['categories']))	{
						reset($recValues);
						$enabled = array();
						while(list($k,$b)=each($recValues))	{
							if ($b)	{
								$enabled[] = $k;
							}
						}
						$data[$table][$uid]['module_sys_dmail_category'] = implode(',',$enabled);
					}
				}
				$data[$table][$uid]['module_sys_dmail_html'] = $indata['html'] ? 1 : 0;
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values=0;
				$tce->start($data,Array());
				$tce->process_datamap();
			}
			break;
		}

		switch($table)	{
		case 'tt_address':
			$res = $TYPO3_DB->exec_SELECTquery(
				'tt_address.*',
				'tt_address LEFT JOIN pages ON pages.uid=tt_address.pid',
				'tt_address.uid='.intval($uid).
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('tt_address').
					t3lib_BEfunc::deleteClause('pages')
				);
			$row = $TYPO3_DB->sql_fetch_assoc($res);
			break;
		case 'fe_users':
			$res = $TYPO3_DB->exec_SELECTquery(
				'fe_users.*',
				'fe_users LEFT JOIN pages ON pages.uid=fe_users.pid',
				'fe_users.uid='.intval($uid).
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('fe_users').
					t3lib_BEfunc::deleteClause('pages')
				);
			$row = $TYPO3_DB->sql_fetch_assoc($res);
			break;
		}
		if (is_array($row))	{
			$row_categories = '';
			$resCat = $TYPO3_DB->exec_SELECTquery(
				'uid_foreign',
				$mm_table,
				'uid_local='.$row['uid']
				);
			while($rowCat=$TYPO3_DB->sql_fetch_assoc($resCat)) {
				$row_categories .= $rowCat['uid_foreign'].',';
			}
			$row_categories = t3lib_div::rm_endComma($row_categories);
			
			$Eparams='&edit['.$table.']['.$row['uid'].']=edit';
			$out='';
			$out.= t3lib_iconWorks::getIconImage($table, $row, $BACK_PATH, 'width="18" height="16" title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row['pid'],$this->perms_clause,40)).'" style="vertical-align:top;"').$row['name'].htmlspecialchars(' <'.$row['email'].'>');
			$out.='&nbsp;&nbsp;<a href="#" onClick="'.t3lib_BEfunc::editOnClick($Eparams,$BACK_PATH,'').'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />' . fw('<b>' . $LANG->getLL('dmail_edit') . '</b>').'</a>';
			$theOutput.= $this->doc->section($LANG->getLL('subscriber_info'),$out);

			$out='';
			$out_check='';
			
			$this->makeCategories($table, $row);
			reset($this->categories);
			while(list($pKey,$pVal)=each($this->categories))	{
				$out_check.='<input type="hidden" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="0" /><input type="checkbox" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="1"'.(t3lib_div::inList($row_categories,$pKey)?' checked="checked"':'').' /> '.$pVal.'<br />';
			}
			$out_check.='<br /><br /><input type="checkbox" name="indata[html]" value="1"'.($row['module_sys_dmail_html']?' checked="checked"':'').' /> ';
			$out_check.=$LANG->getLL('subscriber_profile_htmlemail') . '<br />';
			$out.=fw($out_check);

			$out.='<input type="hidden" name="table" value="'.$table.'" /><input type="hidden" name="uid" value="'.$uid.'" /><input type="hidden" name="CMD" value="'.$this->CMD.'" /><br /><input type="submit" value="' . htmlspecialchars($LANG->getLL('subscriber_profile_update')) . '" />';
			$theOutput.= $this->doc->spacer(20);
			$theOutput.= $this->doc->section($LANG->getLL('subscriber_profile'), $LANG->getLL('subscriber_profile_instructions') . '<br /><br />'.$out);
		}
		return $theOutput;
	}

	/**
	 * @param	[type]		$mode: ...
	 * @return	[type]		...
	 */
	function cmd_default($mode)	{
		global $TCA,$LANG,$TYPO3_CONF_VARS;
		switch($mode)	{
		case 'direct':
			$theOutput = $this->cmd_direct();
			break;
		case 'news':
			$theOutput = $this->cmd_news();
			break;
		case 'recip':
			$theOutput .= $this->cmd_recip();
			break;
		case 'mailerengine':
			$theOutput .= $this->cmd_mailerengine();
			break;
		case 'quick':
			$theOutput.= $this->cmd_quickmail();
			break;
		case 'conf':
			$theOutput.= $this->cmd_conf();
			break;
		case 'convert':
			$theOutput .= $this->cmd_convertCategories();
			break;
		default:
				// Hook for preprocessing of the content for formmails:
			if (is_array($TYPO3_CONF_VARS['EXT']['directmail']['handlemode-'.$mode])) {
				foreach($TYPO3_CONF_VARS['EXT']['directmail']['handlemode-'.$mode] as $_funcRef) {
					$_params = array();
					$theOutput .= t3lib_div::callUserFunction($_funcRef,$_params,$this);
				}
			}
			break;
		}

		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$table: ...
	 * @param	[type]		$uid: ...
	 * @return	[type]		...
	 */
	function editLink($table,$uid)	{
		global $LANG, $BACK_PATH;
		
		$params = '&edit['.$table.']['.$uid.']=edit';
		$str = '<a href="#" onClick="'.t3lib_BEfunc::editOnClick($params,$BACK_PATH,'').'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" /></a>';
		return $str;
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function invokeMEngine()	{
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->start();
		$htmlmail->runcron();
		return implode(chr(10),$htmlmail->logArray);
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function updatePageTS()	{
		global $BE_USER;
		
		if ($BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord( 'pages', $this->id), 2)) {
			$pageTS = t3lib_div::_GP('pageTS');
			if (is_array($pageTS))	{
				t3lib_BEfunc::updatePagesTSconfig($this->id,$pageTS,$this->TSconfPrefix);
				header('Location: '.t3lib_div::locationHeaderUrl(t3lib_div::getIndpEnv('REQUEST_URI')));
			}
		}
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$url: ...
	 * @return	[type]		...
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
	 *
	 */
	function cmd_news () {
		global $LANG, $TYPO3_DB, $BACK_PATH;
		
			// Here the list of subpages, news, is rendered
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,doktype,title,abstract',
			'pages',
			'pid='.intval($this->id).
				' AND doktype IN ('.$GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'].')'.
				' AND '.$this->perms_clause.t3lib_BEfunc::deleteClause('pages').
				t3lib_pageSelect::enableFields('pages'),
			'',
			'sorting'
			);
		if (!$TYPO3_DB->sql_num_rows($res))	{
			$theOutput.= $this->doc->section($LANG->getLL('nl_select'),$LANG->getLL('nl_select_msg1'),0,1);
		} else {
			$out = '';
			while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$out.='<nobr><a href="index.php?id='.$this->id.'&CMD=displayPageInfo&pages_uid='.$row['uid'].'&SET[dmail_mode]=news">'.t3lib_iconWorks::getIconImage('pages', $row, $BACK_PATH, 'width="18" height="16" title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row['uid'],$this->perms_clause,20)).'" style="vertical-align: top;"').
					$row['title'].'</a></nobr><br />';
			}
			$theOutput.= $this->doc->section($LANG->getLL('nl_select').t3lib_BEfunc::cshItem($this->cshTable,'select_newsletter',$BACK_PATH), $out, 1, 1, 0, TRUE);
		}
		
			// Create a new page
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section($LANG->getLL('nl_create').t3lib_BEfunc::cshItem($this->cshTable,'create_newsletter',$BACK_PATH),'<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[pages]['.$this->id.']=new&edit[tt_content][prev]=new',$BACK_PATH,'').'"><b>'.$LANG->getLL('nl_create_msg1').'</b></a>', 1, 1, 0, TRUE);
		return $theOutput;
	}

	/**
	 *
	 */
	function cmd_direct() {
		global $LANG, $TYPO3_DB, $BACK_PATH, $TBE_TEMPLATE;
		
			// Here the dmail list is rendered
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,pid,subject,tstamp,issent,renderedsize,attachment,type',
			'sys_dmail',
			'pid='.intval($this->id).
				' AND scheduled=0 AND issent=0'.
				t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			$TYPO3_DB->stripOrderBy($TCA['sys_dmail']['ctrl']['default_sortby'])
			);
		$out ='<tr>
						<td bgColor="'.$this->doc->bgColor5.'">'.fw('&nbsp;').'</td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_subject').'&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_lastM').'&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_sent').'&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_size').'&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_attach').'&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('nl_l_type').'&nbsp;&nbsp;').'</b></td>
					</tr>';
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$out.='<tr>
						<td>'.t3lib_iconWorks::getIconImage('sys_dmail',$row, $BACK_PATH, 'width="18" height="16" style="vertical-align: top;"').'</td>
						<td>'.$this->linkDMail_record(fw(t3lib_div::fixed_lgd($row['subject'],30).'&nbsp;&nbsp;'),$row['uid']).'</td>
						<td>'.fw(t3lib_BEfunc::date($row['tstamp']).'&nbsp;&nbsp;').'</td>
						<td>'.($row['issent'] ? fw($LANG->getLL('dmail_yes')) : fw($LANG->getLL('dmail_no'))).'</td>
						<td>'.($row['renderedsize'] ? fw(t3lib_div::formatSize($row['renderedsize']).'&nbsp;&nbsp;') : '').'</td>
						<td>'.($row['attachment'] ? '<img '.t3lib_iconWorks::skinImg($BACK_PATH, t3lib_extMgm::extRelPath($this->extKey).'res/gfx/attach.gif', 'width="9" height="13"').' alt="'.htmlspecialchars(fw($LANG->getLL('nl_l_attach'))).'" title="'.htmlspecialchars($row['attachment']).'" width="9" height="13">' : '').'</td>
						<td>'.fw($row['type'] ? $LANG->getLL('nl_l_tUrl') : $LANG->getLL('nl_l_tPage')).'</td>
					</tr>';
		}
		
		$out='<table border="0" cellpadding="0" cellspacing="0">'.$out.'</table>';
		$theOutput.= $this->doc->section($LANG->getLL('dmail_dovsk_selectDmail').t3lib_BEfunc::cshItem($this->cshTable,'select_directmail',$BACK_PATH), $out, 1, 1, 0, TRUE);
		
			// Find all newsletters NOT created as non-deleted DMAILS
		$res = $TYPO3_DB->exec_SELECTquery(
			'DISTINCT pages.uid,pages.title',
			'pages LEFT JOIN sys_dmail ON pages.uid=sys_dmail.page',
			'(sys_dmail.page IS NULL OR sys_dmail.deleted>0)'.
				' AND pages.doktype IN (1,2)'.
				' AND pages.pid='.intval($this->id).
				t3lib_pageSelect::enableFields('pages')
			);
		$out = '';
		while ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				// Working around DBAL limitation in ON conditional expression
			$countRes = $TYPO3_DB->exec_SELECTquery(
				'uid',
				'sys_dmail',
				'sys_dmail.page='.intval($row['uid']).
					t3lib_BEfunc::deleteClause('sys_dmail')
				);
			if (!$TYPO3_DB->sql_num_rows($countRes)) {
				$out.= '<nobr><a href="index.php?id='.$this->id.'&createMailFrom_UID='.$row['uid'].'&SET[dmail_mode]=direct">'.t3lib_iconWorks::getIconImage('pages', $row, $BACK_PATH, 'width="18" height="16" title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath($row['uid'],$this->perms_clause,20)).'" style="vertical-align: top;"').
					$row['title'].'</a></nobr><br />';
			}
		}
		if (!$out) {
			$out = $LANG->getLL('dmail_msg1_crFromNL');
		}

		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section($LANG->getLL('dmail_dovsk_crFromNL').t3lib_BEfunc::cshItem($this->cshTable,'create_directmail_from_nl',$BACK_PATH), $out, 1, 1, 0, TRUE);
		
			// Create
		$out ='
				' . $LANG->getLL('dmail_HTML_url') . '<br />
				<input type="text" value="http://" name="createMailFrom_HTMLUrl"'.$TBE_TEMPLATE->formWidth(40).' /><br />' .
				$LANG->getLL('dmail_plaintext_url') . '<br />
				<input type="text" value="http://" name="createMailFrom_plainUrl"'.$TBE_TEMPLATE->formWidth(40).' /><br />' .
				$LANG->getLL('dmail_subject') . '<br />' .
				'<input type="text" value="' . $LANG->getLL('dmail_write_subject') . '" name="createMailFrom_URL" onFocus="this.value=\'\';"'.$TBE_TEMPLATE->formWidth(40).' /><br />' .
				(($this->error == 'no_valid_url')?('<br /><b>'.$LANG->getLL('dmail_no_valid_url').'</b><br /><br />'):'') .
				'<input type="submit" value="'.$LANG->getLL("dmail_createMail").'" />
				';
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section($LANG->getLL('dmail_dovsk_crFromUrl').t3lib_BEfunc::cshItem($this->cshTable,'create_directmail_from_url',$BACK_PATH), $out, 1, 1, 0, TRUE);
		
		return $theOutput;
	}
	/**
	 *
	 */
	function cmd_recip() {
		global $LANG, $TYPO3_DB, $BACK_PATH;
		
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,pid,title,description,type',
			'sys_dmail_group',
			'pid='.intval($this->id).
				t3lib_BEfunc::deleteClause('sys_dmail_group'),
			'',
			$TYPO3_DB->stripOrderBy($TCA['sys_dmail_group']['ctrl']['default_sortby'])
			);
		$out = '';
		$out.='<tr>
						<td class="'.$this->doc->bgColor5.'" colspan=2>'.fw('&nbsp;').'</td>
						<td class="'.$this->doc->bgColor5.'"><b>'.fw($LANG->sL(t3lib_BEfunc::getItemLabel('sys_dmail_group','title'))).'</b></td>
						<td class="'.$this->doc->bgColor5.'"><b>'.fw($LANG->sL(t3lib_BEfunc::getItemLabel('sys_dmail_group','type'))).'</b></td>
						<td class="'.$this->doc->bgColor5.'"><b>'.fw($LANG->sL(t3lib_BEfunc::getItemLabel('sys_dmail_group','description'))).'</b></td>
					</tr>';
		$TDparams=' valign="top"';
		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$out.='<tr>
						<td'.$TDparams.' nowrap>'.t3lib_iconWorks::getIconImage('sys_dmail_group', $row, $BACK_PATH, 'width="18" height="16" style="vertical-align: top;"').'</td>
						<td'.$TDparams.'>'.$this->editLink('sys_dmail_group',$row['uid']).'</td>
						<td'.$TDparams.' nowrap>'.$this->linkRecip_record(fw('<strong>'.t3lib_div::fixed_lgd($row['title'],30).'</strong>&nbsp;&nbsp;'),$row['uid']).'</td>
						<td'.$TDparams.' nowrap>'.fw(htmlspecialchars(t3lib_BEfunc::getProcessedValue('sys_dmail_group','type',$row['type'])).'&nbsp;&nbsp;').'</td>
						<td'.$TDparams.'>'.fw(htmlspecialchars(t3lib_BEfunc::getProcessedValue('sys_dmail_group','description',$row['description'])).'&nbsp;&nbsp;').'</td>
					</tr>';
		}
		$out='<table border="0" cellpadding="0" cellspacing="0">'.$out.'</table>';
		$theOutput.= $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'select_mailgroup',$BACK_PATH).$LANG->getLL('recip_select_mailgroup'),$out,1,1, 0, TRUE);
		
			// New:
		$out='<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[sys_dmail_group]['.$this->id.']=new',$BACK_PATH,'').'">'.t3lib_iconWorks::getIconImage('sys_dmail_group',array(),$BACK_PATH,'align="top"'). $LANG->getLL('recip_create_mailgroup_msg') . '</a>';
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'create_mailgroup',$BACK_PATH).$LANG->getLL('recip_create_mailgroup'),$out, 1, 1, 0, TRUE);
		
			// Import
		$out='<a href="index.php?id='.$this->id.'&CMD=displayImport">' . $LANG->getLL('recip_import_mailgroup_msg') . '</a>';
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section($LANG->getLL('mailgroup_import'),$out, 1, 1, 0, TRUE);
		return $theOutput;
	}
	
	/**
	 * Shows the status of the mailer engine. TODO: Should really only show some entries, or provide a browsing interface.
	 */
	function cmd_mailerengine() {
		global $LANG, $TYPO3_DB, $BACK_PATH;
		
		if (t3lib_div::_GP('invokeMailerEngine'))	{
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
						<td bgColor="'.$this->doc->bgColor5.'">'.fw('&nbsp;').'</td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('dmail_mailerengine_subject') . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('dmail_mailerengine_scheduled') . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('dmail_mailerengine_delivery_begun') . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw($LANG->getLL('dmail_mailerengine_delivery_ended') . '&nbsp;&nbsp;').'</b></td>
						<td bgColor="'.$this->doc->bgColor5.'"><b>'.fw("&nbsp;" . $LANG->getLL('dmail_mailerengine_number_sent') . '&nbsp;').'</b></td>
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
						<td>'.$this->linkDMail_record(fw(t3lib_div::fixed_lgd($row['subject'],30).'&nbsp;&nbsp;'),$row['uid']).'</td>
						<td>'.fw(t3lib_BEfunc::datetime($row['scheduled']).'&nbsp;&nbsp;').'</td>
						<td>'.fw(($row['scheduled_begin']?t3lib_BEfunc::datetime($row['scheduled_begin']):'').'&nbsp;&nbsp;').'</td>
						<td>'.fw(($row['scheduled_end']?t3lib_BEfunc::datetime($row['scheduled_end']):'').'&nbsp;&nbsp;').'</td>
						<td align=right>'.fw($count?$count:'&nbsp;').'</td>
					</tr>';
		}
		
		$out='<table border="0" cellpadding="0" cellspacing="0">'.$out.'</table>';
		$out.='<br />'. $LANG->getLL('dmail_mailerengine_current_time') . ' '.t3lib_BEfunc::datetime(time()).'<br />';
		$theOutput.= $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'mailerengine_status',$BACK_PATH).$LANG->getLL('dmail_mailerengine_status'),$out,1,1, 0, TRUE);
		
			// Invoke engine
		$out=$LANG->getLL('dmail_mailerengine_manual_explain') . '&nbsp;&nbsp;<a href="index.php?id='.$this->id.'&invokeMailerEngine=1"><strong>' . $LANG->getLL('dmail_mailerengine_invoke_now') . '</strong></a>';
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'mailerengine_invoke',$BACK_PATH).$LANG->getLL('dmail_mailerengine_manual_invoke'), $out, 1, 1, 0, TRUE);
		return $theOutput;
	}
	/**
	 *
	 */
	function cmd_conf() {
		global $TYPO3_DB, $LANG;
		
		$configArray = array(
			'spacer0' => $LANG->getLL('configure_default_headers'),
			'from_email' => array('string', $this->fName('from_email'), $LANG->getLL('from_email.description').'<br />'.$LANG->getLL('from_email.details')),
			'from_name' => array('string', $this->fName('from_name'), $LANG->getLL('from_name.description').'<br />'.$LANG->getLL('from_name.details')),
			'replyto_email' => array('string', $this->fName('replyto_email'), $LANG->getLL('replyto_email.description').'<br />'.$LANG->getLL('replyto_email.details')),
			'replyto_name' => array('string', $this->fName('replyto_name'), $LANG->getLL('replyto_name.description').'<br />'.$LANG->getLL('replyto_name.details')),
			'return_path' => array('string', $this->fName('return_path'), $LANG->getLL('return_path.description').'<br />'.$LANG->getLL('return_path.details')),
			'organisation' => array('string', $this->fName('organisation'), $LANG->getLL('organisation.description').'<br />'.$LANG->getLL('organisation.details')),
			'priority' => array('select', $this->fName('priority'), $LANG->getLL('priority.description').'<br />'.$LANG->getLL('priority.details'), array(3 => $LANG->getLL('configure_priority_normal'), 1 => $LANG->getLL('configure_priority_high'), 5 => $LANG->getLL('configure_priority_low'))),
			
			'spacer1' => $LANG->getLL('configure_default_content'),
			'sendOptions' => array('select', $this->fName('sendOptions'), $LANG->getLL('sendOptions.description').'<br />'.$LANG->getLL('sendOptions.details'), array(3 => $LANG->getLL('configure_plain_and_html') ,1 => $LANG->getLL('configure_plain_only') ,2 => $LANG->getLL('configure_html_only'))),
			'includeMedia' => array('check', $this->fName('includeMedia'), $LANG->getLL('includeMedia.description').'<br />'.$LANG->getLL('includeMedia.details')),
			'flowedFormat' => array('check', $this->fName('flowedFormat'), $LANG->getLL('flowedFormat.description').'<br />'.$LANG->getLL('flowedFormat.details')),

			'spacer2' => $LANG->getLL('configure_default_fetching'),
			'HTMLParams' => array('short', $this->fName('HTMLParams'), $LANG->getLL('configure_HTMLParams_description').'<br />'.$LANG->getLL('configure_HTMLParams_details')),
			'plainParams' => array('short', $this->fName('plainParams'), $LANG->getLL('configure_plainParams_description').'<br />'.$LANG->getLL('configure_plainParams_details')),
			'use_domain' => array('select', $this->fName('use_domain'), $LANG->getLL('use_domain.description').'<br />'.$LANG->getLL('use_domain.details'), array(0 => '')),
			
			'spacer3' => $LANG->getLL('configure_options_encoding'),
			'quick_mail_encoding' => array('select', $LANG->getLL('configure_quickmail_encoding'), $LANG->getLL('configure_quickmail_encoding_description'), array('quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit')),
			'direct_mail_encoding' => array('select', $LANG->getLL('configure_directmail_encoding'), $LANG->getLL('configure_directmail_encoding_description'), array('quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit')),
			'quick_mail_charset' => array('short', $LANG->getLL('configure_quickmail_charset'), $LANG->getLL('configure_quickmail_charset_description')),
			'direct_mail_charset' => array('short', $LANG->getLL('configure_directmail_charset'), $LANG->getLL('configure_directmail_charset_description')),
			
			'spacer4' => $LANG->getLL('configure_options_links'),
			'use_rdct' => array('check', $this->fName('use_rdct'), $LANG->getLL('use_rdct.description').'<br />'.$LANG->getLL('use_rdct.details').'<br />'.$LANG->getLL('configure_options_links_rdct')),
			'long_link_mode' => array('check', $this->fName('long_link_mode'), $LANG->getLL('long_link_mode.description')),
			'enable_jump_url' => array('check', $LANG->getLL('configure_options_links_jumpurl'), $LANG->getLL('configure_options_links_jumpurl_description')),
			'authcode_fieldList' => array('short', $this->fName('authcode_fieldList'), $LANG->getLL('authcode_fieldList.description')),
			
			'spacer5' => $LANG->getLL('configure_options_additional'),
			'http_username' => array('short', $LANG->getLL('configure_http_username'), $LANG->getLL('configure_http_username_description').'<br />'.$LANG->getLL('configure_http_username_details')),
			'http_password' => array('short', $LANG->getLL('configure_http_password'), $LANG->getLL('configure_http_password_description')),
			'userTable' => array('short', $LANG->getLL('configure_user_table'), $LANG->getLL('configure_user_table_description')),
			'test_tt_address_uids' => array('short', $LANG->getLL('configure_test_tt_address_uids'), $LANG->getLL('configure_test_tt_address_uids_description')),
			'test_dmail_group_uids' => array('short', $LANG->getLL('configure_test_dmail_group_uids'), $LANG->getLL('configure_test_dmail_group_uids_description')),
			);
		
			// Set default values
		if (!isset($this->implodedParams['plainParams'])) $this->implodedParams['plainParams'] = '&type=99';
		if (!isset($this->implodedParams['quick_mail_charset'])) $this->implodedParams['quick_mail_charset'] = 'iso-8859-1';
		if (!isset($this->implodedParams['direct_mail_charset'])) $this->implodedParams['direct_mail_charset'] = 'iso-8859-1';
		
			// Set domain selection list
		$rootline = $this->sys_page->getRootLine($this->id);
		$res_domain = $TYPO3_DB->exec_SELECTquery(
			'uid,domainName',
			'sys_domain',
			'sys_domain.pid='.intval($rootline[0]['uid']).
				t3lib_BEfunc::deleteClause('sys_domain')
			);
		while ($row_domain = $TYPO3_DB->sql_fetch_assoc($res_domain)) {
			$configArray['use_domain']['3'][$row_domain['uid']] = $row_domain['domainName'];
		}
		
		$theOutput.= $this->doc->section($LANG->getLL('configure_direct_mail_module'),str_replace('Update configuration', $LANG->getLL('configure_update_configuration'), t3lib_BEfunc::makeConfigForm($configArray,$this->implodedParams,'pageTS')),1,1,0, TRUE);
		return $theOutput;
	}
	
	function cmd_convertCategories() {
		global $LANG;
		
		$outRec = '';
		$convert_confirm = t3lib_div::_GP('convert_confirm');
		
		$out = $this->doc->spacer(10);
		$out .= $this->doc->section($LANG->getLL('convert_categories'), $this->createNewCategories());
		$out .= $this->doc->spacer(10);
		
		if (!$convert_confirm) {
			$out .= $this->doc->section($LANG->getLL('convert_simulation'), '');
		}
		$outRec .= $this->convertCategoriesInRecords('fe_users',$convert_confirm);
		$outRec .= $this->convertCategoriesInRecords('tt_address',$convert_confirm);
		if ($this->userTable)	{
			$outRec .= $this->convertCategoriesInRecords($this->userTable,$convert_confirm);
		}
		$outRec .= $this->convertCategoriesInRecords('sys_dmail_group',$convert_confirm);
		$outRec .= $this->convertCategoriesInRecords('tt_content',$convert_confirm);
		$out .= $this->doc->section($LANG->getLL('convert_records'), $outRec);

		$out .= $this->doc->spacer(10);
		if ($convert_confirm) {
			$out .= $this->doc->section($LANG->getLL('convert_completed'), '');
		} else {
			$out.= '<input type="submit" name="convert_confirm" value="' . $LANG->getLL('convert_confirm') . '" />';
			$out.= '<br /><input type="submit" name="cancel" value="' . $LANG->getLL('dmail_cancel') . '" />';
		}
		$theOutput = $this->doc->section($LANG->getLL('dmail_menu_convert_categories'), $out, 1, 1, 0, TRUE);
		return $theOutput;
	}
	
	function createNewCategories() {
		global $TYPO3_DB, $BE_USER, $LANG;
		
		$theOutput = '';
		$today = getdate();
		reset($this->modList['rows']);
		while(list(,$row) = each($this->modList['rows'])) {
			$temp = t3lib_BEfunc::getModTSconfig($row['uid'],'mod.web_modules.dmail');
			$params = $temp['properties'];
			$count = 0;
			$theOutput .= '<br />' . $LANG->getLL('convert_from_folder') . ' ' . $row['title'];
			if (is_array($params['categories.'])) {
				reset($params['categories.']);
				while(list($num,$cat) = each($params['categories.'])) {
					$theOutput .= '<br />' . $LANG->getLL('convert_category') . ': '.$cat. ' ' . $LANG->getLL('convert_with_number') . ': ' . $num;
					$res = $TYPO3_DB->exec_SELECTquery(
						'*',
						'sys_dmail_category',
						'pid='.intval($row['uid']).
							' AND l18n_parent=0'.
							' AND old_cat_number='.$TYPO3_DB->fullQuoteStr(strval($num),'sys_dmail_category')
					);
					if (!$TYPO3_DB->sql_num_rows($res)) {
						$catRec = array();
						$catRec['pid'] = intval($row['uid']);
						$catRec['category'] = $cat;
						$catRec['old_cat_number'] = strval($num);
						$catRec['tstamp'] = time();
						$catRec['l18n_parent'] = 0;
						$catRec['sys_language_uid'] = 0;
						$catRec['cruser_id'] = intval($BE_USER->user['uid']);
						$catRec['crdate'] = $today[0];
						$res = $TYPO3_DB->exec_INSERTquery(
							'sys_dmail_category',
							$catRec
							);
						$count++;
						$theOutput .= ' ' . $LANG->getLL('convert_was_converted');
					} else {
						$theOutput .= ' ' . $LANG->getLL('convert_was_already_converted');
					}
				}
			}
			$theOutput .= '<br /><br />' . $LANG->getLL('convert_from_folder_number') . ' ' . $row['title'] . ': ' . $count . '<br />';
		}
		return $theOutput;
	}
	
	function convertCategoriesInRecords($table,$convert_confirm=0) {
		global $TYPO3_DB, $TCA, $LANG;
		
		$theOutput = '<br />' . $LANG->getLL('convert_records_from_table') . ': ' . $table;
		t3lib_div::loadTCA($table);
		$mm_field = 'module_sys_dmail_category';
		if ($table == 'sys_dmail_group') {
			$mm_field = 'select_categories';
		}
		$mm_table = $TCA[$table]['columns'][$mm_field]['config']['MM'];
		
		$newConvertCount = 0;
		$notConvertCount = 0;
		$alreadyRelatedCount = 0;
		$res = $TYPO3_DB->exec_SELECTquery(
			'uid,'.$mm_field,
			$table,
			$mm_field.'!=0'.
				t3lib_pageSelect::enableFields($table)
			);
		
		while ($row = $TYPO3_DB->sql_fetch_assoc($res)) {
				// If we find a mm relation with this uid as uid_local, we assume that the record was already converted.
			$res_mm = $TYPO3_DB->exec_SELECTquery(
				'uid_local',
				$mm_table,
				'uid_local='.intval($row['uid'])
				);
			if (!$TYPO3_DB->sql_num_rows($res_mm)) {
				$categoryArr = array();
				for ($a = 0; $a <= 30; $a++) {
					if ($row[$mm_field] & pow(2, $a)) {
						$categoryArr[] = strval($a);
					}
				}
				$categoryList = implode(',', $categoryArr);
				$out = '<br />'. $LANG->getLL('convert_record') . ':' . ' ' . $row['uid'] . ' ' . $LANG->getLL('convert_had_categories') . ': ' . $categoryList;
				$this->makeCategories($table,$row);
				$categoryUids = implode(',', array_keys($this->categories));
				
				if (!empty($categoryArr) && !empty($categoryUids)) {
					$res_cat = $TYPO3_DB->exec_SELECTquery(
						'uid',
						'sys_dmail_category',
						'sys_dmail_category.uid IN (' . $categoryUids . ')'.
							' AND l18n_parent=0'.
							' AND sys_dmail_category.old_cat_number IN (' . $categoryList . ')'.
							t3lib_pageSelect::enableFields('sys_dmail_category')
						);
					if ($TYPO3_DB->sql_num_rows($res_cat)) {
						$mm_count = 0;
						$converted = array();
						while ($cat_row = $TYPO3_DB->sql_fetch_assoc($res_cat)) {
							$mm_count++;
							$mmRec['uid_local'] = intval($row['uid']);
							$mmRec['uid_foreign'] = intval($cat_row['uid']);
							$mmRec['tablenames'] = '';
							$mmRec['sorting'] = intval($mm_count);
							if ($convert_confirm) {
								$res_insert_mm = $TYPO3_DB->exec_INSERTquery(
									$mm_table,
									$mmRec
									);
							}
							$converted[] = $cat_row['uid'];
						}
						$out .= ' ' . $LANG->getLL('convert_converted_into') . ': ' . implode(',', $converted);
						$updateFields = array(
							$mm_field => intval($mm_count)
						);
						if ($convert_confirm) {
							$res_update = $TYPO3_DB->exec_UPDATEquery(
								$table,
								'uid='.intval($row['uid']),
								$updateFields
								);
						}
						$newConvertCount++;
						if ($newConvertCount > 50) $out = '';
					} else {
						$notConvertCount++;
						$out .= ' ' . $LANG->getLL('convert_could_not_be_converted');
						if ($notConvertCount > 50) $out = '';
					}
				} else {
					$notConvertCount++;
					$out .= ' ' . $LANG->getLL('convert_could_not_be_converted');
					if ($notConvertCount > 50) $out = '';
				}
				$theOutput .= $out;
			} else {
				$alreadyRelatedCount++;
			}
		}
		$theOutput .= '<br /><br />' . $LANG->getLL('number_of_records_converted_in') . ' ' . $table . ': ' . $newConvertCount;
		if ($newConvertCount > 50) {
			$theOutput .= '<br />' . $LANG->getLL('records_converted_too_many');
		}
		$theOutput .= '<br />' . $LANG->getLL('number_of_records_not_converted_in') . ' ' . $table . ': ' . $notConvertCount;
		if ($notConvertCount) {
			$theOutput .= '<br />' . $LANG->getLL('records_not_converted_explain1') . ' TCEFORM.'.$table.'.'.$mm_field.'.PAGE_TSCONFIG_IDLIST '.$LANG->getLL('records_not_converted_explain2').' '.$LANG->getLL('records_not_converted_explain3');
		}
		if ($notConvertCount > 50) {
			$theOutput .= '<br />' . $LANG->getLL('records_not_converted_too_many');
		}
		$theOutput .= '<br />' . $LANG->getLL('number_of_records_already_related_in') . ' ' . $table . ': ' . $alreadyRelatedCount . '<br />';
		
		return $theOutput;
	}
	
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function cmd_fetch($row)        {
		global $TCA, $TYPO3_DB, $LANG;
		
		$theOutput = '';
		
			// Make sure long_link_rdct_url is consistent with use_domain.
		$this->urlbase = $this->getUrlBase($row['use_domain']);
		$row['long_link_rdct_url'] = $this->urlbase;
		
			// Compile the mail
		$htmlmail = t3lib_div::makeInstance('dmailer');
		if($this->params['enable_jump_url']) {
			$htmlmail->jumperURL_prefix = $this->urlbase.'?id='.$row['page'].'&rid=###SYS_TABLE_NAME###_###USER_uid###&mid=###SYS_MAIL_ID###&aC=###SYS_AUTHCODE###&jumpurl=';
			$htmlmail->jumperURL_useId=1;
		}
		$htmlmail->start();
		$htmlmail->charset = $row['charset'];
		$htmlmail->useBase64();
		$htmlmail->http_username = $this->params['http_username'];
		$htmlmail->http_password = $this->params['http_password'];
		
		if ($this->url_plain) {
			$htmlmail->addPlain(t3lib_div::getURL($this->addUserPass($this->url_plain)));
		}
		if ($this->url_html) {
			$htmlmail->addHTML($this->url_html);    // Username and password is added in htmlmail object
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
		
			// Read again:
		$res = $TYPO3_DB->exec_SELECTquery(
			'*',
			'sys_dmail',
			'pid='.intval($this->id).
				' AND uid='.intval($this->sys_dmail_uid).
				t3lib_BEfunc::deleteClause('sys_dmail')
			);
		$row = $TYPO3_DB->sql_fetch_assoc($res);
		
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function cmd_prefetch($row)	{
		global $LANG;
		
		if ($row['sendOptions'])	{
			$msg = $LANG->getLL('prefetch_read_url') . '<br /><br />';
			if ($this->url_plain)	{
				$msg.= '<b>' . $LANG->getLL('dmail_plaintext_url') . '</b> <a href="'.$this->url_plain.'" target="testing_window">'.htmlspecialchars($this->url_plain).'</a><br />';
			}
			if ($this->url_html)	{
				$msg.= '<b>' . $LANG->getLL('dmail_HTML_url') . '</b> <a href="'.$this->url_html.'" target="testing_window">'.htmlspecialchars($this->url_html).'</a><br /><br />';
			}
			
			$msg.= $LANG->getLL('prefetch_patience') . '<br /><br />';
			$msg.= '<input type="Submit" value="' . $LANG->getLL('prefetch_fetch') . '" onClick="jumpToUrlD(\'index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=fetch\'); return false;" />  ';
			$theOutput.= $this->doc->section($LANG->getLL('prefetch_fetching'),fw($msg), 1, 1, 0, TRUE);
		} else {
			$theOutput.= $this->doc->section($LANG->getLL('dmail_error'),fw($LANG->getLL('prefetch_error_choose') .'<br /><br />'.$this->back));
		}
		$this->noView=1;
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
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
					t3lib_BEfunc::deleteClause('tt_address').
					t3lib_BEfunc::deleteClause('pages')
				);
			$msg=$LANG->getLL('testmail_individual_msg') . '<br /><br />';
			while ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$msg.='<a href="index.php?id='.$this->id.'&CMD=displayUserInfo&table=tt_address&uid='.$row['uid'].'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" /></a><a href="index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=send_mail_test&tt_address_uid='.$row['uid'].'">'.t3lib_iconWorks::getIconImage('tt_address', $row, $BACK_PATH, ' alt="'.htmlspecialchars($LANG->getLL('dmail_send')).'" title="'.htmlspecialchars($LANG->getLL('dmail_menuItems_testmail')).'" "width="18" height="16" style="margin: 0px 5px; vertical-align: top;"').htmlspecialchars($row['name'].' <'.$row['email'].'>'.($row['module_sys_dmail_html']?' html':'')).'</a><br />';
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
					t3lib_BEfunc::deleteClause('sys_dmail_group').
					t3lib_BEfunc::deleteClause('pages')
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
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function cmd_finalmail($row)	{
		global $TCA, $LANG, $TYPO3_DB, $TBE_TEMPLATE;

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
			$opt[] = '<option value="'.$row['uid'].'">'.htmlspecialchars($row['title']).'</option>';
		}
		$select = '<select name="mailgroup_uid">'.implode(chr(10),$opt).'</select>';

			// Set up form:		
		$msg="";
		$msg.= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'" />';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_final" />';
		$msg.= $LANG->getLL('schedule_mailgroup') . ' '.$select.'<br /><br />';
		$msg.= $LANG->getLL('schedule_time') . ' <input type="text" name="send_mail_datetime_hr'.'" onChange="typo3FormFieldGet(\'send_mail_datetime\', \'datetime\', \'\', 0,0);"'.$TBE_TEMPLATE->formWidth(20).'><input type="hidden" value="'.time().'" name="send_mail_datetime" /><br />';
		$this->extJSCODE.='typo3FormFieldSet(\'send_mail_datetime\', \'datetime\', \'\', 0,0);';
		$msg.= '<br /><input type="Submit" name="mailingMode_mailGroup" value="' . $LANG->getLL('schedule_send_all') . '" />';

		$theOutput.= $this->doc->section($LANG->getLL('schedule_select_mailgroup'),fw($msg), 1, 1, 0, TRUE);
		$theOutput.= $this->doc->spacer(20);

		$msg='';
		$msg.= $LANG->getLL('schedule_enter_emails') . '<br /><br />';
		$msg.= '<textarea'.$TBE_TEMPLATE->formWidthText().' rows="30" name="SET[dmail_test_email]"></textarea><br /><br />';
		$msg.= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'" />';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_final" />';
		$msg.= '<input type="Submit" name="mailingMode_simple" value="' . $LANG->getLL('dmail_send') . '" />';
		$msg.=$this->JSbottom();

		$theOutput.= $this->doc->section($LANG->getLL('schedule_list_emails'),fw($msg), 1, 1, 0, TRUE);
		$this->noView=1;
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mailgroup_uid: ...
	 * @return	[type]		...
	 */
	function getUniqueEmailsFromGroup($mailgroup_uid)	{
		$result = $this->cmd_compileMailGroup(intval($mailgroup_uid));
		$idLists = $result['queryInfo']['id_lists'];
		$emailArr = array();
		$emailArr = $this->addMailAddresses($idLists,'tt_address',$emailArr);
		$emailArr = $this->addMailAddresses($idLists,'fe_users',$emailArr);
		$emailArr = $this->addMailAddresses($idLists,'PLAINLIST',$emailArr);
		$emailArr = $this->addMailAddresses($idLists,$this->userTable,$emailArr);
		$emailArr = array_unique($emailArr);
		return $emailArr;
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function cmd_quickmail()	{
		global $TCA, $BE_USER, $LANG, $TYPO3_DB;
		
		$theOutput='';
		$errorOutput='';
		$whichMode='';
		
			// Check if send mail:
		if (t3lib_div::_GP('quick_mail_send'))	{
			$mailgroup_uid = t3lib_div::_GP('mailgroup_uid');
			$senderName = t3lib_div::_GP('senderName');
			$senderEmail = t3lib_div::_GP('senderEmail');
			$subject = t3lib_div::_GP('subject');
			$message = t3lib_div::_GP('message');
			$sendMode = t3lib_div::_GP('sendMode');
			$breakLines = t3lib_div::_GP('breakLines');
			if ($mailgroup_uid && $senderName && $senderEmail && $subject && $message) {
				$emailArr = $this->getUniqueEmailsFromGroup($mailgroup_uid);
				if (count($emailArr))	{
					if (trim($this->params['use_rdct'])) {
						$message = t3lib_div::substUrlsInPlainText($message,$this->params['long_link_mode']?'all':'76',$this->getUrlBase($this->params['use_domain']));
					}
					if ($breakLines)	{
						$message = t3lib_div::breakTextForEmail($message);
					}
					
					if (isset($this->params['quick_mail_charset'])) $charSet = $LANG->csConvObj->parse_charset($this->params['quick_mail_charset']);
					$charSet = $charSet?$charSet:'iso-8859-1';
					if ($charSet != $LANG->charSet)     {
						$message = $LANG->csConvObj->conv($message, $LANG->charSet, $charSet, 1);
					}
					
					$headers=array();
					$headers[]='From: '.$senderName.' <'.$senderEmail.'>';
					$headers[]='Return-path: '.$senderName.' <'.$senderEmail.'>';
					
					if ($sendMode=='CC')	{
						$headers[]='Cc: '.implode(',',$emailArr);
						t3lib_div::plainMailEncoded($senderEmail,$subject,$message,implode(chr(13).chr(10),$headers),$this->params['quick_mail_encoding'], $charSet);
						$whichMode=$LANG->getLL('quickmail_one_mail_cc');
					}
					if ($sendMode=='BCC')	{
						$headers[]='Bcc: '.implode(',',$emailArr);
						t3lib_div::plainMailEncoded($senderEmail,$subject,$message,implode(chr(13).chr(10),$headers),$this->params['quick_mail_encoding'], $charSet);
						$whichMode=$LANG->getLL('quickmail_one_mail_bcc');
					}
					if ($sendMode=='1-1')	{
						reset($emailArr);
						while(list(,$email)=each($emailArr))	{
							t3lib_div::plainMailEncoded($email,$subject,$message,implode(chr(13).chr(10),$headers),$this->params['quick_mail_encoding'], $charSet);
						}
						$whichMode=$LANG->getLL('quickmail_many_mails');
					}
					
					
					$msg='<strong>' . $LANG->getLL('quickmail_sent_to') . '</strong>';
					$msg.='<table border="0">
					<tr>
						<td style="width: 50px;"></td>
						<td><em>'.implode(', ',$emailArr).'</em></td>
					</tr>
					</table>
					<br />
					<strong>' . $LANG->getLL('quickmail_copy_to') . ' </strong> '.$senderEmail.'<br />
					<strong>' . $LANG->getLL('quickmail_mode') . ' </strong> '.$whichMode;
				} else {
					$msg=$LANG->getLL('quickmail_no_recipient');
				}
				$theOutput.= $this->doc->section($LANG->getLL('dmail_menu_quickMail'), $LANG->getLL('send_was_sent').'<br />'.$msg,1, 1, 0, TRUE);
			} else {
				$errorOutput.= $this->doc->section($LANG->getLL('quickmail_missing_fields'),'',1);
			}
		}

		if (!$whichMode)	{
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
				$opt[] = '<option value="'.$row['uid'].'"'.($mailgroup_uid==$row['uid']?' selected="selected"':'').'>'.htmlspecialchars($row['title']).'</option>';
			}
			$select = '<select name="mailgroup_uid">'.implode(chr(10),$opt).'</select>';
	
			$selectMode = '<select name="sendMode">
			<option value="BCC"'.($sendMode=='BCC'?' selected="selected"':'').'>' . $LANG->getLL('quickmail_one_mail_bcc') . '</option>
			<option value="CC"'.($sendMode=='CC'?' selected="selected"':'').'>' . $LANG->getLL('quickmail_one_mail_cc') . '</option>
			<option value="1-1"'.($sendMode=='1-1'?' selected="selected"':'').'>' . $LANG->getLL('quickmail_many_mails') . '</option>
			</select>';
				// Set up form:		
			$msg='';
			$msg.= '<input type="hidden" name="id" value="'.$this->id.'" />';
			$msg.= $LANG->getLL('quickmail_mailgroup') . '<br />' . $select.$selectMode.'<br />';
			if ($mailgroup_uid)	{
				$msg.=$LANG->getLL('send_recipients') . '<br /><em>'.implode(', ',$this->getUniqueEmailsFromGroup($mailgroup_uid)).'</em><br /><br />';
			}
			$msg.= $LANG->getLL('quickmail_sender_name') . '<br /><input type="text" name="senderName" value="'.($senderName?$senderName:$BE_USER->user['realName']).'"'.$this->doc->formWidth().' /><br />';
			$msg.= $LANG->getLL('quickmail_sender_email') . '<br /><input type="text" name="senderEmail" value="'.($senderEmail?$senderEmail:$BE_USER->user['email']).'"'.$this->doc->formWidth().' /><br />';
			$msg.= $LANG->getLL('dmail_subject') . '<br /><input type="text" name="subject" value="'.$subject.'"'.$this->doc->formWidth().' /><br />';
			$msg.= $LANG->getLL('quickmail_message') . '<br /><textarea rows="20" name="message"'.$this->doc->formWidthText().'>'.t3lib_div::formatForTextarea($message).'</textarea><br />';
			$msg.= $LANG->getLL('quickmail_break_lines') . ' <input type="checkbox" name="breakLines" value="1"'.($breakLines?' checked="checked"':'').' /><br /><br />';
			$msg.= $errorOutput;
			$msg.= '<input type="Submit" name="quick_mail_send" value="' . $LANG->getLL('quickmail_send_now') . '" />';

			$theOutput.= $this->doc->section($LANG->getLL('dmail_menu_quickMail'),$msg,1,1,0,TRUE);
		}
		return $theOutput;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$idLists: ...
	 * @param	[type]		$table: ...
	 * @param	[type]		$htmlmail: ...
	 * @return	[type]		...
	 */
	function sendTestMailToTable($idLists,$table,$htmlmail)	{
		$sentFlag=0;
		if (is_array($idLists[$table]))	{
			if ($table!='PLAINLIST')	{
				$recs=$this->fetchRecordsListValues($idLists[$table],$table,'*');
			} else {
				$recs = $idLists['PLAINLIST'];
			}
			reset($recs);
			while(list($k,$rec)=each($recs))	{
				$recipRow = dmailer::convertFields($rec);
				$recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories($table,$recipRow['uid']);
				$kc = substr($table,0,1);
				$htmlmail->dmailer_sendAdvanced($recipRow,$kc=='p'?'P':$kc);
				$sentFlag++;
			}
		}
		return $sentFlag;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$idLists: ...
	 * @param	[type]		$table: ...
	 * @param	[type]		$arr: ...
	 * @return	[type]		...
	 */
	function addMailAddresses($idLists,$table,$arr)	{
		if (is_array($idLists[$table]))	{
			if ($table!='PLAINLIST')	{
				$recs=$this->fetchRecordsListValues($idLists[$table],$table,'*');
			} else {
				$recs = $idLists['PLAINLIST'];
			}
			reset($recs);
			while(list($k,$rec)=each($recs))	{
				$arr[]=$rec['email'];
			}
		}
		return $arr;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function cmd_send_mail($row)	{
		global $LANG, $TYPO3_DB;

			// Preparing mailer
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->start();
		$htmlmail->dmailer_prepare($row);

		$sentFlag=false;
		if (t3lib_div::_GP('mailingMode_simple'))	{
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
				if (t3lib_div::_GP('tt_address_uid'))	{
					$res = $TYPO3_DB->exec_SELECTquery(
						'tt_address.*',
						'tt_address LEFT JOIN pages ON pages.uid=tt_address.pid',
						'tt_address.uid='.intval(t3lib_div::_GP('tt_address_uid')).
							' AND '.$this->perms_clause.
							t3lib_BEfunc::deleteClause('tt_address').
							t3lib_BEfunc::deleteClause('pages')
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

					if ($sendFlag)	{
						$theOutput.= $this->doc->section($LANG->getLL('send_sending'),fw(sprintf($LANG->getLL('send_was_sent_to_number'), $sendFlag)), 1, 1, 0, TRUE);
						$this->noView=1;
					}
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
	 * [Describe function...]
	 *
	 * @param	[type]		$pieces: ...
	 * @param	[type]		$total: ...
	 * @return	[type]		...
	 */
	function showWithPercent($pieces,$total)	{
		$total = intval($total);
		$str = $pieces;
		if ($total)	{
			$str.= ' / '.number_format(($pieces/$total*100),2).'%';
		}
		return $str;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$tableLines: ...
	 * @param	[type]		$cellParams: ...
	 * @param	[type]		$header: ...
	 * @param	[type]		$cellcmd: ...
	 * @param	[type]		$tableParams: ...
	 * @return	[type]		...
	 */
	function formatTable($tableLines,$cellParams,$header,$cellcmd=array(),$tableParams='border="0" cellpadding="2" cellspacing="1"')	{
		reset($tableLines);
		$cols = count(current($tableLines));

		reset($tableLines);
		$lines=array();
		$first=$header?1:0;
		while(list(,$r)=each($tableLines))	{
			$rowA=array();
			for($k=0;$k<$cols;$k++)	{
				$v=$r[$k];
				$v = $v ? ($cellcmd[$k]?$v:htmlspecialchars($v)) : "&nbsp;";
				if ($first) $v='<B>'.$v.'</B>';
				$rowA[]='<td'.($cellParams[$k]?" ".$cellParams[$k]:"").'>'.$v.'</td>';
			}
			$lines[]='<tr class="'.($first?'bgColor5':'bgColor4').'">'.implode('',$rowA).'</tr>';
			$first=0;
		}
		$table = '<table '.$tableParams.'>'.implode('',$lines).'</table>';
		return $table;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function cmd_stats($row)	{
		global $LANG, $BACK_PATH, $TYPO3_DB, $TBE_TEMPLATE;

		if (t3lib_div::_GP("recalcCache"))	{
			$this->makeStatTempTableContent($row);
		}
		$thisurl = 'index.php?id='.$this->id.'&sys_dmail_uid='.$row['uid'].'&CMD='.$this->CMD.'&recalcCache=1';
		$output.=t3lib_iconWorks::getIconImage('sys_dmail',$row,$BACK_PATH,'style="vertical-align: top;"').$row['subject'].'<br />';

			// *****************************
			// Mail responses, general:
			// *****************************			

		$queryArray = array('response_type,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']), 'response_type');
		$table = $this->getQueryRows($queryArray, 'response_type');

			// Plaintext/HTML
		$queryArray = array('html_sent,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=0', 'html_sent');
		$table2 = $this->getQueryRows($queryArray, 'html_sent');
		
			// Unique responses, html
		$res = $TYPO3_DB->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=1', 'rid,rtbl', 'counter');
		$unique_html_responses = $TYPO3_DB->sql_num_rows($res);

			// Unique responses, Plain
		$res = $TYPO3_DB->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=2', 'rid,rtbl', 'counter');
		$unique_plain_responses = $TYPO3_DB->sql_num_rows($res);

			// Unique responses, pings
		$res = $TYPO3_DB->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=-1', 'rid,rtbl', 'counter');
		$unique_ping_responses = $TYPO3_DB->sql_num_rows($res);

		$tblLines=array();
		$tblLines[]=array('',$LANG->getLL('stats_total'),$LANG->getLL('stats_HTML'),$LANG->getLL('stats_plaintext'));

		$sent_total = intval($table['0']['counter']);
		$sent_html = intval($table2['3']['counter']+$table2['1']['counter']);
		$sent_plain = intval($table2['2']['counter']);
		$tblLines[]=array($LANG->getLL('stats_mails_sent'),$sent_total,$sent_html,$sent_plain);
		$tblLines[]=array($LANG->getLL('stats_mails_returned'),$this->showWithPercent($table['-127']['counter'],$sent_total));
		$tblLines[]=array($LANG->getLL('stats_HTML_mails_viewed'),'',$this->showWithPercent($unique_ping_responses,$sent_html));
		$tblLines[]=array($LANG->getLL('stats_unique_responses'),$this->showWithPercent($unique_html_responses+$unique_plain_responses,$sent_total),$this->showWithPercent($unique_html_responses,$sent_html),$this->showWithPercent($unique_plain_responses,$sent_plain));
		
		$output.='<br /><strong>' . $LANG->getLL('stats_general_information') . '</strong>';
		$output.=$this->formatTable($tblLines,array('nowrap','nowrap align="right"','nowrap align="right"','nowrap align="right"'),1);
		
			// ******************
			// Links:
			// ******************
		
			// Most popular links, html:
		$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=1', 'url_id', 'counter');
		$htmlUrlsTable=$this->getQueryRows($queryArray,'url_id');
		
			// Most popular links, plain:
		$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=2', 'url_id', 'counter');
		$plainUrlsTable=$this->getQueryRows($queryArray,'url_id');
		
		// Find urls:
		$temp_unpackedMail = unserialize($row['mailContent']);
		$urlArr=array();
		$urlMd5Map=array();
		if (is_array($temp_unpackedMail['html']['hrefs']))	{
			reset($temp_unpackedMail['html']['hrefs']);
			while(list($k,$v)=each($temp_unpackedMail['html']['hrefs']))	{
				$urlArr[$k]=$v['absRef'];
				$urlMd5Map[md5($v['absRef'])]=$k;
			}
		}
		if (is_array($temp_unpackedMail['plain']['link_ids']))	{
			reset($temp_unpackedMail['plain']['link_ids']);
			while(list($k,$v)=each($temp_unpackedMail['plain']['link_ids']))	{
				$urlArr[intval(-$k)]=$v;
			}
		}
		// Traverse plain urls:
		reset($plainUrlsTable);
		$plainUrlsTable_mapped=array();
		while(list($id,$c)=each($plainUrlsTable))	{
			$url = $urlArr[intval($id)];
			if (isset($urlMd5Map[md5($url)]))	{
				$plainUrlsTable_mapped[$urlMd5Map[md5($url)]]=$c;
			} else {
				$plainUrlsTable_mapped[$id]=$c;
			}
		}

		// Traverse html urls:
		$urlCounter['html']=array();
		reset($htmlUrlsTable);
		while(list($id,$c)=each($htmlUrlsTable))	{	
			$urlCounter['html'][$id]=$c['counter'];
		}

		$urlCounter['total']=$urlCounter['html'];

		// Traverse plain urls:
		$urlCounter['plain']=array();
		reset($plainUrlsTable_mapped);
		while(list($id,$c)=each($plainUrlsTable_mapped))	{
			$urlCounter['plain'][$id]=$c['counter'];
			$urlCounter['total'][$id]+=$c['counter'];
		}

		$tblLines=array();
		$tblLines[]=array('',$LANG->getLL('stats_total'),$LANG->getLL('stats_HTML'),$LANG->getLL('stats_plaintext'),'');
		$tblLines[]=array($LANG->getLL('stats_total_responses'),$this->showWithPercent($table['1']['counter']+$table['2']['counter'],$sent_total),$this->showWithPercent($table['1']['counter'],$sent_html),$this->showWithPercent($table['2']['counter'],$sent_plain));
		$tblLines[]=array($LANG->getLL('stats_unique_responses'),$this->showWithPercent($unique_html_responses+$unique_plain_responses,$sent_total),$this->showWithPercent($unique_html_responses,$sent_html),$this->showWithPercent($unique_plain_responses,$sent_plain));
		$tblLines[]=array($LANG->getLL('stats_links_clicked_per_respondent'),
			($unique_html_responses+$unique_plain_responses ? number_format(($table['1']['counter']+$table['2']['counter'])/($unique_html_responses+$unique_plain_responses),2):''),
			($unique_html_responses ? number_format(($table['1']['counter'])/($unique_html_responses),2):''),
			($unique_plain_responses ? number_format(($table['2']['counter'])/($unique_plain_responses),2):'')
		);
		arsort($urlCounter['total']);
		arsort($urlCounter['html']);
		arsort($urlCounter['plain']);
		reset($urlCounter['total']);
		while(list($id,$c)=each($urlCounter['total']))	{
			$uParts = parse_url($urlArr[intval($id)]);
			$urlstr = $uParts['path'].($uParts['query']?'?'.$uParts['query']:'');
			if (strlen($urlstr)<10)	{
				$urlstr=$uParts['host'].$urlstr;
			}
			$urlstr=substr($urlstr,0,40);
			$img='<a href="'.htmlspecialchars($urlArr[$id]).'"><img '.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/zoom.gif', 'width="12" height="12"').' title="'.htmlspecialchars($urlArr[$id]).'" /></a>';
			$tblLines[]=array($LANG->getLL('stats_link') . ' #'.$id.' ('.$urlstr.')',$c,$urlCounter['html'][$id],$urlCounter['plain'][$id],$img);
		}
		$output.='<br /><strong>' . $LANG->getLL('stats_response') . '</strong>';
		$output.=$this->formatTable($tblLines,array('nowrap','nowrap align="right"','nowrap align="right"','nowrap align="right"','nowrap align="right"'),1,array(0,0,0,0,1));

		
			// ******************
			// Returned mails
			// ******************
		$queryArray = array('count(*) as counter,return_code', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=-127', 'return_code');
		$table_ret = $this->getQueryRows($queryArray,'return_code');

		$tblLines=array();
		$tblLines[]=array("",$LANG->getLL('stats_count'));
		$tblLines[]=array($LANG->getLL('stats_total_mails_returned'),$table["-127"]['counter']);
		$tblLines[]=array($LANG->getLL('stats_recipient_unknown'),$this->showWithPercent($table_ret['550']['counter']+$table_ret["553"]['counter'],$table["-127"]['counter']));
		$tblLines[]=array($LANG->getLL('stats_mailbox_full'),$this->showWithPercent($table_ret['551']['counter'],$table['-127']['counter']));
		$tblLines[]=array($LANG->getLL('stats_bad_host'),$this->showWithPercent($table_ret['552']['counter'],$table['-127']['counter']));
		$tblLines[]=array($LANG->getLL('stats_error_in_header'),$this->showWithPercent($table_ret['554']['counter'],$table['-127']['counter']));
		$tblLines[]=array($LANG->getLL('stats_reason_unkown'),$this->showWithPercent($table_ret['-1']['counter'],$table['-127']['counter']));

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned') . '</strong>';
		$output.=$this->formatTable($tblLines,array('nowrap','nowrap align="right"'),1);

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned') . '</strong><br>';
		$output.='<a href="'.$thisurl.'&returnList=1">' . $LANG->getLL('stats_list_returned') . '</a><br />';
		$output.='<a href="'.$thisurl.'&returnDisable=1">' . $LANG->getLL('stats_disable_returned') . '</a><br />';
		$output.='<a href="'.$thisurl.'&returnCSV=1">' . $LANG->getLL('stats_CSV_returned') . '</a><br />';

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
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . $this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . $this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');}
			}
			if (t3lib_div::_GP('returnDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('returnCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=$this->fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=$this->fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned_unknown_recipient') . '</strong><br />';
		$output.='<a href="'.$thisurl.'&unknownList=1">' . $LANG->getLL('stats_list_returned_unknown_recipient') . '</a><br />';
		$output.='<a href="'.$thisurl.'&unknownDisable=1">' . $LANG->getLL('stats_disable_returned_unknown_recipient') . '</a><br />';
		$output.='<a href="'.$thisurl.'&unknownCSV=1">' . $LANG->getLL('stats_CSV_returned_unknown_recipient') . '</a><br />';

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
					$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . $this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
				}
				if (is_array($idLists['fe_users'])) {
					$output.= '<br />' . $LANG->getLL('stats_website_users') . $this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
				}
			}
			if (t3lib_div::_GP('unknownDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('unknownCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=$this->fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=$this->fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_unknown_recipient_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned_mailbox_full') . '</strong><br />';
		$output.='<a href="'.$thisurl.'&fullList=1">' . $LANG->getLL('stats_list_returned_mailbox_full') . '</a><br />';
		$output.='<a href="'.$thisurl.'&fullDisable=1">' . $LANG->getLL('stats_disable_returned_mailbox_full') . '</a><br />';
		$output.='<a href="'.$thisurl.'&fullCSV=1">' . $LANG->getLL('stats_CSV_returned_mailbox_full') . '</a><br />';

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
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . $this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . $this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');}
			}
			if (t3lib_div::_GP('fullDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('fullCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=$this->fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=$this->fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_mailbox_full_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned_bad_host') . '</strong><br />';
		$output.='<a href="'.$thisurl.'&badHostList=1">' . $LANG->getLL('stats_list_returned_bad_host') . '</a><br />';
		$output.='<a href="'.$thisurl.'&badHostDisable=1">' . $LANG->getLL('stats_disable_returned_bad_host') . '</a><br />';
		$output.='<a href="'.$thisurl.'&badHostCSV=1">' . $LANG->getLL('stats_CSV_returned_bad_host') . '</a><br />';

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
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . $this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . $this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');}
			}
			if (t3lib_div::_GP('badHostDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('badHostCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=$this->fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=$this->fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_bad_host_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned_bad_header') . '</strong><br />';
		$output.='<a href="'.$thisurl.'&badHeaderList=1">' . $LANG->getLL('stats_list_returned_bad_header') . '</a><br />';
		$output.='<a href="'.$thisurl.'&badHeaderDisable=1">' . $LANG->getLL('stats_disable_returned_bad_header') . '</a><br />';
		$output.='<a href="'.$thisurl.'&badHeaderCSV=1">' . $LANG->getLL('stats_CSV_returned_bad_header') . '</a><br />';

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
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . $this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . $this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');}
			}

			if (t3lib_div::_GP('badHeaderDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('badHeaderCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=$this->fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=$this->fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_bad_header_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
			}
		}

		$output.='<br /><strong>' . $LANG->getLL('stats_mails_returned_reason_unknown') . '</strong><br />';
		$output.='<a href="'.$thisurl.'&reasonUnknownList=1">' . $LANG->getLL('stats_list_returned_reason_unknown') . '</a><br />';
		$output.='<a href="'.$thisurl.'&reasonUnknownDisable=1">' . $LANG->getLL('stats_disable_returned_reason_unknown') . '</a><br />';
		$output.='<a href="'.$thisurl.'&reasonUnknownCSV=1">' . $LANG->getLL('stats_CSV_returned_reason_unknown') . '</a><br />';

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
				if (is_array($idLists['tt_address']))	{$output.='<br />' . $LANG->getLL('stats_emails') . '<br />' . $this->getRecordList($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');}
				if (is_array($idLists['fe_users']))		{$output.= '<br />' . $LANG->getLL('stats_website_users') . $this->getRecordList($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');}
			}
			if (t3lib_div::_GP('reasonUnknownDisable'))	{
				if (is_array($idLists['tt_address']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_emails_disabled');
				}
				if (is_array($idLists['fe_users']))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
					$output.='<br />' . $c . ' ' . $LANG->getLL('stats_website_users_disabled');
				}
			}
			if (t3lib_div::_GP('reasonUnknownCSV'))	{
				$emails=array();
				if (is_array($idLists['tt_address']))	{
					$arr=$this->fetchRecordsListValues($idLists['tt_address'],'tt_address');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				if (is_array($idLists['fe_users']))	{
					$arr=$this->fetchRecordsListValues($idLists['fe_users'],'fe_users');
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v['email'];
					}
				}
				$output.='<br />' . $LANG->getLL('stats_emails_returned_reason_unknown_list') .  '<br />';
				$output.='<textarea'.$TBE_TEMPLATE->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode(chr(10), $emails)).'</textarea>';
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
	 * [Describe function...]
	 *
	 * @param	[type]		$arr: ...
	 * @param	[type]		$table: ...
	 * @return	[type]		...
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
	 * [Describe function...]
	 *
	 * @param	[type]		$mrow: ...
	 * @return	[type]		...
	 */
	function makeStatTempTableContent($mrow)	{
		// Remove old:
		$GLOBALS['TYPO3_DB']->exec_DELETEquery(
			'cache_sys_dmail_stat',
			'mid='.intval($mrow['uid'])
			);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'rid,rtbl,tstamp,response_type,url_id,html_sent,size',
			'sys_dmail_maillog',
			'mid='.intval($mrow['uid']),
			'',
			'rtbl,rid,tstamp'
			);

		$currentRec = '';
		$recRec = '';
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
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
				$recRec[($row['response_type']==1?"html_links":"plain_links")][] = $row['tstamp'];
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
	 * [Describe function...]
	 *
	 * @param	[type]		$recRec: ...
	 * @return	[type]		...
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
	 * [Describe function...]
	 *
	 * @param	[type]		$query: ...
	 * @return	[type]		...
	 */
	function showQueryRes($query)	{
		$res = $GLOBALS['TYPO3_DB']->sql(TYPO3_db,$query);
		$lines = array();
		$first = 1;
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			if ($first)	{
				$lines[]='<tr bgcolor=#cccccc><td><b>'.implode('</b></td><td><b>',array_keys($row)).'</b></td></tr>';
				$first=0;
			}
			$lines[]='<tr bgcolor=#eeeeee><td>'.implode('</td><td>',$row).'</td></tr>';
		}
		$str = '<table border=1 cellpadding=0 cellspacing=0>'.implode('',$lines).'</table>';
		return $str;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$queryArray: ...
	 * @param	[type]		$key_field: ...
	 * @return	[type]		...
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
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function directMail_defaultView($row)	{
		global $LANG, $BE_USER, $BACK_PATH;
		
			// Render record:
		$dmailTitle=t3lib_iconWorks::getIconImage('sys_dmail',$row,$BACK_PATH,'style="vertical-align: top;"').$row['subject'];
		$out='';
		$Eparams='&edit[sys_dmail]['.$row['uid'].']=edit';
		$out .= '<tr><td colspan=3 bgColor="' . $this->doc->bgColor5 . '" valign=top>'.fw($this->fName('subject').' <b>'.t3lib_div::fixed_lgd($row['subject'],30).'  </b>').'</td></tr>';
		$nameArr = explode(',','from_name,from_email,replyto_name,replyto_email,organisation,return_path,priority,attachment,type,page,sendOptions,includeMedia,flowedFormat,plainParams,HTMLParams,encoding,charset,issent,renderedsize');
		while(list(,$name)=each($nameArr))	{
			$out.='<tr><td bgColor="'.$this->doc->bgColor4.'">'.fw($this->fName($name)).'</td><td bgColor="'.$this->doc->bgColor4.'">'.fw(str_replace('Yes', $LANG->getLL('yes'),t3lib_BEfunc::getProcessedValue('sys_dmail',$name,$row[$name]))).'</td></tr>';
		}
		$out='<table border="0" cellpadding="1" cellspacing="1" width="460">'.$out.'</table>';
		if (!$row['issent'])	{
			if ($BE_USER->check('tables_modify','sys_dmail')) {
				$retUrl = 'returnUrl='.rawurlencode(t3lib_div::linkThisScript(array('sys_dmail_uid' => $row['uid'], 'createMailFrom_UID' => '', 'createMailFrom_URL' => '')));
				$editOnClick = 'document.location=\''.$BACK_PATH.'alt_doc.php?'.$retUrl.$Eparams.'\'; return false;';
				$out.='<br /><a href="#" onClick="' .$editOnClick . '"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />'.fw('<b>'.$LANG->getLL('dmail_edit').'</b>').'</a>';
			} else {
				$out.='<br /><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />'.fw('('.$LANG->getLL('dmail_noEdit_noPerms').')');
			}
		} else {
			$out.='<br /><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" />'.fw('('.$LANG->getLL('dmail_noEdit_isSent').')');
		}
		
		if ($row['type']==0 && $row['page'])	{
			$pageRow = t3lib_BEfunc::getRecord('pages',$row['page']);
			if ($pageRow)	{
				$out .= '<br /><br />' . $LANG->getLL('dmail_basedOn') . '<br />';
				$out .= '<nobr><a href="index.php?id='.$this->id.'&CMD=displayPageInfo&pages_uid='.$pageRow['uid'].'&SET[dmail_mode]=news">'.t3lib_iconWorks::getIconImage('pages',$pageRow, $BACK_PATH, 'width="18" height="16" title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($pageRow['uid'],$this->perms_clause,20)).'" style="vertical-align: top;"').htmlspecialchars($pageRow['title']).'</a></nobr><br />';
			}
		}
		
		$theOutput.= $this->doc->section($LANG->getLL('dmail_view').' '.$dmailTitle, $out, 1, 1, 0, TRUE);
		
		return $theOutput;
	}
	
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row: ...
	 * @return	[type]		...
	 */
	function directMail_optionsMenu($row, $current='') {
		global $LANG, $BACK_PATH;
		
			// Direct mail options menu:
		$menuItems = array();
		$menuItems[0]=$LANG->getLL('dmail_menuItems');
		if (!$row['issent'])	{
			$menuItems['prefetch']=$LANG->getLL('dmail_menuItems_prefetch');
		}
		if ($row['from_email'] && $row['renderedsize'])	{
			$menuItems['testmail']=$LANG->getLL('dmail_menuItems_testmail');
			if (!$row['issent'])	{
				$menuItems['finalmail']=$LANG->getLL('dmail_menuItems_finalmail');
			}
		}
		if ($row['scheduled'] && $row['issent']) {
			$menuItems['stats']=$LANG->getLL('dmail_menuItems_stats');
		}
		$menu = t3lib_BEfunc::getFuncMenu($this->id,'CMD',$current,$menuItems,'','&sys_dmail_uid=' . $row['uid']);
		
		return $this->doc->section('','<div style="text-align: right;">'.$menu.t3lib_BEfunc::cshItem($this->cshTable,'directmail_actions',$BACK_PATH).'</div>');
	}
	
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$str: ...
	 * @param	[type]		$uid: ...
	 * @return	[type]		...
	 */
	function linkDMail_record($str,$uid)	{
		return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct">'.$str.'</a>';
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$str: ...
	 * @param	[type]		$uid: ...
	 * @return	[type]		...
	 */
	function linkRecip_record($str,$uid)	{
		return '<a href="index.php?id='.$this->id.'&CMD=displayMailGroup&group_uid='.$uid.'&SET[dmail_mode]=recip">'.$str.'</a>';
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$name: ...
	 * @return	[type]		...
	 */
	function fName($name)	{
		global $LANG;
		return stripslashes($LANG->sL(t3lib_BEfunc::getItemLabel('sys_dmail',$name)));
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$formname: ...
	 * @return	[type]		...
	 */
	function JSbottom($formname='forms[0]')	{
		if ($this->extJSCODE)	{
			$out.='
			<script language="javascript" type="text/javascript" src="'.$GLOBALS['BACK_PATH'].'t3lib/jsfunc.evalfield.js"></script>
			<script language="javascript" type="text/javascript">
				var evalFunc = new evalFunc;
				function typo3FormFieldSet(theField, evallist, is_in, checkbox, checkboxValue)	{
					var theFObj = new evalFunc_dummy (evallist,is_in, checkbox, checkboxValue);
					var theValue = document.'.$formname.'[theField].value;
					if (checkbox && theValue==checkboxValue)	{
						document.'.$formname.'[theField+"_hr"].value=\'\';
						if (document.'.$formname.'[theField+"_cb"])	document.'.$formname.'[theField+"_cb"].checked = \'\';
					} else {
						document.'.$formname.'[theField+"_hr"].value = evalFunc.outputObjValue(theFObj, theValue);
						if (document.'.$formname.'[theField+"_cb"])	document.'.$formname.'[theField+"_cb"].checked = \'on\';
					}
				}
				function typo3FormFieldGet(theField, evallist, is_in, checkbox, checkboxValue, checkbox_off)	{
					var theFObj = new evalFunc_dummy (evallist,is_in, checkbox, checkboxValue);
					if (checkbox_off)	{
						document.'.$formname.'[theField].value=checkboxValue;
					}else{
						document.'.$formname.'[theField].value = evalFunc.evalObjValue(theFObj, document.'.$formname.'[theField+"_hr"].value);
					}
					typo3FormFieldSet(theField, evallist, is_in, checkbox, checkboxValue);
				}
			</script>
			<script language="javascript" type="text/javascript">'.$this->extJSCODE.'</script>';
			return $out;
		}
	}
	
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$formname: ...
	 * @return	[type]		...
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
	 * [Describe function...]
	 *
	 * @param	[type]		$formname: ...
	 * @return	string		urlbase
	 */
	function getUrlBase($domainUid) {
		global $TYPO3_DB;
		
		$domainName = '';
		$scheme = '';
		$port = '';
		if ($domainUid) {
			$res_domain = $TYPO3_DB->exec_SELECTquery(
				'domainName',
				'sys_domain',
				'uid='.intval($domainUid).
					t3lib_BEfunc::deleteClause('sys_domain')
				);
			if ($row_domain = $TYPO3_DB->sql_fetch_assoc($res_domain)) {
				$domainName = $row_domain['domainName'];
				$url_parts = parse_url(t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR'));
				$scheme = $url_parts['scheme'];
				$port = $url_parts['port'];
			}
		}
		
		return ($domainName ? (($scheme?$scheme:'http') . '://' . $domainName . ($port?':'.$port:'') . '/') : substr(t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR'),0,-(strlen(t3lib_div::resolveBackPath(TYPO3_mainDir.TYPO3_MOD_PATH))))).'index.php';
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.mod_web_dmail.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod/class.mod_web_dmail.php']);
}

?>
