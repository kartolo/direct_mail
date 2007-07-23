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
 * @author		Kasper Skårhøj <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage	tx_directmail
 *
 * @version		$Id: 11 2006-11-16 10:22:16Z ivan $
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *  103: class tx_directmail_recipient_list extends t3lib_SCbase
 *  137:     function init()
 *  190:     function main()
 *  270:     function printContent()
 *  280:     function moduleContent()
 *  318:     function mailModule_main()
 *  346:     function cmd_recip()
 *  398:     function editLink($table,$uid)
 *  413:     function linkRecip_record($str,$uid)
 *  423:     function cmd_compileMailGroup($group_uid)
 *  531:     function cmd_displayMailGroup($result)
 *  606:     function getRecursiveSelect($id,$perms_clause)
 *  623:     function cleanPlainList($plainlist)
 *  639:     function update_specialQuery($mailGroup)
 *  694:     function cmd_specialQuery($mailGroup)
 *  730:     function getIdList($table,$pidList,$group_uid,$cat)
 *  821:     function getStaticIdList($table,$uid)
 *  880:     function getSpecialQueryIdList($table,$group)
 *  908:     function getMailGroups($list,$parsedGroups)
 *  942:     function downloadCSV($idArr)
 *  969:     function rearrangeCsvValues($lines)
 * 1039:     function rearrangePlainMails($plainMails)
 * 1060:     function getCsvValues($str,$sep=',')
 * 1081:     function getRecordList($listArr,$table,$dim=0,$editLinkFlag=1)
 * 1123:     function fetchRecordsListValues($listArr,$table,$fields='uid,name,email')
 * 1148:     function cmd_displayUserInfo()
 * 1251:     function cmd_displayImport()
 * 1559:     function makeCategories($table,$row)
 * 1600:     function getRecordOverlay($table,$row,$sys_language_content,$OLmode='')
 *
 *              SECTION: function for importing tt_address
 * 1665:     function filterCSV($mappedCSV)
 * 1698:     function doImport ($csvData)
 * 1807:     function makeDropdown($name, $option, $selected)
 * 1828:     function makeHidden($name,$value="")
 * 1846:     function readCSV()
 * 1873:     function readExampleCSV($records=3)
 * 1906:     function formatTable($tableLines,$cellParams,$header,$cellcmd=array(),$tableParams='border="0" cellpadding="2" cellspacing="3"',$switchBG=0)
 * 1939:     function userTempFolder()
 * 1955:     function writeTempFile()
 * 2008:     function checkUpload()
 *
 * TOTAL FUNCTIONS: 38
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_basicfilefunc.php');
require_once (PATH_t3lib.'class.t3lib_extfilefunc.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.mailselect.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_static.php');

/**
 * Recipient list module for tx_directmail extension
 *
 */
class tx_directmail_recipient_list extends t3lib_SCbase {
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
	 * first initialization of global variables
	 *
	 * @return	void		initialize global variables
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
		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

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
	 * The main function.
	 *
	 * @return	void		update global variable 'content'
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
			$this->doc->form='<form action="" method="post" enctype="multipart/form-data">';

			//CSS
			//hide textarea in import
			$this->doc->inDocStyles = 'textarea.hide{display:none;}';

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
			if ($module == 'dmail') {

					// Render content:
				$this->content.=$this->doc->startPage($LANG->getLL('mailgroup_header'));
				$this->content.=$this->doc->section($LANG->getLL('mailgroup_header').t3lib_BEfunc::cshItem($this->cshTable,'',$BACK_PATH), '', 1, 1, 0 , TRUE);
				$this->moduleContent();
			} else {
				$this->content.=$this->doc->startPage($LANG->getLL(''));
				$this->content.=$this->doc->section($LANG->getLL('header_recip'), $LANG->getLL('select_folder'), 1, 1, 0 , TRUE);
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
	 * @return	void		print out 'content' variable
	 */
	function printContent()	{
		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * show the module content
	 *
	 * @return	string		The compiled content of the module.
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
	 * @return	string		HTML content
	 */
	function mailModule_main()	{
		global $LANG, $TYPO3_DB, $TYPO3_CONF_VARS;

				// COMMAND:
			switch($this->CMD) {
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
					$theOutput.= $this->cmd_recip();
					break;
			}

		return $theOutput;
	}

	/**
	 * shows the existing recipient lists and shows link to create a new one or import a list
	 *
	 * @return	string		List of existing recipient list, link to create a new list and link to import
	 */
	function cmd_recip() {
		global $LANG, $TYPO3_DB, $BACK_PATH, $TCA;

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
	 * shows edit link
	 *
	 * @param	string		$table: table name
	 * @param	integer		$uid: record uid
	 * @return	string		the edit link
	 */
	function editLink($table,$uid)	{
		global $LANG, $BACK_PATH;

		$params = '&edit['.$table.']['.$uid.']=edit';
		$str = '<a href="#" onClick="'.t3lib_BEfunc::editOnClick($params,$BACK_PATH,'').'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$LANG->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$LANG->getLL("dmail_edit").'" /></a>';
		return $str;
	}

	/**
	 * shows link to show the recipient infos
	 *
	 * @param	string		$str: name of the recipient link
	 * @param	integer		$uid: uid of the recipient link
	 * @return	string		the link
	 */
	function linkRecip_record($str,$uid)	{
		return '<a href="index.php?id='.$this->id.'&CMD=displayMailGroup&group_uid='.$uid.'&SET[dmail_mode]=recip">'.$str.'</a>';
	}

	/**
	 * put all recipients uid from all table into an array
	 *
	 * @param	integer		$group_uid: uid of the group
	 * @return	array		list of the uid in an array
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
						$recipients = tx_directmail_static::rearrangeCsvValues(tx_directmail_static::getCsvValues($mailGroup['list']),$this->fieldList);
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
					$groups = array_unique(tx_directmail_static::getMailGroups($mailGroup['mail_groups'],array($mailGroup['uid'],$this->perms_clause)));
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
			}
		}
		$outputArray = array(
			'queryInfo' => array('id_lists' => $id_lists)
			);
		return $outputArray;
	}

	/**
	 * display infos of the mail group
	 *
	 * @param	array		$result: array containing list of recipient uid
	 * @return	string		list of all recipient (HTML)
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
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_address'),tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address',$this->id,$this->doc->bgColor4));
				$theOutput.= $this->doc->spacer(20);
			}
			if (is_array($idLists['fe_users'])) {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_fe_users'),tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users',$this->id,$this->doc->bgColor4));
			$theOutput.= $this->doc->spacer(20);
			}
			if (is_array($idLists['PLAINLIST'])) {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_plain_list'),tx_directmail_static::getRecordList($idLists['PLAINLIST'],'default',$this->id,$this->doc->bgColor4,1));
				$theOutput.= $this->doc->spacer(20);
			}
			if (is_array($idLists[$this->userTable])) {
				$theOutput.= $this->doc->section($LANG->getLL('mailgroup_table_custom') . ' ' . $this->userTable,tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable,$this->id,$this->doc->bgColor4));
			}
			break;
		default:
			if (t3lib_div::_GP('csv'))	{
				$csvValue=t3lib_div::_GP('csv');
				if ($csvValue=='PLAINLIST')	{
					$this->downloadCSV($idLists['PLAINLIST']);
				} elseif (t3lib_div::inList('tt_address,fe_users,'.$this->userTable, $csvValue)) {
					$this->downloadCSV(tx_directmail_static::fetchRecordsListValues($idLists[$csvValue],$csvValue,(($csvValue == 'fe_users') ? str_replace('phone','telephone',$this->fieldList) : $this->fieldList).',tstamp'));
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
	 * update recipient list record with a special query
	 *
	 * @param	array		$mailGroup: DB records
	 * @return	array		updated DB records
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
	 * show HTML form to make special query
	 *
	 * @param	array		$mailGroup: recipient list DB record
	 * @return	string		HTML form to make a special query
	 */
	function cmd_specialQuery($mailGroup) {
		global $LANG;

		$this->queryGenerator->init('dmail_queryConfig',$this->MOD_SETTINGS['queryTable']);

		if ($this->MOD_SETTINGS['queryTable'] && $this->MOD_SETTINGS['queryConfig']) {
			$this->queryGenerator->queryConfig = unserialize($this->MOD_SETTINGS['queryConfig']);
			$this->queryGenerator->extFieldLists['queryFields'] = 'uid';
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
	 * send csv values as download by sending appropriate HTML header
	 *
	 * @param	array		$idArr: values to be put into csv
	 * @return	void		sent HML header for a file download
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
	 * shows user's info and categories
	 *
	 * @return	string		HTML showing user's info and the categories
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
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::BEenableFields('tt_address').
					t3lib_BEfunc::deleteClause('tt_address')
				);
			$row = $TYPO3_DB->sql_fetch_assoc($res);
			break;
		case 'fe_users':
			$res = $TYPO3_DB->exec_SELECTquery(
				'fe_users.*',
				'fe_users LEFT JOIN pages ON pages.uid=fe_users.pid',
				'fe_users.uid='.intval($uid).
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::BEenableFields('fe_users').
					t3lib_BEfunc::deleteClause('fe_users')
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

			$this->categories = tx_directmail_static::makeCategories($table, $row, $this->sys_language_uid);
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
	 * import CSV-Data in step-by-step mode
	 *
	 * @return	string		HTML form
	 */
	function cmd_displayImport()	{
		global $LANG, $BACK_PATH, $TYPO3_DB;

		$this->indata = t3lib_div::_GP('CSV_IMPORT');
		$currentFileInfo = t3lib_basicFileFunctions::getTotalFileInfo($this->indata['newFile']);
		$currentFileName = $currentFileInfo['file'];
		$curentFileSize = t3lib_basicFileFunctions::formatSize($currentFileInfo['size']);
		$currentFileMessage = $currentFileName.' ('.$curentFileSize.')';

		if(empty($this->indata['csv']) && !empty($_FILES['upload_1']['name'])){
			$this->indata['newFile'] = $this->checkUpload();
		} elseif(!empty($this->indata['csv']) && empty($_FILES['upload_1']['name'])) {
			if(((strpos($currentFileInfo['file'],'import')=== false)?0:1) && ($currentFileInfo['realFileext'] === 'txt')){
				//do nothing
			} else {
				unset($this->indata['newFile']);
			}
		} else if(!empty($this->indata['newFile'])){
			$this->indata['newFile'] = $this->indata['newFile'];
		}
		$step = t3lib_div::_GP('importStep');

		if($this->indata['back']){
			$stepCurrent = $step['back'];
		} elseif ($this->indata['next']){
			$stepCurrent = $step['next'];
		}

		if(strlen($this->indata['csv']) > 0){
			$this->indata['mode'] = 'csv';
			$this->indata['newFile'] = $this->writeTempFile();
		}
		elseif(!empty($this->indata['newFile']))
			$this->indata['mode'] = 'file';
		else
			unset($stepCurrent);

		//check if "email" is mapped
		if($stepCurrent === 'import'){
			$map = $this->indata['map'];
			$error = array();
			//check noMap
			$newMap = t3lib_div::removeArrayEntryByValue(t3lib_div::uniqueArray($map),'noMap');
			if (empty($newMap)){
				$error[]='noMap';
			} elseif(!t3lib_div::inArray($map,'email')){
				$error[] = 'email';
			}
			if ($error){
				$stepCurrent = 'mapping';
			}
		}

		switch($stepCurrent){
			case 'conf':
					//get list of sysfolder
//TODO: maybe only subtree von this->id??
				$res = $TYPO3_DB->exec_SELECTquery(
					'uid,title',
					'pages',
					'doktype = 254'.t3lib_BEfunc::deleteClause('pages').t3lib_BEfunc::BEenableFields('pages'),
					'',
					'uid'
				);
				$optStorage = array();
				while($row = $TYPO3_DB->sql_fetch_assoc($res)){
					$optStorage[] = array($row['uid'],$row['title'].' [uid:'.$row['uid'].']');
				}

				$optDelimiter=array(
					array('comma',$LANG->getLL('mailgroup_import_separator_comma')),
					array('semicolon',$LANG->getLL('mailgroup_import_separator_semicolon')),
					array('colon',$LANG->getLL('mailgroup_import_separator_colon')),
					array('tab',$LANG->getLL('mailgroup_import_separator_tab'))
				);

				$optEncap = array(
					array('doubleQuote',' " '),
					array('singleQuote'," ' "),
				);

				//TODO: make it variable?
				$optUnique = array(
					array('email','email'),
					array('name','name')
				);

					//show configuration
				$out = '<hr /><h3>'.$LANG->getLL('mailgroup_import_header_conf').'</h3>';
				$tblLines = array();
				$tblLines[]=array($LANG->getLL('mailgroup_import_storage'),$this->makeDropdown('CSV_IMPORT[storage]',$optStorage,$this->indata['storage']));
				$tblLines[]=array($LANG->getLL('mailgroup_import_remove_existing'),'<input type="checkbox" name="CSV_IMPORT[remove_existing]" value="1"'.(!$this->indata['remove_existing']?'':' checked="checked"').'/> ');
				$tblLines[]=array($LANG->getLL('mailgroup_import_first_fieldnames'),'<input type="checkbox" name="CSV_IMPORT[first_fieldname]" value="1"'.(!$this->indata['first_fieldname']?'':' checked="checked"').'/> ');
				$tblLines[]=array($LANG->getLL('mailgroup_import_separator'),$this->makeDropdown('CSV_IMPORT[delimiter]', $optDelimiter,$this->indata['delimiter']));
				$tblLines[]=array($LANG->getLL('mailgroup_import_encapsulation'),$this->makeDropdown('CSV_IMPORT[encapsulation]', $optEncap , $this->indata['encapsulation']));
				$tblLines[]=array($LANG->getLL('mailgroup_import_csv_validemail-description'),'<input type="checkbox" name="CSV_IMPORT[valid_email]" value="1"'.(!$this->indata['valid_email']?'':' checked="checked"').'/> ');
				$tblLines[]=array($LANG->getLL('mailgroup_import_csv_dublette-description'),'<input type="checkbox" name="CSV_IMPORT[remove_dublette]" value="1"'.(!$this->indata['remove_dublette']?'':' checked="checked"').'/> ');
				$tblLines[]=array($LANG->getLL('mailgroup_import_update_unique'),'<input type="checkbox" name="CSV_IMPORT[update_unique]" value="1"'.(!$this->indata['update_unique']?'':' checked="checked"').'/>');
				$tblLines[]=array($LANG->getLL('mailgroup_import_record_unique'),$this->makeDropdown('CSV_IMPORT[record_unique]',$optUnique,$this->indata['record_unique']));

				$out.= $this->formatTable($tblLines,array('width=300','nowrap'),0,array(0,1));
				$out.= '<br /><br />';
				$out.= '<input type="submit" name="CSV_IMPORT[back]" value="'.$LANG->getLL('mailgroup_import_back').'" />
						<input type="submit" name="CSV_IMPORT[next]" value="' . $LANG->getLL('mailgroup_import_next') . '" />'.
						$this->makeHidden(array(
							'CMD' => 'displayImport',
							'importStep[next]' => 'mapping',
							'importStep[back]' => 'upload',
							'CSV_IMPORT[newFile]' => $this->indata['newFile']));
			break;

			case 'mapping':
					//show mapping form
				$out = '<hr /><h3>'.$LANG->getLL('mailgroup_import_mapping_conf').'</h3>';

				if($this->indata['first_fieldname']){
						//read csv
					$csvData = $this->readExampleCSV(4);
					$csv_firstRow = $csvData[0];
					$csvData = array_slice($csvData,1);
				}else{
						//read csv
					$csvData = $this->readExampleCSV(3);
					$fieldsAmount = count($csvData[0]);
					$csv_firstRow = array();
					for ($i=0;$i<$fieldsAmount;$i++){
						$csv_firstRow[] = 'field_'.$i;
					}
				}

				//read tt_address TCA
				$no_map = array('hidden','image');
				$tt_address_fields = array_keys($GLOBALS['TCA']['tt_address']['columns']);
				foreach($no_map as $v){
					$tt_address_fields = t3lib_div::removeArrayEntryByValue($tt_address_fields, $v);
				}
				$mapFields = array();
				foreach($tt_address_fields as $map){
					$mapFields[] = array($map, str_replace(':','',$LANG->sL($GLOBALS['TCA']['tt_address']['columns'][$map]['label'])));
				}
				//add 'no value'
				array_unshift($mapFields, array('noMap',$LANG->getLL('mailgroup_import_mapping_mapTo')));
				reset($csv_firstRow);
				reset($csvData);

				$tblLines = array();
				$tblLines[] = array($LANG->getLL('mailgroup_import_mapping_number'),$LANG->getLL('mailgroup_import_mapping_description'),$LANG->getLL('mailgroup_import_mapping_mapping'),$LANG->getLL('mailgroup_import_mapping_value'));
				for($i=0;$i<(count($csv_firstRow));$i++){
						//example CSV
					$exampleLines = array();
					for($j=0;$j<(count($csvData));$j++){
						$exampleLines[] = array($csvData[$j][$i]);
					}
					$tblLines[] = array($i+1,$csv_firstRow[$i],$this->makeDropdown('CSV_IMPORT[map]['.($i).']', $mapFields, $this->indata['map'][$i]), $this->formatTable($exampleLines,array('nowrap'),0,array(0),'border="0" cellpadding="2" cellspacing="0" style="width:100%"',1));
				}

				if($error){
					$out.= '<h3>'.$LANG->getLL('mailgroup_import_mapping_error').'</h3>';
					$out.= $LANG->getLL('mailgroup_import_mapping_error_detail').'<br /><ul>';
					foreach($error as $errorDetail){
						$out.= '<li>'.$LANG->getLL('mailgroup_import_mapping_error_'.$errorDetail).'</li>';
					}
					$out.= '</ul>';
				}

				$out.= $this->formatTable($tblLines,array('nowrap','nowrap','nowrap','nowrap'),1,array(0,0,1,1),'border="0" cellpadding="2" cellspacing="0"',1);
				$out.= '<br /><br />';
				$out.= '<input type="submit" name="CSV_IMPORT[back]" value="'.$LANG->getLL('mailgroup_import_back').'"/>
						<input type="submit" name="CSV_IMPORT[next]" value="' . $LANG->getLL('mailgroup_import_next') . '"/>'.
						$this->makeHidden(array(
							'CMD' => 'displayImport',
							'importStep[next]' => 'import',
							'importStep[back]' => 'conf',
							'CSV_IMPORT[newFile]' => $this->indata['newFile'],
							'CSV_IMPORT[storage]' => $this->indata['storage'],
							'CSV_IMPORT[remove_existing]' => $this->indata['remove_existing'],
							'CSV_IMPORT[first_fieldname]' => $this->indata['first_fieldname'],
							'CSV_IMPORT[delimiter]' => $this->indata['delimiter'],
							'CSV_IMPORT[encapsulation]' => $this->indata['encapsulation'],
							'CSV_IMPORT[valid_email]' => $this->indata['valid_email'],
							'CSV_IMPORT[remove_dublette]' => $this->indata['remove_dublette'],
							'CSV_IMPORT[update_unique]' => $this->indata['update_unique'],
							'CSV_IMPORT[record_unique]' => $this->indata['record_unique']));

			break;

			case 'import':
					//show import messages
				$out.= '<hr /><h3>'.$LANG->getLL('mailgroup_import_ready_import').'</h3>';
				$out.= $LANG->getLL('mailgroup_import_ready_import_label').'<br /><br />';

				$out.= '<input type="submit" name="CSV_IMPORT[back]" value="'.$LANG->getLL('mailgroup_import_back').'" />
						<input type="submit" name="CSV_IMPORT[next]" value="' . $LANG->getLL('mailgroup_import_import') . '" />'.
						$this->makeHidden(array(
							'CMD' => 'displayImport',
							'importStep[next]' => 'startImport',
							'importStep[back]' => 'mapping',
							'CSV_IMPORT[newFile]' => $this->indata['newFile'],
							'CSV_IMPORT[storage]' => $this->indata['storage'],
							'CSV_IMPORT[remove_existing]' => $this->indata['remove_existing'],
							'CSV_IMPORT[first_fieldname]' => $this->indata['first_fieldname'],
							'CSV_IMPORT[delimiter]' => $this->indata['delimiter'],
							'CSV_IMPORT[encapsulation]' => $this->indata['encapsulation'],
							'CSV_IMPORT[valid_email]' => $this->indata['valid_email'],
							'CSV_IMPORT[remove_dublette]' => $this->indata['remove_dublette'],
							'CSV_IMPORT[update_unique]' => $this->indata['update_unique'],
							'CSV_IMPORT[record_unique]' => $this->indata['record_unique']));
				$hiddenMapped = array();
				foreach($this->indata['map'] as $fieldNr => $fieldMapped){
					$hiddenMapped[]	= $this->makeHidden('CSV_IMPORT[map]['.$fieldNr.']', $fieldMapped);
				}
				$out.=implode('',$hiddenMapped);

			break;

			case 'startImport':
					//starting import & show errors
				//read csv
				if($this->indata['first_fieldname']){
						//read csv
					$csvData = $this->readCSV();
					$csvData = array_slice($csvData,1);
				}else{
						//read csv
					$csvData = $this->readCSV();
				}

					//show not imported record and reasons,
				$result = $this->doImport($csvData);
				$out = $LANG->getLL('mailgroup_import_done');

				foreach($result as $act => $importData){
					$tblLines = array();
					$tblLines1 = array();
					$tblLines[] = array($LANG->getLL('mailgroup_import_report_'.$act));
					foreach($importData as $k => $v){
						$tblLines1[]= array($k+1,$v['name'],$v['email']);
					}
					$tblLines[] = array($this->formatTable($tblLines1,array('nowrap','nowrap','nowrap'),0));
					$out.= $this->formatTable($tblLines, array('nowrap'), 1, array(1), 'border="0" cellpadding="2" cellspacing="0"');
				}

				//back button
				$out.= '<input type="submit" name="CSV_IMPORT[back]" value="'.$LANG->getLL('mailgroup_import_back').'" />'.
						$this->makeHidden(array(
							'CMD' => 'displayImport',
							'importStep[back]' => 'import',
							'CSV_IMPORT[newFile]' => $this->indata['newFile'],
							'CSV_IMPORT[storage]' => $this->indata['storage'],
							'CSV_IMPORT[remove_existing]' => $this->indata['remove_existing'],
							'CSV_IMPORT[first_fieldname]' => $this->indata['first_fieldname'],
							'CSV_IMPORT[delimiter]' => $this->indata['delimiter'],
							'CSV_IMPORT[encapsulation]' => $this->indata['encapsulation'],
							'CSV_IMPORT[valid_email]' => $this->indata['valid_email'],
							'CSV_IMPORT[remove_dublette]' => $this->indata['remove_dublette'],
							'CSV_IMPORT[update_unique]' => $this->indata['update_unique'],
							'CSV_IMPORT[record_unique]' => $this->indata['record_unique']));
				$hiddenMapped = array();
				foreach($this->indata['map'] as $fieldNr => $fieldMapped){
					$hiddenMapped[]	= $this->makeHidden('CSV_IMPORT[map]['.$fieldNr.']', $fieldMapped);
				}
				$out.=implode('',$hiddenMapped);

			break;

			case 'upload':
			default:
					//show upload file form
				$out = '<hr /><h3>'.$LANG->getLL('mailgroup_import_header_upload').'</h3>';
				$tempDir = $this->userTempFolder();
				$tblLines = array();
				$tblLines[]=array($LANG->getLL('mailgroup_import_upload_file'),'<input type="file" name="upload_1" size="30" />');
				$tblLines[]=array('','<input type="checkbox" name="overwriteExistingFiles" value="1"'.' '.($_POST['importNow'] ? 'disabled' : '').'/>&nbsp;'.$LANG->getLL('mailgroup_import_overwrite'));
				if(($this->indata['mode'] == 'file') && !(((strpos($currentFileInfo['file'],'import')=== false)?0:1) && ($currentFileInfo['realFileext'] === 'txt'))){
					$tblLines[]=array($LANG->getLL('mailgroup_import_current_file'),'<b>'.$currentFileMessage.'</b>');
				}
				$out .= $this->formatTable($tblLines, array('nowrap','nowrap'), 0, array(1,1));
				if(((strpos($currentFileInfo['file'],'import')=== false)?0:1) && ($currentFileInfo['realFileext'] === 'txt')){
					$handleCSV = fopen($this->indata['newFile'],'r');
					$this->indata['csv'] = fread($handleCSV, filesize($this->indata['newFile']));
					fclose($handleCSV);
				}
				$tblLines = array();
				$tblLines[]=array('<b>'.$LANG->getLL('mailgroup_import_or').'</b>');
				$tblLines[]=array($LANG->getLL('mailgroup_import_paste_csv'));
				$tblLines[]=array('<textarea name="CSV_IMPORT[csv]" rows="25" wrap="off"'.$this->doc->formWidthText(48,'','off').'>'.t3lib_div::formatForTextarea($this->indata['csv']).'</textarea>');
				$tblLines[]=array('<input type="submit" name="CSV_IMPORT[next]" value="' . $LANG->getLL('mailgroup_import_next') . '" />');
				$out.= $this->formatTable($tblLines, array('nowrap'), 0, array(1));
				$out.= '<input type="hidden" name="CMD" value="displayImport" />
						<input type="hidden" name="importStep[next]" value="conf" />
						<input type="hidden" name="file[upload][1][target]" value="'.htmlspecialchars($tempDir).'" '.($_POST['importNow'] ? 'disabled' : '').'/>
						<input type="hidden" name="file[upload][1][data]" value="1" />
						<input type="hidden" name="CSV_IMPORT[newFile]" value ="'.$this->indata['newFile'].'">';
			break;
		}

		$theOutput.= $this->doc->section($LANG->getLL('mailgroup_import').t3lib_BEfunc::cshItem($this->cshTable,'mailgroup_import',$BACK_PATH),$out, 1, 1, 0, TRUE);
		return $theOutput;
	}

	/*****
	 * function for importing tt_address
	 *****/

	/**
	 * filter doublette from input csv data
	 *
	 * @param	array		$mappedCSV: mapped csv
	 * @return	array		filtered csv and double csv
	 */
	function filterCSV($mappedCSV){
		$cmpCSV = $mappedCSV;
		$remove=array();

		foreach($mappedCSV as $k => $csvData){
			if(!in_array($k,$remove)){
				$found=0;
				foreach($cmpCSV as $kk =>$cmpData){
					if($k != $kk){
						if($csvData[$this->indata['record_unique']] == $cmpData[$this->indata['record_unique']]){
							$double[]=$mappedCSV[$kk];
							$filtered[] = $csvData;
							$remove[]=$kk;
							$found=1;
						}
					}
				}
				if(!$found){
					$filtered[] = $csvData;
				}
			}
		}
		$csv['clean'] = $filtered;
		$csv['double'] = $double;
		return $csv;
	}

	/**
	 * start importing users
	 *
	 * @param	array		$csvData: the csv raw data
	 * @return	array		array containing doublette, updated and invalid-email records
	 */
	function doImport ($csvData){
		global $TYPO3_DB;
		$resultImport = array();

		//empty table if flag is set
		if($this->indata['remove_existing'] && $this->indata['doImport']){
			$res = $TYPO3_DB->exec_DELETEquery('tt_address','pid = '.$TYPO3_DB->fullQuoteStr($this->indata['storage'],'tt_address'));
		}

		$mappedCSV = array();
		$invalidEmailCSV = array();
		foreach($csvData as $k => $dataArray){
			$tempData = array();
			$invalidEmail = 0;
			foreach($dataArray as $kk => $fieldData){
				if($this->indata['map'][$kk] !== 'noMap'){
					if(($this->indata['valid_email']) && ($this->indata['map'][$kk] === 'email')){
						$invalidEmail = t3lib_div::validEmail($fieldData)?0:1;
						$tempData[$this->indata['map'][$kk]] = $fieldData;
					} else {
						$tempData[$this->indata['map'][$kk]] = $fieldData;
					}
				}
			}
			if ($invalidEmail){
				$invalidEmailCSV[] = $tempData;
			} else {
				$mappedCSV[]=$tempData;
			}
		}

		//remove doublette from csv data
		if($this->indata['remove_dublette']){
			$filteredCSV = $this->filterCSV($mappedCSV);
			unset($mappedCSV);
			$mappedCSV = $filteredCSV['clean'];
		}

			//array for the process_datamap();
		$data=array();
		if($this->indata['update_unique']){
			$res = $TYPO3_DB->exec_SELECTquery(
						'uid,'.$this->indata['record_unique'],
						'tt_address',
						'pid = '.$this->indata['storage'].t3lib_BEfunc::deleteClause('tt_address')
					);
			while ($row = $TYPO3_DB->sql_fetch_row($res)){
				$user[]=$row[1];
				$userID[]=$row[0];
			}

			//check user one by one, new or update
			$c=1;
			foreach($mappedCSV as $k => $dataArray){
				$foundUser = array();
				$foundUser = array_keys($user, $dataArray[$this->indata['record_unique']]);
				if(is_array($foundUser) && !empty($foundUser)){
					if(count($foundUser)==1){
						$data['tt_address'][$userID[$foundUser[0]]]= $dataArray;
						$data['tt_address'][$userID[$foundUser[0]]]['pid'] = $this->indata['storage'];
						$resultImport['update'][]=$dataArray;
					} else {
						//which one to update? all?
						foreach($foundUser as $kk => $updateUid){
							$data['tt_address'][$userID[$foundUser[$kk]]]= $dataArray;
							$data['tt_address'][$userID[$foundUser[$kk]]]['pid'] = $this->indata['storage'];
						}
						$resultImport['update'][]=$dataArray;
					}
				} else {
					//write new user
					$data['tt_address']['NEW'.$c] = $dataArray;
					$data['tt_address']['NEW'.$c]['pid'] = $this->indata['storage'];
					$resultImport['new'][]=$dataArray;
					$c++;		//counter
				}
			}
		} else {
			//no update, import all
			$c=1;
			foreach($mappedCSV as $k => $dataArray){
				$data['tt_address']['NEW'.$c] = $dataArray;
				$data['tt_address']['NEW'.$c]['pid'] = $this->indata['storage'];
				$resultImport['new'][]=$dataArray;
				$c++;
			}
		}

		$resultImport['invalid_email']=$invalidEmailCSV;
		$resultImport['double']=(is_array($filteredCSV['double']))?$filteredCSV['double']: array();

		// start importing
		$tce = t3lib_div::makeInstance('t3lib_TCEmain');
		$tce->stripslashes_values=0;
		$tce->enableLogging=0;
		$tce->start($data,array());
		$tce->process_datamap();

		return $resultImport;
	}

	/**
	 * make dropdown menu
	 *
	 * @param	string		$name: name of the dropdown
	 * @param	array		$option: array of array (v,k)
	 * @param	string		$selected: set selected flag
	 * @return	string		HTML code of the dropdown
	 */
	function makeDropdown($name, $option, $selected){
		global $LANG;

		$opt = array();
		foreach($option as $v){
			if (is_array($v)){
				$opt[] = '<option value="'.htmlspecialchars($v[0]).'" '.($selected==$v[0]?' selected="selected"':'').'>'.htmlspecialchars($v[1]).'</option>';
			}
		}

		$dropdown = '<select name="'.$name.'">'.implode('',$opt).'</select>';
		return $dropdown;
	}

	/**
	 * make hidden field
	 *
	 * @param	mixed		$name: name of the hidden field (string) or name => value (array)
	 * @param	string		$value: value of the hidden field
	 * @return	string		HTML code
	 */
	function makeHidden($name,$value=""){
		if(is_array($name)){
			$hiddenFields = array();
			foreach($name as $n=>$v){
				$hiddenFields[] = '<input type="hidden" name="'.$n.'" value="'.$v.'" />';
			}
			return implode('',$hiddenFields);
		} else {
			return '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
		}
	}

	/**
	 * Read in the given CSV file. The function is used during the final file import.
	 * Removes first the first data row if the CSV has fieldnames.
	 *
	 * @return	array		file content in array
	 */
	function readCSV() {
		$mydata = array();
		$handle = fopen($this->indata['newFile'], "r");
		$i=0;
		$delimiter = $this->indata['delimiter'];
		$encaps = $this->indata['encapsulation'];
		$delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
		$delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
		$delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
		$delimiter = ($delimiter === 'tab') ? chr(9) : $delimiter;
		$encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
		$encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;
		while (($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== FALSE) {
			$mydata[] = $data;
		}
		fclose ($handle);
		reset ($mydata);
		return $mydata;
	}

	/**
	 * Read in the given CSV file. Only showed a couple of the CSV values as example
	 * Removes first the first data row if the CSV has fieldnames.
	 *
	 * @param	integer		number of example values
	 * @return	array		file content in array
	 */
	function readExampleCSV($records=3) {
		$mydata = array();
		$handle = fopen($this->indata['newFile'], "r");
		$i=0;
		$delimiter = $this->indata['delimiter'];
		$encaps = $this->indata['encapsulation'];
		$delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
		$delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
		$delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
		$delimiter = ($delimiter === 'tab') ? chr(9) : $delimiter;
		$encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
		$encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;
		while (($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== FALSE) {
			$mydata[] = $data;
			$i++;
			if($i>=$records)break;
		}
		fclose ($handle);
		reset ($mydata);
		return $mydata;
	}

	/**
	 * formating the given array in to HTML table
	 *
	 * @param	array		$tableLines: array of table row -> array of cells
	 * @param	array		$cellParams: cells' parameter
	 * @param	boolean		$header: first tableLines is table header
	 * @param	array		$cellcmd: escaped cells' value
	 * @param	string		$tableParams: table's parameter
	 * @param	boolean		$switchBG: if set, background of each row will be alternating. Default is not alternating
	 * @return	string		HTML the table
	 */
	function formatTable($tableLines,$cellParams,$header,$cellcmd=array(),$tableParams='border="0" cellpadding="2" cellspacing="3"',$switchBG=0)	{
		reset($tableLines);
		$cols = count(current($tableLines));

		reset($tableLines);
		$lines=array();
		$first=$header?1:0;
		$c=0;
		while(list(,$r)=each($tableLines))	{
			$rowA=array();
			for($k=0;$k<$cols;$k++)	{
				$v=$r[$k];
				$v = strlen($v) ? ($cellcmd[$k]?$v:htmlspecialchars($v)) : "&nbsp;";
				if ($first) $v='<B>'.$v.'</B>';
				$rowA[]='<td'.($cellParams[$k]?" ".$cellParams[$k]:"").'>'.$v.'</td>';
			}
			if($switchBG){
				$lines[]='<tr '.(($c%2)?'bgcolor="#FFEFBF"':'bgcolor="#FFE79F"').'>'.implode('',$rowA).'</tr>';
			} else {
				$lines[]='<tr class="'.($first?'bgColor5':'bgColor4').'">'.implode('',$rowA).'</tr>';
			}
			$first=0;
			$c++;
		}
		$table = '<table '.$tableParams.'>'.implode('',$lines).'</table>';
		return $table;
	}

	/**
	 * Returns first temporary folder of the user account (from $FILEMOUNTS)
	 *
	 * @return	string		Absolute path to first "_temp_" folder of the current user, otherwise blank.
	 */
	function userTempFolder() {
		global $FILEMOUNTS;

		foreach($FILEMOUNTS as $filePathInfo) {
			$tempFolder = $filePathInfo['path'].'_temp_/';
			if (@is_dir($tempFolder))	{
				return $tempFolder;
			}
		}
	}

	/**
	 * write CSV Data to a temporary file and will be used for the import
	 *
	 * @return	string		path of the temp file
	 */
	function writeTempFile(){
		global $FILEMOUNTS,$TYPO3_CONF_VARS,$BE_USER,$LANG;

		unset($this->fileProcessor);
		// Initializing:
		$this->fileProcessor = t3lib_div::makeInstance('t3lib_extFileFunctions');
		$this->fileProcessor->init($FILEMOUNTS, $TYPO3_CONF_VARS['BE']['fileExtensions']);
		$this->fileProcessor->init_actionPerms($BE_USER->user['fileoper_perms']);
		$this->fileProcessor->dontCheckForUnique = 1;

		if (is_array($FILEMOUNTS) && !empty($FILEMOUNTS)) {
			// we have a filemount
			// do something here
		} else {
			// we don't have a valid file mount
			// should be fixed

			// this throws a error message because we have no rights to upload files
			// to our extension's own upload folder
			// further investigation needed
			$file['upload']['1']['target'] = t3lib_div::getFileAbsFileName('uploads/tx_directmail/');
		}

		// Checking referer / executing:
		$refInfo = parse_url(t3lib_div::getIndpEnv('HTTP_REFERER'));
		$httpHost = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');

		if(empty($this->indata['newFile'])){
				//new file
			$file['newfile']['1']['target']=$this->userTempFolder();
			$file['newfile']['1']['data']='import_'.$GLOBALS['EXEC_TIME'].'.txt';
			if ($httpHost != $refInfo['host'] && $this->vC != $BE_USER->veriCode() && !$TYPO3_CONF_VARS['SYS']['doNotCheckReferer'])	{
				$this->fileProcessor->writeLog(0,2,1,'Referer host "%s" and server host "%s" did not match!',array($refInfo['host'],$httpHost));
			} else {
				$this->fileProcessor->start($file);
				$newfile = $this->fileProcessor->func_newfile($file['newfile']['1']);
			}
		} else {
			$newfile = $this->indata['newFile'];
		}
		if($newfile){
			$csvFile['data']=$this->indata['csv'];
			$csvFile['target']= $newfile;
			$write=$this->fileProcessor->func_edit($csvFile);
		}
		return $newfile;
	}

	/**
	 * Checks if a file has been uploaded and returns the complete physical fileinfo if so.
	 *
	 * @return	string		the complete physical file name, including path info.
	 */
	function checkUpload()	{

		global $FILEMOUNTS,$TYPO3_CONF_VARS,$BE_USER,$LANG;

		$file = t3lib_div::_GP('file');

		// Initializing:
		$this->fileProcessor = t3lib_div::makeInstance('t3lib_extFileFunctions');
		$this->fileProcessor->init($FILEMOUNTS, $TYPO3_CONF_VARS['BE']['fileExtensions']);
		$this->fileProcessor->init_actionPerms($BE_USER->user['fileoper_perms']);
		$this->fileProcessor->dontCheckForUnique = t3lib_div::_GP('overwriteExistingFiles') ? 1 : 0;

		if (is_array($FILEMOUNTS) && !empty($FILEMOUNTS)) {
			// we have a filemount
			// do something here
		} else {
			// we don't have a valid file mount
			// should be fixed

			// this throws a error message because we have no rights to upload files
			// to our extension's own upload folder
			// further investigation needed
			$file['upload']['1']['target'] = t3lib_div::getFileAbsFileName('uploads/tx_directmail/');
		}

		// Checking referer / executing:
		$refInfo = parse_url(t3lib_div::getIndpEnv('HTTP_REFERER'));
		$httpHost = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');

		if ($httpHost != $refInfo['host'] && $this->vC != $BE_USER->veriCode() && !$TYPO3_CONF_VARS['SYS']['doNotCheckReferer'])	{
			$this->fileProcessor->writeLog(0,2,1,'Referer host "%s" and server host "%s" did not match!',array($refInfo['host'],$httpHost));
		} else {
			$this->fileProcessor->start($file);
			$newfile = $this->fileProcessor->func_upload($file['upload']['1']);
		}
		return $newfile;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod3/class.tx_directmail_recipient_list.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod3/class.tx_directmail_recipient_list.php']);
}

?>