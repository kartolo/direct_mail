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

include_once(PATH_t3lib.'class.t3lib_pagetree.php');

/**
 * Static class.
 * Functions in this class are used by more than one modules.
 *
 */
class tx_directmail_static {

	/**
	 * get recipient DB record given on the ID
	 *
	 * @param	array		$listArr: list of recipient IDs
	 * @param	string		$table: table name
	 * @param	string		$fields: field to be selected
	 * @return	array		recipients' data
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
	 * get the ID of page in a tree
	 *
	 * @param	integer		$id: page ID
	 * @param	string		$perms: select query clause
	 * @return	array		the page ID, recursively
	 */
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
	 * remove double record in an array
	 *
	 * @param	array		$plainlist: email of the recipient
	 * @return	array		cleaned array
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
	 * Return all uid's from $table where the $pid is in $pidList. If $cat is 0 or empty, then all entries (with pid $pid) is returned
	 * else only entires which are subscribing to the categories of the group with uid $group_uid is returned.
	 * The relation between the recipients in $table and sys_dmail_categories is a true MM relation (Must be correctly defined in TCA).
	 *
	 * @param	string		$table: The table to select from
	 * @param	string		$pidList: The pidList
	 * @param	integer		$group_uid: The groupUid.
	 * @param	integer		$cat: The number of relations from sys_dmail_group to sysmail_categories
	 * @return	array		The resulting array of uid's
	 */
	function getIdList($table,$pidList,$group_uid,$cat) {
		global $TCA, $TYPO3_DB;

		if ($table == 'fe_groups') {
			$switchTable = 'fe_users';
		} else {
			$switchTable = $table;
		}
			 // Direct Mail needs an email address!
		$emailIsNotNull = ' AND ' . $switchTable . '.email !=' . $TYPO3_DB->fullQuoteStr('', $switchTable);

			// fe user group uid should be in list of fe users list of user groups
		$field = $switchTable.'.usergroup';
		$command = $table.'.uid';
		// This approach, using standard SQL, does not work, even when fe_users.usergroup is defined as varchar(255) instead of tinyblob
		//$usergroupInList = ' AND ('.$field.' LIKE \'%,\'||'.$command.'||\',%\' OR '.$field.' LIKE '.$command.'||\',%\' OR '.$field.' LIKE \'%,\'||'.$command.' OR '.$field.'='.$command.')';
		// The following will work but INSTR and CONCAT are available only in mySQL
		$usergroupInList = ' AND INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )';

		t3lib_div::loadTCA($switchTable);
		$mm_table = $TCA[$switchTable]['columns']['module_sys_dmail_category']['config']['MM'];
		$cat = intval($cat);
		if($cat < 1) {
			if ($table == 'fe_groups') {
				$res = $TYPO3_DB->exec_SELECTquery(
					'DISTINCT '.$switchTable.'.uid',
					$switchTable.','.$table,
					'fe_groups.pid IN('.$pidList.')'.
						$usergroupInList.
						$emailIsNotNull.
						t3lib_BEfunc::BEenableFields($switchTable).
						t3lib_BEfunc::deleteClause($switchTable).
						t3lib_BEfunc::BEenableFields($table).
						t3lib_BEfunc::deleteClause($table)
					);
			} else {
				$res = $TYPO3_DB->exec_SELECTquery(
					'DISTINCT '.$switchTable.'.uid',
					$switchTable,
					$switchTable.'.pid IN('.$pidList.')'.
						$emailIsNotNull.
						t3lib_BEfunc::BEenableFields($switchTable).
						t3lib_BEfunc::deleteClause($switchTable)
					);
			}
		} else {
			if ($table == 'fe_groups') {
				$res = $TYPO3_DB->exec_SELECTquery(
					'DISTINCT '.$switchTable.'.uid',
					'sys_dmail_group, sys_dmail_group_category_mm as g_mm, '.$mm_table.' as mm_1, fe_groups LEFT JOIN '.$switchTable.' ON '.$switchTable.'.uid = mm_1.uid_local',
					'fe_groups.pid IN ('.$pidList.')'.
						$usergroupInList.
						' AND mm_1.uid_foreign=g_mm.uid_foreign'.
						' AND sys_dmail_group.uid=g_mm.uid_local'.
						' AND sys_dmail_group.uid='.intval($group_uid).
						$emailIsNotNull.
						t3lib_BEfunc::BEenableFields($switchTable).
						t3lib_BEfunc::deleteClause($switchTable).
						t3lib_BEfunc::BEenableFields($table).
						t3lib_BEfunc::deleteClause($table).
						t3lib_BEfunc::deleteClause('sys_dmail_group')
					);
			} else {
				$res = $TYPO3_DB->exec_SELECTquery(
					'DISTINCT '.$switchTable.'.uid',
					'sys_dmail_group, sys_dmail_group_category_mm as g_mm, '.$mm_table.' as mm_1 LEFT JOIN '.$table.' ON '.$table.'.uid = mm_1.uid_local',
					$switchTable.'.pid IN('.$pidList.')'.
						' AND mm_1.uid_foreign=g_mm.uid_foreign'.
						' AND sys_dmail_group.uid=g_mm.uid_local'.
						' AND sys_dmail_group.uid='.intval($group_uid).
						$emailIsNotNull.
						t3lib_BEfunc::BEenableFields($switchTable).
						t3lib_BEfunc::deleteClause($switchTable).
						t3lib_BEfunc::deleteClause('sys_dmail_group')
					);
			}
		}
		$outArr = array();
		while ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$outArr[] = $row['uid'];
		}
		return $outArr;
	}

	/**
	 * Return all uid's from $table for a static direct mail group.
	 *
	 * @param	string		$table: The table to select from
	 * @param	integer		$uid: The uid of the direct_mail group
	 * @return	array		The resulting array of uid's
	 */
	function getStaticIdList($table,$uid) {
		global $TYPO3_DB;

		if ($table == 'fe_groups') {
			$switchTable = 'fe_users';
		} else {
			$switchTable = $table;
		}
		$emailIsNotNull = ' AND ' . $switchTable . '.email !=' . $TYPO3_DB->fullQuoteStr('', $switchTable);
			// fe user group uid should be in list of fe users list of user groups
		$field = $switchTable.'.usergroup';
		$command = $table.'.uid';
		// See comment above
		// $usergroupInList = ' AND ('.$field.' LIKE \'%,\'||'.$command.'||\',%\' OR '.$field.' LIKE '.$command.'||\',%\' OR '.$field.' LIKE \'%,\'||'.$command.' OR '.$field.'='.$command.')';
		$usergroupInList = ' AND INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )';

		if ($table == 'fe_groups') {
			$res = $TYPO3_DB->exec_SELECTquery(
				'DISTINCT '.$switchTable.'.uid',
				$switchTable.','.$table.',sys_dmail_group LEFT JOIN sys_dmail_group_mm ON sys_dmail_group_mm.uid_local=sys_dmail_group.uid',
				'sys_dmail_group.uid='.intval($uid).
					' AND fe_groups.uid=sys_dmail_group_mm.uid_foreign'.
					' AND sys_dmail_group_mm.tablenames='.$TYPO3_DB->fullQuoteStr($table, $table).
					$usergroupInList.
					$emailIsNotNull.
					t3lib_BEfunc::BEenableFields($switchTable).
					t3lib_BEfunc::deleteClause($switchTable).
					t3lib_BEfunc::BEenableFields($table).
					t3lib_BEfunc::deleteClause($table).
					t3lib_BEfunc::deleteClause('sys_dmail_group')
				);
		} else {
			$res = $TYPO3_DB->exec_SELECTquery(
				'DISTINCT '.$switchTable.'.uid',
				'sys_dmail_group,'.$switchTable.' LEFT JOIN sys_dmail_group_mm ON sys_dmail_group_mm.uid_foreign='.$switchTable.'.uid',
				'sys_dmail_group.uid = '.intval($uid).
					' AND sys_dmail_group_mm.uid_local=sys_dmail_group.uid'.
					' AND sys_dmail_group_mm.tablenames='.$TYPO3_DB->fullQuoteStr($switchTable, $switchTable).
					$emailIsNotNull.
					t3lib_BEfunc::BEenableFields($switchTable).
					t3lib_BEfunc::deleteClause($switchTable).
					t3lib_BEfunc::deleteClause('sys_dmail_group')
				);
		}

		$outArr=array();
		while ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			$outArr[]=$row['uid'];
		}
		return $outArr;
	}

	/**
	 * Construct the array of uid's from $table selected by special query of mail group of such type
	 *
	 * @param	string		$table: The table to select from
	 * @param	array		$group: The direct_mail group record
	 * @param	object		$queryGenerator: the query generator object
	 * @return	string		The resulting query.
	 */
	function getSpecialQueryIdList(&$queryGenerator,$table,$group) {
		global $TYPO3_DB;

		$outArr = array();
		if ($group['query']) {
			$queryGenerator->init('dmail_queryConfig', $table, 'uid');
			$queryGenerator->queryConfig = unserialize($group['query']);
			$whereClause = $queryGenerator->getQuery($queryGenerator->queryConfig).t3lib_BEfunc::deleteClause($table);
			$res = $TYPO3_DB->exec_SELECTquery(
				$table.'.uid',
				$table,
				$whereClause
				);

			while ($row = $TYPO3_DB->sql_fetch_assoc($res))	{
				$outArr[] = $row['uid'];
			}
		}
		return $outArr;
	}

	/**
	 * get all group IDs
	 *
	 * @param	string		$list: comma-separated ID
	 * @param	array		$parsedGroups: Groups ID, which is already parsed
	 * @param	string		$perms_clause: permission clause (Where)
	 * @return	array		the new Group IDs
	 */
	function getMailGroups($list,$parsedGroups, $perms_clause)	{
		global $TYPO3_DB;

		$groupIdList = t3lib_div::intExplode(',',$list);
		$groups = array();

		$res = $TYPO3_DB->exec_SELECTquery(
			'sys_dmail_group.*',
			'sys_dmail_group LEFT JOIN pages ON pages.uid=sys_dmail_group.pid',
			'sys_dmail_group.uid IN ('.implode(',',$groupIdList).')'.
				' AND '.$perms_clause.
				t3lib_BEfunc::deleteClause('pages').
				t3lib_BEfunc::deleteClause('sys_dmail_group')
			);

		while($row = $TYPO3_DB->sql_fetch_assoc($res))	{
			if ($row['type']==4)	{	// Other mail group...
				if (!in_array($row['uid'],$parsedGroups))	{
					$parsedGroups[]=$row['uid'];
					$groups=array_merge($groups,tx_directmail_static::getMailGroups($row['mail_groups'],$parsedGroups, $perms_clause));
				}
			} else {
				$groups[]=$row['uid'];	// Normal mail group, just add to list
			}
		}
		return $groups;
	}

	/**
	 * parse CSV lines into array form
	 *
	 * @param	array		$lines: CSV lines
	 * @param	string		$fieldList: list of the fields
	 * @return	array		parsed CSV values
	 */
	function rearrangeCsvValues($lines, $fieldList) {
		global $TYPO3_CONF_VARS;

		$out=array();
		if (is_array($lines) && count($lines)>0)	{
			// Analyse if first line is fieldnames.
			// Required is it that every value is either 1) found in the list fieldsList in this class, the value is empty (value omitted then) or 3) the field starts with "user_".
			// In addition fields may be prepended with "[code]". This is used if the incoming value is true in which case '+[value]' adds that number to the field value (accummulation) and '=[value]' overrides any existing value in the field
			$first = $lines[0];
			$fieldListArr = explode(',',$fieldList);
			if ($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields']) {
				$fieldListArr = array_merge($fieldListArr, explode(',',$TYPO3_CONF_VARS['EXTCONF']['direct_mail']['addRecipFields']));
			}
			reset($first);
			$fieldName=1;
			$fieldOrder=array();
			while(list(,$v)=each($first))	{
				list($fName,$fConf) = split('\[|\]',$v);
				$fName =trim($fName);
				$fConf =trim($fConf);
				$fieldOrder[]=array($fName,$fConf);
				if ($fName && substr($fName,0,5) != 'user_' && !in_array($fName,$fieldListArr))	{
					$fieldName = 0;
					break;
				}
			}
				// If not field list, then:
			if (!$fieldName)	{
				$fieldOrder = array(array('name'),array('email'));
			}
				// Re-map values
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
	 * rearrange emails array into a 2-dimensional array
	 *
	 * @param	array		$plainMails: recipient emails
	 * @return	array		a 2-dimensional array consisting email and name
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
	 * Compile the categories enables for this $row of this $table.
	 * From version 2.0 the categories are fetched from the db table sys_dmail_category and not page TSconfig.
	 *
	 * @param	string		$table: table name
	 * @param	array		$row: row from table
	 * @param	integer		$sys_language_uid: User language ID
	 * @return	void		No return value, updates $this->categories
	 */
	function makeCategories($table,$row, $sys_language_uid) {
		global $TYPO3_DB;

		$categories = array();
		
		$mm_field = 'module_sys_dmail_category';
		if ($table == 'sys_dmail_group') {
			$mm_field = 'select_categories';
		}
		
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
						t3lib_BEfunc::BEenableFields('sys_dmail_category').
						t3lib_BEfunc::deleteClause('sys_dmail_category')
					);
				while($rowCat = $TYPO3_DB->sql_fetch_assoc($res)) {
					if($localizedRowCat = tx_directmail_static::getRecordOverlay('sys_dmail_category',$rowCat,$sys_language_uid,'')) {
						$categories[$localizedRowCat['uid']] = $localizedRowCat['category'];
					}
				}
			}
		}
		return $categories;
	}

	/**
	 * Import from t3lib_page in order to eate backend version
	 * Creates language-overlay for records in general (where translation is found in records from the same table)
	 *
	 * @param	string		$table: Table name
	 * @param	array		$row: Record to overlay. Must containt uid, pid and $table]['ctrl']['languageField']
	 * @param	integer		$sys_language_content: Pointer to the sys_language uid for content on the site.
	 * @param	string		$OLmode: Overlay mode. If "hideNonTranslated" then records without translation will not be returned un-translated but unset (and return value is false)
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
									t3lib_BEfunc::BEenableFields($table).
									t3lib_BEfunc::deleteClause($table),
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

	/**
	 * print out an array as a table
	 *
	 * @param	array		$tableLines: content of the cell
	 * @param	array		$cellParams: the additional cell parameter
	 * @param	boolean		$header: if set, the first arrray is the header of the table
	 * @param	array		$cellcmd: if set, the content is HTML escaped
	 * @param	string		$tableParams: the additional table parameter
	 * @return	string		HTML table
	 */
	function formatTable($tableLines,$cellParams,$header,$cellcmd=array(),$tableParams='border="0" cellpadding="2" cellspacing="3"')	{
		reset($tableLines);
		$cols = count(current($tableLines));

		reset($tableLines);
		$lines=array();
		$first=$header?1:0;
		while(list(,$r)=each($tableLines))	{
			$rowA=array();
			for($k=0;$k<$cols;$k++)	{
				$v=$r[$k];
				$v = strlen($v) ? ($cellcmd[$k]?$v:htmlspecialchars($v)) : "&nbsp;";
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
	 * Set up URL variables for this $row.
	 *
	 * @param	array		$row: directmail DB record
	 * @return	void		set the global variable url_plain and url_html
	 */
	function setURLs($row)	{
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
	 * get the base URL
	 *
	 * @param	integer		$domainUid: ID of a domain
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
				$url_parts = @parse_url(t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR'));
				$scheme = $url_parts['scheme'];
				$port = $url_parts['port'];
			}
		}

		return ($domainName ? (($scheme?$scheme:'http') . '://' . $domainName . ($port?':'.$port:'') . '/') : substr(t3lib_div::getIndpEnv('TYPO3_REQUEST_DIR'),0,-(strlen(t3lib_div::resolveBackPath(TYPO3_mainDir.TYPO3_MOD_PATH))))).'index.php';
	}

	/**
	 * get locallang label
	 *
	 * @param	string		$name: locallang label index
	 * @return	string		the label
	 */
	function fName($name)	{
		global $LANG;
		return stripslashes($LANG->sL(t3lib_BEfunc::getItemLabel('sys_dmail',$name)));
	}
	
	/**
	 * parsing csv-formated text to an array
	 *
	 * @param	string		$str: string in csv-format
	 * @param	string		$sep: separator
	 * @return	array		parsed csv in an array
	 */
	function getCsvValues($str,$sep=',')	{
		$fh=tmpfile();
		fwrite ($fh, trim($str));
		fseek ($fh,0);
		$lines=array();
		if ($sep == 'tab') $sep = chr(9);
		while ($data = fgetcsv ($fh, 1000, $sep)) {
			$lines[]=$data;
		}
		return $lines;
	}
	
	
	/**
	 * show DB record in HTML table format
	 *
	 * @param	array		$listArr: all DB records to be formated
	 * @param	string		$table: table name
	 * @param	integer		$pageId: pageID, to which the link points to
	 * @param	string		@bgColor: background Color of the row
	 * @param	integer		$dim: if set, icon will be shaded
	 * @param	boolean		$editLinkFlag: if set, edit link is showed
	 * @return	string		list of record in HTML format
	 */
	function getRecordList($listArr,$table,$pageId,$bgColor,$dim=0,$editLinkFlag=1)	{
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
						$editLink = '<td><a href="index.php?id='.$pageId.'&CMD=displayUserInfo&table='.$table.'&uid='.$row['uid'].'"><img'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/edit2.gif', 'width="12" height="12"').' alt="' . $LANG->getLL('dmail_edit') . '" width="12" height="12" style="margin:0px 5px; vertical-align:top;" title="' . $LANG->getLL('dmail_edit') . '" /></a></td>';
					}
				}

				$lines[]='<tr bgcolor="'.$bgColor.'">
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
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_static.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_static.php']);
}

?>