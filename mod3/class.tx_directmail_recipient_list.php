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
 * @version		$Id: class.tx_directmail_recipient_list.php 30331 2010-02-22 22:27:07Z ivankartolo $
 */

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.mailselect.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_static.php');
require_once (t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_importer.php');

/**
 * Recipient list module for tx_directmail extension
 *
 */
class tx_directmail_recipient_list extends t3lib_SCbase {
	var $fieldList = 'uid,name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';
	// Internal
	var $params = array();
	var $perms_clause = '';
	var $pageinfo = '';
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

	/**
	 * @var mailSelect
	 */
	var $queryGenerator;
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';

	/**
	 * first initialization of global variables
	 *
	 * @return	void		initialize global variables
	 */
	function init()	{
		$this->MCONF = $GLOBALS['MCONF'];

		$this->include_once[]=PATH_t3lib.'class.t3lib_tcemain.php';

		parent::init();

		$temp = t3lib_BEfunc::getModTSconfig($this->id,'mod.web_modules.dmail');
		$this->params = $temp['properties'];
		$this->implodedParams = t3lib_BEfunc::implodeTSParams($this->params);
		if ($this->params['userTable'] && is_array($GLOBALS['TCA'][$this->params['userTable']]))	{
			$this->userTable = $this->params['userTable'];
			t3lib_div::loadTCA($this->userTable);
			$this->allowedTables[] = $this->userTable;
		}
		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize the query generator
		$this->queryGenerator = t3lib_div::makeInstance('mailSelect');

			// initialize backend user language
		if ($GLOBALS['LANG']->lang && t3lib_extMgm::isLoaded('static_info_tables')) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3='.$GLOBALS['TYPO3_DB']->fullQuoteStr($GLOBALS['LANG']->lang,'static_languages').
					t3lib_BEfunc::BEenableFields('sys_language').
					t3lib_BEfunc::deleteClause('sys_language').
					t3lib_BEfunc::deleteClause('static_languages')
				);
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
			}
		}
			// load contextual help
		$this->cshTable = '_MOD_'.$this->MCONF['name'];
		if ($GLOBALS['BE_USER']->uc['edit_showFieldHelp']){
			$GLOBALS['LANG']->loadSingleTableDescription($this->cshTable);
		}

		t3lib_div::loadTCA('sys_dmail');

	}

	/**
	 * The main function.
	 *
	 * @return	void		update global variable 'content'
	 */
	function main()	{
		$this->CMD = t3lib_div::_GP('CMD');
		$this->pages_uid = intval(t3lib_div::_GP('pages_uid'));
		$this->sys_dmail_uid = intval(t3lib_div::_GP('sys_dmail_uid'));
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($GLOBALS['BE_USER']->user['admin'] && !$this->id))	{

			// Draw the header.
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $GLOBALS['BACK_PATH'];
			$this->doc->setModuleTemplate('EXT:direct_mail/mod3/mod_template.html');
			$this->doc->form='<form action="" method="post" name="' . $this->formname . '" enctype="multipart/form-data">';

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

			$markers = array(
				'FLASHMESSAGES' => '',
				'CONTENT' => '',
			);

			$docHeaderButtons = array(
				'PAGEPATH' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
				'SHORTCUT' => '',
				'CSH' => t3lib_BEfunc::cshItem($this->cshTable, '', $GLOBALS['BACK_PATH'])
			);
				// shortcut icon
			if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
				$docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
			}


			$module = $this->pageinfo['module'];
			if (!$module) {
				$pidrec = t3lib_BEfunc::getRecord('pages', intval($this->pageinfo['pid']));
				$module = $pidrec['module'];
			}

				// Render content:
			if ($module == 'dmail') {
						// Direct mail module
				if ($this->pageinfo['doktype']==254 && $this->pageinfo['module']=='dmail') {
					$markers['CONTENT'] = '<h2>' . $GLOBALS['LANG']->getLL('mailgroup_header') . '</h2>'
					. $this->moduleContent();
				} elseif ($this->id != 0) {
					/** @var $flashMessage t3lib_FlashMessage */
					$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
						$GLOBALS['LANG']->getLL('dmail_noRegular'),
						$GLOBALS['LANG']->getLL('dmail_newsletters'),
						t3lib_FlashMessage::WARNING
					);
					$markers['FLASHMESSAGES'] = $flashMessage->render();
				}
			} else {
				/** @var $flashMessage t3lib_FlashMessage */
				$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
					$GLOBALS['LANG']->getLL('select_folder'),
					$GLOBALS['LANG']->getLL('header_recip'),
					t3lib_FlashMessage::WARNING
				);
				$markers['FLASHMESSAGES'] = $flashMessage->render();
			}

			$this->content = $this->doc->startPage($GLOBALS['LANG']->getLL('mailgroup_header'));
			$this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());


		} else {
			// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $GLOBALS['BACK_PATH'];

			$this->content.=$this->doc->startPage($GLOBALS['LANG']->getLL('title'));
			$this->content.=$this->doc->header($GLOBALS['LANG']->getLL('title'));
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void		print out 'content' variable
	 */
	function printContent()	{
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * show the module content
	 *
	 * @return	string		The compiled content of the module.
	 */
	protected function moduleContent() {

			// COMMAND:
		switch ($this->CMD) {
			case 'displayUserInfo':
				$theOutput = $this->cmd_displayUserInfo();
			break;
			case 'displayMailGroup':
				$result = $this->cmd_compileMailGroup(intval(t3lib_div::_GP('group_uid')));
				$theOutput = $this->cmd_displayMailGroup($result);
			break;
			case 'displayImport':
				/** @var $importer tx_directmail_importer */
				$importer = t3lib_div::makeInstance('tx_directmail_importer');
				$importer->init($this);
				$theOutput = $importer->cmd_displayImport();
				break;
			default:
				$theOutput = $this->showExistingRecipientLists();
				break;
		}

		return $theOutput;
	}

	/**
	 * shows the existing recipient lists and shows link to create a new one or import a list
	 *
	 * @return	string		List of existing recipient list, link to create a new list and link to import
	 */
	public function showExistingRecipientLists() {
		$out = '<tr>
					<td class="t3-row-header" colspan="2">&nbsp;</td>
					<td class="t3-row-header">' . $GLOBALS['LANG']->sL(t3lib_BEfunc::getItemLabel('sys_dmail_group', 'title')) . '</td>
					<td class="t3-row-header">' . $GLOBALS['LANG']->sL(t3lib_BEfunc::getItemLabel('sys_dmail_group', 'type')) . '</td>
					<td class="t3-row-header">' . $GLOBALS['LANG']->sL(t3lib_BEfunc::getItemLabel('sys_dmail_group', 'description')) . '</td>
					<td class="t3-row-header">' . $GLOBALS['LANG']->getLL('recip_group_amount') . '</td>
				</tr>';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid,pid,title,description,type',
			'sys_dmail_group',
			'pid = ' . intval($this->id).
				t3lib_BEfunc::deleteClause('sys_dmail_group'),
			'',
			$GLOBALS['TYPO3_DB']->stripOrderBy($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
		);

		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$result = $this->cmd_compileMailGroup(intval($row['uid']));
			$count = 0;
			$idLists = $result['queryInfo']['id_lists'];
			if (is_array($idLists['tt_address']))	$count+=count($idLists['tt_address']);
			if (is_array($idLists['fe_users']))	$count+=count($idLists['fe_users']);
			if (is_array($idLists['PLAINLIST']))	$count+=count($idLists['PLAINLIST']);
			if (is_array($idLists[$this->userTable]))	$count+=count($idLists[$this->userTable]);

			$out.='<tr>
					<td nowrap="nowrap">'.t3lib_iconWorks::getIconImage('sys_dmail_group', $row, $GLOBALS['BACK_PATH'], 'width="18" height="16" style="vertical-align: top;"').'</td>
					<td>'.$this->editLink('sys_dmail_group',$row['uid']).'</td>
					<td nowrap="nowrap">'.$this->linkRecip_record('<strong>'.htmlspecialchars(t3lib_div::fixed_lgd_cs($row['title'],30)).'</strong>&nbsp;&nbsp;',$row['uid']).'</td>
					<td nowrap="nowrap">'.htmlspecialchars(t3lib_BEfunc::getProcessedValue('sys_dmail_group','type',$row['type'])).'&nbsp;&nbsp;</td>
					<td>'.t3lib_BEfunc::getProcessedValue('sys_dmail_group','description',htmlspecialchars($row['description'])).'&nbsp;&nbsp;</td>
					<td>'.$count.'</td>
				</tr>';
		}

		$out='<table class="typo3-dblist" border="0" cellpadding="0" cellspacing="0">' . $out . '</table>';
		$theOutput = $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'select_mailgroup',$GLOBALS['BACK_PATH']).$GLOBALS['LANG']->getLL('recip_select_mailgroup'),$out,1,1, 0, TRUE);

			// New:
		$out='<a href="#" class="t3-link" onClick="'.t3lib_BEfunc::editOnClick('&edit[sys_dmail_group]['.$this->id.']=new',$GLOBALS['BACK_PATH'],'').'">'.t3lib_iconWorks::getIconImage('sys_dmail_group',array(),$GLOBALS['BACK_PATH'],'align="top"'). $GLOBALS['LANG']->getLL('recip_create_mailgroup_msg') . '</a>';
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section(t3lib_BEfunc::cshItem($this->cshTable,'create_mailgroup',$GLOBALS['BACK_PATH']).$GLOBALS['LANG']->getLL('recip_create_mailgroup'),$out, 1, 0, FALSE, TRUE, FALSE, TRUE);

			// Import
		$out='<a class="t3-link" href="index.php?id='.$this->id.'&CMD=displayImport">' . $GLOBALS['LANG']->getLL('recip_import_mailgroup_msg') . '</a>';
		$theOutput.= $this->doc->spacer(20);
		$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_import'),$out, 1, 1, 0, TRUE);
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
		$str = "";

		// check if the user has the right to modify the table
		if ($GLOBALS["BE_USER"]->check('tables_modify',$table)) {
			$params = '&edit['.$table.']['.$uid.']=edit';
			$str = '<a href="#" onClick="'.t3lib_BEfunc::editOnClick($params,$GLOBALS['BACK_PATH'],'').'"><img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$GLOBALS['LANG']->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$GLOBALS['LANG']->getLL("dmail_edit").'" /></a>';
		}

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
			$mailGroup = t3lib_BEfunc::getRecord('sys_dmail_group',$group_uid);
			if (is_array($mailGroup) && $mailGroup['pid']==$this->id)	{
				switch($mailGroup['type'])	{
				case 0:	// From pages
					$thePages = $mailGroup['pages'] ? $mailGroup['pages'] : $this->id;		// use current page if no else
					$pages = t3lib_div::intExplode(',',$thePages);	// Explode the pages
					$pageIdArray=array();
					foreach ($pages as $pageUid) {
						if ($pageUid>0) {
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
						$recipients = tx_directmail_static::rearrangePlainMails(array_unique(preg_split('|[[:space:],;]+|',$mailGroup['list'])));
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
					foreach ($groups as $group) {
						$collect = $this->cmd_compileMailGroup($group);
						if (is_array($collect['queryInfo']['id_lists'])) {
							$id_lists = array_merge_recursive($id_lists,$collect['queryInfo']['id_lists']);
						}
					}

						// Make unique entries
					if (is_array($id_lists['tt_address'])) {
						$id_lists['tt_address'] = array_unique($id_lists['tt_address']);
					}
					if (is_array($id_lists['fe_users'])) {
						$id_lists['fe_users'] = array_unique($id_lists['fe_users']);
					}
					if (is_array($id_lists[$this->userTable]) && $this->userTable) {
						$id_lists[$this->userTable] = array_unique($id_lists[$this->userTable]);
					}
					if (is_array($id_lists['PLAINLIST'])) {
						$id_lists['PLAINLIST'] = tx_directmail_static::cleanPlainList($id_lists['PLAINLIST']);
					}
					break;
				}
			}
		}

		/**
		* Hook for cmd_compileMailGroup
		* manipulate the generated id_lists
		*/
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'])) {
			$hookObjectsArr = array();

			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
			foreach($hookObjectsArr as $hookObj)    {
				if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
					$temp_lists = $hookObj->cmd_compileMailGroup_postProcess($id_lists, $this, $mailGroup);
				}
			}

			unset ($id_lists);
			$id_lists = $temp_lists;
		}


		return array(
			'queryInfo' => array('id_lists' => $id_lists)
		);
	}

	/**
	 * display infos of the mail group
	 *
	 * @param	array		$result: array containing list of recipient uid
	 * @return	string		list of all recipient (HTML)
	 */
	function cmd_displayMailGroup($result)	{
		$totalRecipients = 0;
		$idLists = $result['queryInfo']['id_lists'];
		if (is_array($idLists['tt_address'])) {
			$totalRecipients += count($idLists['tt_address']);
		}
		if (is_array($idLists['fe_users'])) {
			$totalRecipients += count($idLists['fe_users']);
		}
		if (is_array($idLists['PLAINLIST'])) {
			$totalRecipients += count($idLists['PLAINLIST']);
		}
		if (is_array($idLists[$this->userTable])) {
			$totalRecipients += count($idLists[$this->userTable]);
		}

		$group = t3lib_BEfunc::getRecord('sys_dmail_group',intval(t3lib_div::_GP('group_uid')));
		$out = t3lib_iconWorks::getIconImage('sys_dmail_group',$group,$GLOBALS['BACK_PATH'],'style="vertical-align: top;"') . htmlspecialchars($group['title']);

		$lCmd = t3lib_div::_GP('lCmd');

		$mainC = $GLOBALS['LANG']->getLL('mailgroup_recip_number') . ' <strong>'.$totalRecipients.'</strong>';
		if (!$lCmd)	{
			$mainC.= '<br /><br /><strong><a class="t3-link" href="'.t3lib_div::linkThisScript(array('lCmd'=>'listall')).'">' . $GLOBALS['LANG']->getLL('mailgroup_list_all') . '</a></strong>';
		}

		$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_recip_from').' '.$out,$mainC, 1, 1, 0, TRUE);
		$theOutput .= $this->doc->spacer(20);

			// do the CSV export
		$csvValue = t3lib_div::_GP('csv');
		if ($csvValue) {
			if ($csvValue == 'PLAINLIST') {
				$this->downloadCSV($idLists['PLAINLIST']);
			} elseif (t3lib_div::inList('tt_address,fe_users,' . $this->userTable, $csvValue)) {
				$this->downloadCSV(tx_directmail_static::fetchRecordsListValues($idLists[$csvValue], $csvValue, (($csvValue == 'fe_users') ? str_replace('phone','telephone', $this->fieldList) : $this->fieldList) . ',tstamp'));
			}
		}

		switch($lCmd) {
			case 'listall':
				if (is_array($idLists['tt_address'])) {
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_table_address'),tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'), 'tt_address', $this->id, $this->doc->bgColor4));
					$theOutput.= $this->doc->spacer(20);
				}
				if (is_array($idLists['fe_users'])) {
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_table_fe_users'),tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'), 'fe_users', $this->id, $this->doc->bgColor4));
					$theOutput.= $this->doc->spacer(20);
				}
				if (is_array($idLists['PLAINLIST'])) {
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_plain_list'),tx_directmail_static::getRecordList($idLists['PLAINLIST'],'default',$this->id,$this->doc->bgColor4,1));
					$theOutput.= $this->doc->spacer(20);
				}
				if (is_array($idLists[$this->userTable])) {
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_table_custom') . ' ' . $this->userTable,tx_directmail_static::getRecordList(tx_directmail_static::fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable,$this->id,$this->doc->bgColor4));
				}
			break;
			default:

				if (is_array($idLists['tt_address']) && count($idLists['tt_address'])) {
					$recipContent = $GLOBALS['LANG']->getLL('mailgroup_recip_number') . ' ' . count($idLists['tt_address']) . '<br /><a href="' . t3lib_div::linkThisScript(array('csv'=>'tt_address')) . '" class="t3-link">' . $GLOBALS['LANG']->getLL('mailgroup_download') . '</a>';
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_table_address'), $recipContent);
					$theOutput.= $this->doc->spacer(20);
				}

				if (is_array($idLists['fe_users']) && count($idLists['fe_users'])) {
					$recipContent = $GLOBALS['LANG']->getLL('mailgroup_recip_number') . ' ' . count($idLists['fe_users']) . '<br /><a href="' . t3lib_div::linkThisScript(array('csv'=>'fe_users')) . '" class="t3-link">' . $GLOBALS['LANG']->getLL('mailgroup_download') . '</a>';
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_table_fe_users'), $recipContent);
					$theOutput.= $this->doc->spacer(20);
				}

				if (is_array($idLists['PLAINLIST']) && count($idLists['PLAINLIST'])) {
					$recipContent = $GLOBALS['LANG']->getLL('mailgroup_recip_number') . ' ' . count($idLists['PLAINLIST']) . '<br /><a href="' . t3lib_div::linkThisScript(array('csv'=>'PLAINLIST')) . '" class="t3-link">' . $GLOBALS['LANG']->getLL('mailgroup_download') . '</a>';
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_plain_list'), $recipContent);
					$theOutput.= $this->doc->spacer(20);
				}

				if (is_array($idLists[$this->userTable]) && count($idLists[$this->userTable])) {
					$recipContent = $GLOBALS['LANG']->getLL('mailgroup_recip_number') . ' ' . count($idLists[$this->userTable]) . '<br /><a href="' . t3lib_div::linkThisScript(array('csv' => $this->userTable)) . '" class="t3-link">' . $GLOBALS['LANG']->getLL('mailgroup_download') . '</a>';
					$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('mailgroup_table_custom'), $recipContent);
					$theOutput.= $this->doc->spacer(20);
				}

				if ($group['type'] == 3) {
					if ($GLOBALS["BE_USER"]->check('tables_modify','sys_dmail_group')) {
						$theOutput .= $this->cmd_specialQuery($group);
					}
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

			$res_update = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
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
		$out = "";
		$this->queryGenerator->init('dmail_queryConfig',$this->MOD_SETTINGS['queryTable']);

		if ($this->MOD_SETTINGS['queryTable'] && $this->MOD_SETTINGS['queryConfig']) {
			$this->queryGenerator->queryConfig = unserialize($this->MOD_SETTINGS['queryConfig']);
			$this->queryGenerator->extFieldLists['queryFields'] = 'uid';
			$out .= $this->queryGenerator->getSelectQuery();
			$out .= $this->doc->spacer(20);
		}

		$this->queryGenerator->setFormName($this->formname);
		$this->queryGenerator->noWrap='';
		$this->queryGenerator->allowedTables = $this->allowedTables;
		$tmpCode = $this->queryGenerator->makeSelectorTable($this->MOD_SETTINGS,'table,query');
		$tmpCode .= '<input type="hidden" name="CMD" value="displayMailGroup" /><input type="hidden" name="group_uid" value="'.$mailGroup['uid'].'" />';
		$tmpCode .= '<input type="submit" value="'.$GLOBALS['LANG']->getLL('dmail_updateQuery').'" />';
		$out .= $this->doc->section($GLOBALS['LANG']->getLL('dmail_makeQuery'),$tmpCode);

		$theOutput = $this->doc->spacer(20);
		$theOutput .= $this->doc->section($GLOBALS['LANG']->getLL('dmail_query'),$out);

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
			$lines[] = t3lib_div::csvValues(array_keys(current($idArr)),',','');

			reset($idArr);
			while(list($i,$rec)=each($idArr))	{
				$lines[] = t3lib_div::csvValues($rec);
			}
		}

		$filename = 'DirectMail_export_'.date('dmy-Hi').'.csv';
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
	function cmd_displayUserInfo() {
		$uid = intval(t3lib_div::_GP('uid'));
		$indata = t3lib_div::_GP('indata');
		$table = t3lib_div::_GP('table');
		t3lib_div::loadTCA($table);
		$mm_table = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];

		if(t3lib_div::_GP('submit')) {
			$indata = t3lib_div::_GP('indata');
			if(!$indata){
				$indata['html']= 0;
			}
		}

		switch($table) {
		case 'tt_address':
		case 'fe_users':
			if (is_array($indata)) {
				$data=array();
				if (is_array($indata['categories'])) {
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
				/** @var $tce t3lib_TCEmain */
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values = 0;
				$tce->start($data, Array());
				$tce->process_datamap();
			}
			break;
		}

		switch($table)	{
		case 'tt_address':
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'tt_address.*',
				'tt_address LEFT JOIN pages ON pages.uid=tt_address.pid',
				'tt_address.uid='.intval($uid).
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::BEenableFields('tt_address').
					t3lib_BEfunc::deleteClause('tt_address')
				);
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			break;
		case 'fe_users':
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'fe_users.*',
				'fe_users LEFT JOIN pages ON pages.uid=fe_users.pid',
				'fe_users.uid='.intval($uid).
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::BEenableFields('fe_users').
					t3lib_BEfunc::deleteClause('fe_users')
				);
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			break;
		}
		if (is_array($row))	{
			$row_categories = '';
			$resCat = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'uid_foreign',
				$mm_table,
				'uid_local='.$row['uid']
				);
			while($rowCat=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($resCat)) {
				$row_categories .= $rowCat['uid_foreign'].',';
			}
			$row_categories = rtrim($row_categories, ",");

			$Eparams = '&edit['.$table.']['.$row['uid'].']=edit';
			$out = '';
			$out .= t3lib_iconWorks::getIconImage($table, $row, $GLOBALS['BACK_PATH'], 'width="18" height="16" title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row['pid'],$this->perms_clause,40)).'" style="vertical-align:top;"').htmlspecialchars($row['name']).htmlspecialchars(' <'.$row['email'].'>');
			$out .= '&nbsp;&nbsp;<a href="#" onClick="'.t3lib_BEfunc::editOnClick($Eparams,$GLOBALS['BACK_PATH'],'').'"><img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$GLOBALS['LANG']->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$GLOBALS['LANG']->getLL("dmail_edit").'" /><b>' . $GLOBALS['LANG']->getLL('dmail_edit') . '</b></a>';
			$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('subscriber_info'),$out);

			$out = '';
			$out_check = '';

			$this->categories = tx_directmail_static::makeCategories($table, $row, $this->sys_language_uid);
			reset($this->categories);
			while(list($pKey,$pVal)=each($this->categories)) {
				$out_check.='<input type="hidden" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="0" /><input type="checkbox" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="1"'.(t3lib_div::inList($row_categories,$pKey)?' checked="checked"':'').' /> '.htmlspecialchars($pVal).'<br />';
			}
			$out_check.='<br /><br /><input type="checkbox" name="indata[html]" value="1"'.($row['module_sys_dmail_html']?' checked="checked"':'').' /> ';
			$out_check.=$GLOBALS['LANG']->getLL('subscriber_profile_htmlemail') . '<br />';
			$out.=$out_check;

			$out .= '<input type="hidden" name="table" value="'.$table.'" /><input type="hidden" name="uid" value="'.$uid.'" /><input type="hidden" name="CMD" value="'.$this->CMD.'" /><br /><input type="submit" name="submit" value="' . htmlspecialchars($GLOBALS['LANG']->getLL('subscriber_profile_update')) . '" />';
			$theOutput .= $this->doc->spacer(20);
			$theOutput .= $this->doc->section($GLOBALS['LANG']->getLL('subscriber_profile'), $GLOBALS['LANG']->getLL('subscriber_profile_instructions') . '<br /><br />'.$out);
		}
		return $theOutput;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod3/class.tx_directmail_recipient_list.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod3/class.tx_directmail_recipient_list.php']);
}

?>