<?php
namespace DirectMailTeam\DirectMail;

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
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage	tx_directmail
 *
 * @version		$Id: class.tx_directmail_recipient_list.php 8398 2008-02-26 14:22:00Z ivankartolo $
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\File\BasicFileUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Recipient list module for tx_directmail extension
 *
 */
class Importer {
	/**
	 * the GET-Data
	 * @var array
	 */
	var $indata = array();
	var $params = array();

	/**
	 * @var tx_directmail_recipient_list
	 */
	var $parent;

	/**
	 * @var \TYPO3\CMS\Core\Utility\File\ExtendedFileUtility
	 */
	var $fileProcessor;

	function init (&$pObj) {
		$this->parent = &$pObj;

			//get some importer default from pageTS
		$temp = BackendUtility::getModTSconfig(intval(GeneralUtility::_GP('id')),'mod.web_modules.dmail.importer');
		$this->params = $temp['properties'];
	}

	/**
	 * import CSV-Data in step-by-step mode
	 *
	 * @return	string		HTML form
	 */
	function cmd_displayImport()	{
		$step = GeneralUtility::_GP('importStep');

		$defaultConf = array(
			'remove_existing' => 0,
			'first_fieldname' => 0,
			'valid_email' => 0,
			'remove_dublette' => 0,
			'update_unique' => 0
		);

		if (GeneralUtility::_GP('CSV_IMPORT')) {
			$importerConfig = GeneralUtility::_GP('CSV_IMPORT');
			if ($step['next'] == 'mapping') {
				$this->indata = GeneralUtility::array_merge($defaultConf, $importerConfig);
			} else {
				$this->indata = $importerConfig;
			}
		}

		if (empty($this->indata)) {
			$this->indata = array();
		}

		if (empty($this->params)) {
			$this->params = array();
		}
		// merge it with inData, but inData has priority.
		$this->indata = GeneralUtility::array_merge($this->params, $this->indata);

		$currentFileInfo = BasicFileUtility::getTotalFileInfo($this->indata['newFile']);
		$currentFileName = $currentFileInfo['file'];
		$currentFileSize = GeneralUtility::formatSize($currentFileInfo['size']);
		$currentFileMessage = $currentFileName.' ('.$currentFileSize.')';

		if(empty($this->indata['csv']) && !empty($_FILES['upload_1']['name'])){
			$this->indata['newFile'] = $this->checkUpload();
			// TYPO3 6.0 returns an object...
			if(is_object($this->indata['newFile'][0])){
				$storageConfig = $this->indata['newFile'][0]->getStorage()->getConfiguration();
				$this->indata['newFile'] = $storageConfig['basePath'].ltrim($this->indata['newFile'][0]->getIdentifier(),'/');
			}
		} elseif(!empty($this->indata['csv']) && empty($_FILES['upload_1']['name'])) {
			if(((strpos($currentFileInfo['file'],'import')=== false)?0:1) && ($currentFileInfo['realFileext'] === 'txt')){
				//do nothing
			} else {
				unset($this->indata['newFile']);
			}
		}

		if($this->indata['back']){
			$stepCurrent = $step['back'];
		} elseif ($this->indata['next']){
			$stepCurrent = $step['next'];
		} elseif ($this->indata['update']) {
			$stepCurrent = 'mapping';
		}

		if(strlen($this->indata['csv']) > 0){
			$this->indata['mode'] = 'csv';
			$this->indata['newFile'] = $this->writeTempFile();
		} elseif(!empty($this->indata['newFile'])) {
			$this->indata['mode'] = 'file';
		} else {
			unset($stepCurrent);
		}

		//check if "email" is mapped
		if($stepCurrent === 'import'){
			$map = $this->indata['map'];
			$error = array();
			//check noMap
			$newMap = GeneralUtility::removeArrayEntryByValue(array_unique($map),'noMap');
			if (empty($newMap)){
				$error[]='noMap';
			} elseif(!GeneralUtility::inArray($map,'email')){
				$error[] = 'email';
			}

			if ($error){
				$stepCurrent = 'mapping';
			}
		}

		$out = "";
		switch($stepCurrent){
			case 'conf':
					//get list of sysfolder
//TODO: maybe only subtree von this->id??
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid,title',
					'pages',
					'doktype = 254 AND '.$GLOBALS['BE_USER']->getPagePermsClause(3).BackendUtility::deleteClause('pages').BackendUtility::BEenableFields('pages'),
					'',
					'uid'
				);
				$optStorage = array();
				while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
					if(BackendUtility::readPageAccess($row['uid'],$GLOBALS['BE_USER']->getPagePermsClause(1))){
						$optStorage[] = array($row['uid'],$row['title'].' [uid:'.$row['uid'].']');
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($res);

				$optDelimiter=array(
					array('comma',$GLOBALS['LANG']->getLL('mailgroup_import_separator_comma')),
					array('semicolon',$GLOBALS['LANG']->getLL('mailgroup_import_separator_semicolon')),
					array('colon',$GLOBALS['LANG']->getLL('mailgroup_import_separator_colon')),
					array('tab',$GLOBALS['LANG']->getLL('mailgroup_import_separator_tab'))
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

				($this->params['inputDisable'] == 1) ? $disableInput = 'disabled="disabled"' : $disableInput = '';

					//show configuration
				$out = '<hr /><h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_header_conf').'</h3>';
				$tblLines = array();
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_storage'),$this->makeDropdown('CSV_IMPORT[storage]',$optStorage,$this->indata['storage']));
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_remove_existing'),'<input type="checkbox" name="CSV_IMPORT[remove_existing]" value="1"'.(!$this->indata['remove_existing']?'':' checked="checked"').' '.$disableInput.'/> ');
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_first_fieldnames'),'<input type="checkbox" name="CSV_IMPORT[first_fieldname]" value="1"'.(!$this->indata['first_fieldname']?'':' checked="checked"').' '.$disableInput.'/> ');
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_separator'),$this->makeDropdown('CSV_IMPORT[delimiter]', $optDelimiter,$this->indata['delimiter'], $disableInput));
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_encapsulation'),$this->makeDropdown('CSV_IMPORT[encapsulation]', $optEncap , $this->indata['encapsulation'], $disableInput));
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_csv_validemail-description'),'<input type="checkbox" name="CSV_IMPORT[valid_email]" value="1"'.(!$this->indata['valid_email']?'':' checked="checked"').' '.$disableInput.'/> ');
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_csv_dublette-description'),'<input type="checkbox" name="CSV_IMPORT[remove_dublette]" value="1"'.(!$this->indata['remove_dublette']?'':' checked="checked"').' '.$disableInput.'/> ');
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_update_unique'),'<input type="checkbox" name="CSV_IMPORT[update_unique]" value="1"'.(!$this->indata['update_unique']?'':' checked="checked"').' '.$disableInput.'/>');
				$tblLines[]=array($GLOBALS['LANG']->getLL('mailgroup_import_record_unique'),$this->makeDropdown('CSV_IMPORT[record_unique]',$optUnique,$this->indata['record_unique'], $disableInput));

				$out.= $this->formatTable($tblLines,array('width=300','nowrap'),0,array(0,1));
				$out.= '<br /><br />';
				$out.= '<input type="submit" name="CSV_IMPORT[back]" value="'.$GLOBALS['LANG']->getLL('mailgroup_import_back').'" />
						<input type="submit" name="CSV_IMPORT[next]" value="' . $GLOBALS['LANG']->getLL('mailgroup_import_next') . '" />'.
						$this->makeHidden(array(
							'CMD' => 'displayImport',
							'importStep[next]' => 'mapping',
							'importStep[back]' => 'upload',
							'CSV_IMPORT[newFile]' => $this->indata['newFile']));
			break;

			case 'mapping':
					//show charset selector
				$cs = array_unique( array_values( $GLOBALS['LANG']->csConvObj->synonyms ) );
				$charSets = array();
				foreach( $cs as $charset )	{
					$charSets[] = array($charset,$charset);
				}

				if(!isset($this->indata['charset'])){
					$this->indata['charset'] = 'iso-8859-1';
				}
				$out .= '<hr /><h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_mapping_charset').'</h3>';
				$tblLines = array();
				$tblLines[] = array($GLOBALS['LANG']->getLL('mailgroup_import_mapping_charset_choose'),$this->makeDropdown('CSV_IMPORT[charset]',$charSets,$this->indata['charset']));
				$out .= $this->formatTable($tblLines, array('nowrap','nowrap'), 0, array(1,1), 'border="0" cellpadding="0" cellspacing="0" class="typo3-dblist"');
				$out .= '<input type="submit" name="CSV_IMPORT[update]" value="'.$GLOBALS['LANG']->getLL('mailgroup_import_update').'"/>';
				unset($tblLines);

					//show mapping form
				$out .= '<hr /><h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_mapping_conf').'</h3>';

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
				$no_map = array('image');
				$tt_address_fields = array_keys($GLOBALS['TCA']['tt_address']['columns']);
				foreach($no_map as $v){
					$tt_address_fields = GeneralUtility::removeArrayEntryByValue($tt_address_fields, $v);
				}
				$mapFields = array();
				foreach($tt_address_fields as $map){
					$mapFields[] = array($map, str_replace(':','',$GLOBALS['LANG']->sL($GLOBALS['TCA']['tt_address']['columns'][$map]['label'])));
				}
				//add 'no value'
				array_unshift($mapFields, array('noMap',$GLOBALS['LANG']->getLL('mailgroup_import_mapping_mapTo')));
				$mapFields[] = array('cats',$GLOBALS['LANG']->getLL('mailgroup_import_mapping_categories'));
				reset($csv_firstRow);
				reset($csvData);

				$tblLines = array();
				$tblLines[] = array($GLOBALS['LANG']->getLL('mailgroup_import_mapping_number'),$GLOBALS['LANG']->getLL('mailgroup_import_mapping_description'),$GLOBALS['LANG']->getLL('mailgroup_import_mapping_mapping'),$GLOBALS['LANG']->getLL('mailgroup_import_mapping_value'));
				for($i=0;$i<(count($csv_firstRow));$i++){
						//example CSV
					$exampleLines = array();
					for($j=0;$j<(count($csvData));$j++){
						$exampleLines[] = array($csvData[$j][$i]);
					}
					$tblLines[] = array($i+1,$csv_firstRow[$i],$this->makeDropdown('CSV_IMPORT[map]['.($i).']', $mapFields, $this->indata['map'][$i]), $this->formatTable($exampleLines, array('nowrap'), 0, array(0), 'border="0" cellpadding="0" cellspacing="0" class="typo3-dblist" style="width:100%; border:0px; margin:0px;"'));
				}

				if($error){
					$out.= '<h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_mapping_error').'</h3>';
					$out.= $GLOBALS['LANG']->getLL('mailgroup_import_mapping_error_detail').'<br /><ul>';
					foreach($error as $errorDetail){
						$out.= '<li>'.$GLOBALS['LANG']->getLL('mailgroup_import_mapping_error_'.$errorDetail).'</li>';
					}
					$out.= '</ul>';
				}

				//additional options
				$tblLinesAdd = array();
					//header
				$tblLinesAdd[] = array($GLOBALS['LANG']->getLL('mailgroup_import_mapping_all_html'), '<input type="checkbox" name="CSV_IMPORT[all_html]" value="1"'.(!$this->indata['all_html']?'':' checked="checked"').'/> ');
				//get categories
				$temp = BackendUtility::getModTSconfig($this->parent->id,'TCEFORM.sys_dmail_group.select_categories.PAGE_TSCONFIG_IDLIST');
				if(is_numeric($temp['value'])){
					$rowCat = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
						'*',
						'sys_dmail_category',
						'pid IN ('. $temp['value'].')'.BackendUtility::deleteClause('sys_dmail_category').BackendUtility::BEenableFields('sys_dmail_category')
					);
					if(!empty($rowCat)){
						$tblLinesAdd[] = array($GLOBALS['LANG']->getLL('mailgroup_import_mapping_cats'), '');
						if($this->indata['update_unique']) {
							$tblLinesAdd[] = array($GLOBALS['LANG']->getLL('mailgroup_import_mapping_cats_add'), '<input type="checkbox" name="CSV_IMPORT[add_cat]" value="1"'.($this->indata['add_cat']?' checked="checked"':'').'/> ');
						}
						foreach ($rowCat as $k => $v){
							$tblLinesAdd[] = array('&nbsp;&nbsp;&nbsp;'.htmlspecialchars($v['category']), '<input type="checkbox" name="CSV_IMPORT[cat]['.$k.']" value="'.$v['uid'].'"'.(($this->indata['cat'][$k]!=$v['uid'])?'':' checked="checked"').'/> ');
						}
					}
				}

				$out.= $this->formatTable($tblLines, array('nowrap','nowrap','nowrap','nowrap'), 1, array(0,0,1,1), 'border="0" cellpadding="0" cellspacing="0" class="typo3-dblist"');
				$out.= '<br /><br />';
				//additional options
				$out.= '<hr /><h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_mapping_conf_add').'</h3>';
				$out.= $this->formatTable($tblLinesAdd, array('nowrap','nowrap'), 0, array(1,1), 'border="0" cellpadding="0" cellspacing="0" class="typo3-dblist"');
				$out.= '<br /><br />';
				$out.= '<input type="submit" name="CSV_IMPORT[back]" value="'.$GLOBALS['LANG']->getLL('mailgroup_import_back').'"/>
						<input type="submit" name="CSV_IMPORT[next]" value="' . $GLOBALS['LANG']->getLL('mailgroup_import_next') . '"/>'.
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
							'CSV_IMPORT[record_unique]' => $this->indata['record_unique'],
						));

			break;

			case 'import':
					//show import messages
				$out.= '<hr /><h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_ready_import').'</h3>';
				$out.= $GLOBALS['LANG']->getLL('mailgroup_import_ready_import_label').'<br /><br />';

				$out.= '<input type="submit" name="CSV_IMPORT[back]" value="'.$GLOBALS['LANG']->getLL('mailgroup_import_back').'" />
						<input type="submit" name="CSV_IMPORT[next]" value="' . $GLOBALS['LANG']->getLL('mailgroup_import_import') . '" />'.
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
							'CSV_IMPORT[record_unique]' => $this->indata['record_unique'],
							'CSV_IMPORT[all_html]' => $this->indata['all_html'],
							'CSV_IMPORT[add_cat]' => $this->indata['add_cat'],
							'CSV_IMPORT[charset]' => $this->indata['charset'],
						));
				$hiddenMapped = array();
				foreach($this->indata['map'] as $fieldNr => $fieldMapped){
					$hiddenMapped[]	= $this->makeHidden('CSV_IMPORT[map]['.$fieldNr.']', $fieldMapped);
				}
				if(is_array($this->indata['cat'])){
					foreach($this->indata['cat'] as $k => $catUID){
						$hiddenMapped[] = $this->makeHidden('CSV_IMPORT[cat]['.$k.']', $catUID);
					}
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
				$out = '<hr /><h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_done').'</h3>';

				$defaultOrder = array('new','update','invalid_email','double');

				if ( !empty($this->params['resultOrder']) ) {
					$resultOrder = GeneralUtility::trimExplode(',',$this->params['resultOrder']);
				} else {
					$resultOrder = array();
				}

				$diffOrder = array_diff($defaultOrder,$resultOrder);
				$endOrder = array_merge($resultOrder,$diffOrder);

				foreach ( $endOrder as $order ) {
					$tblLines = array();
					$tblLines[] = array($GLOBALS['LANG']->getLL('mailgroup_import_report_'.$order));
					if ( is_array($result[$order]) ) {
						foreach($result[$order] as $k => $v){
							$mapKeys = array_keys($v);
							$tblLines[]= array($k+1, $v[$mapKeys[0]], $v['email']);
						}
					}
					$out.= $this->formatTable($tblLines, array('nowrap', 'first' => 'colspan="3"'), 1, array(1));
				}

				//back button
				$out.= $this->makeHidden(array(
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
							'CSV_IMPORT[record_unique]' => $this->indata['record_unique'],
							'CSV_IMPORT[all_html]' => $this->indata['all_html'],
							'CSV_IMPORT[charset]' => $this->indata['charset'],
						));
				$hiddenMapped = array();
				foreach($this->indata['map'] as $fieldNr => $fieldMapped){
					$hiddenMapped[]	= $this->makeHidden('CSV_IMPORT[map]['.$fieldNr.']', $fieldMapped);
				}
				if(is_array($this->indata['cat'])) {
					foreach($this->indata['cat'] as $k => $catUID){
						$hiddenMapped[] = $this->makeHidden('CSV_IMPORT[cat]['.$k.']', $catUID);
					}
				}
				$out.=implode('',$hiddenMapped);

			break;

			case 'upload':
			default:
					//show upload file form
				$out = '<hr /><h3>'.$GLOBALS['LANG']->getLL('mailgroup_import_header_upload').'</h3>';
				$tempDir = $this->userTempFolder();

				$tblLines[] = $GLOBALS['LANG']->getLL('mailgroup_import_upload_file').'<input type="file" name="upload_1" size="30" />';
				if(($this->indata['mode'] == 'file') && !(((strpos($currentFileInfo['file'],'import')=== false)?0:1) && ($currentFileInfo['realFileext'] === 'txt'))){
					$tblLines[] = $GLOBALS['LANG']->getLL('mailgroup_import_current_file').'<b>'.$currentFileMessage.'</b>';
				}

				if(((strpos($currentFileInfo['file'],'import')=== false)?0:1) && ($currentFileInfo['realFileext'] === 'txt')){
					$handleCSV = fopen($this->indata['newFile'],'r');
					$this->indata['csv'] = fread($handleCSV, filesize($this->indata['newFile']));
					fclose($handleCSV);
				}

				$tblLines[] = '';
				$tblLines[] = '<b>'.$GLOBALS['LANG']->getLL('mailgroup_import_or').'</b>';
				$tblLines[] = '';
				$tblLines[] = $GLOBALS['LANG']->getLL('mailgroup_import_paste_csv');
				$tblLines[] = '<textarea name="CSV_IMPORT[csv]" rows="25" wrap="off"'.$this->parent->doc->formWidthText(48,'','off').'>'.GeneralUtility::formatForTextarea($this->indata['csv']).'</textarea>';
				$tblLines[] = '<input type="submit" name="CSV_IMPORT[next]" value="' . $GLOBALS['LANG']->getLL('mailgroup_import_next') . '" />';

				$out.= implode('<br />', $tblLines);

				$out.= '<input type="hidden" name="CMD" value="displayImport" />
						<input type="hidden" name="importStep[next]" value="conf" />
						<input type="hidden" name="file[upload][1][target]" value="'.htmlspecialchars($tempDir).'" '.($_POST['importNow'] ? 'disabled' : '').'/>
						<input type="hidden" name="file[upload][1][data]" value="1" />
						<input type="hidden" name="CSV_IMPORT[newFile]" value ="'.$this->indata['newFile'].'">';
			break;
		}

		$theOutput = $this->parent->doc->section($GLOBALS['LANG']->getLL('mailgroup_import').BackendUtility::cshItem($this->cshTable,'mailgroup_import',$GLOBALS['BACK_PATH']),$out, 1, 1, 0, TRUE);

		/**
		 *  Hook for cmd_displayImport
		 *  use it to manipulate the steps in the import process
		 */
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['cmd_displayImport'])) {
	   		$hookObjectsArr = array();
	   		foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['cmd_displayImport'] as $classRef) {
				$hookObjectsArr[] = &GeneralUtility::getUserObj($classRef);
			}
		}
		if(is_array($hookObjectsArr)){
			foreach($hookObjectsArr as $hookObj)    {
				if (method_exists($hookObj, 'cmd_displayImport')) {
					$theOutput = '';
					$theOutput = $hookObj->cmd_displayImport($this);
				}
			}
		}

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
		$remove = array();
		$filtered = array();
		$double = array();

		foreach($mappedCSV as $k => $csvData){
			if(!in_array($k,$remove)){
				$found=0;
				foreach($cmpCSV as $kk =>$cmpData){
					if($k != $kk){
						if($csvData[$this->indata['record_unique']] == $cmpData[$this->indata['record_unique']]){
							$double[]=$mappedCSV[$kk];
							if (!$found) {
								$filtered[] = $csvData;
							}
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
		$resultImport = array();
		$filteredCSV = array();

		//empty table if flag is set
		if($this->indata['remove_existing']){
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tt_address','pid = '.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->indata['storage'],'tt_address'));
		}

		$mappedCSV = array();
		$invalidEmailCSV = array();
		foreach($csvData as $dataArray){
			$tempData = array();
			$invalidEmail = 0;
			foreach($dataArray as $kk => $fieldData){
				if($this->indata['map'][$kk] !== 'noMap'){
					if(($this->indata['valid_email']) && ($this->indata['map'][$kk] === 'email')){
						$invalidEmail = GeneralUtility::validEmail(trim($fieldData))?0:1;
						$tempData[$this->indata['map'][$kk]] = trim($fieldData);
					} else {
						if ($this->indata['map'][$kk] !== 'cats'){
							$tempData[$this->indata['map'][$kk]] = $fieldData;
						} else {
							$tempCats = explode(',',$fieldData);
							foreach($tempCats as $catC => $tempCat) {
								$tempData['module_sys_dmail_category'][$catC] = $tempCat;
							}
						}
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
		$data = array();
		if($this->indata['update_unique']){
			$user = array();
			$userID = array();
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'uid,'.$this->indata['record_unique'],
				'tt_address',
				'pid = '.$this->indata['storage'].BackendUtility::deleteClause('tt_address')
			);
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res)){
				$user[]=$row[1];
				$userID[]=$row[0];
			}

			//check user one by one, new or update
			$c = 1;
			foreach($mappedCSV as $dataArray){
				$foundUser = array_keys($user, $dataArray[$this->indata['record_unique']]);
				if(is_array($foundUser) && !empty($foundUser)){
					if(count($foundUser) == 1){
						$data['tt_address'][$userID[$foundUser[0]]]= $dataArray;
						$data['tt_address'][$userID[$foundUser[0]]]['pid'] = $this->indata['storage'];
						if($this->indata['all_html']){
							$data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_html'] = $this->indata['all_html'];
						}
						if( is_array($this->indata['cat']) && !GeneralUtility::inArray($this->indata['map'], 'cats') ){
							if($this->indata['add_cat']){
								// Load already assigned categories
								$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
									'uid_local,uid_foreign',
									'sys_dmail_ttaddress_category_mm',
									'uid_local='.$userID[$foundUser[0]],
									'',
									'sorting'
								);
								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res)){
									$data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_category'][] = $row[1];
								}
							}
							// Add categories
							foreach($this->indata['cat'] as $v){
								$data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_category'][] = $v;
							}
						}
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
					$this->addDataArray($data, 'NEW'.$c, $dataArray);
					$resultImport['new'][] = $dataArray;
					//counter
					$c++;
				}
			}
		} else {
			//no update, import all
			$c = 1;
			foreach($mappedCSV as $dataArray){
				$this->addDataArray($data, 'NEW'.$c, $dataArray);
				$resultImport['new'][] = $dataArray;
				$c++;
			}
		}

		$resultImport['invalid_email'] = $invalidEmailCSV;
		$resultImport['double'] = (is_array($filteredCSV['double']))?$filteredCSV['double']: array();

		// start importing
		/** @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
		$tce = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
		$tce->stripslashes_values = 0;
		$tce->enableLogging = 0;
		$tce->start($data,array());
		$tce->process_datamap();

		/**
		 * Hook for doImport Mail
		 * will be called every time a record is inserted
		 */
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['doImport'])) {
			$hookObjectsArr = array();
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['doImport'] as $classRef) {
				$hookObjectsArr[] = &GeneralUtility::getUserObj($classRef);
			}

			foreach($hookObjectsArr as $hookObj)    {
				if (method_exists($hookObj, 'doImport')) {
					$hookObj->doImport($this,$data,$c);
				}
			}
		}

		return $resultImport;
	}

	/**
	 * Prepare insert array for the TCE
	 * @param array $data: array for the TCE
	 * @param string $id
	 * @param array $dataArray: the data to be imported
	 */
	function addDataArray(&$data, $id, $dataArray) {
		$data['tt_address'][$id] = $dataArray;
		$data['tt_address'][$id]['pid'] = $this->indata['storage'];
		if($this->indata['all_html']){
			$data['tt_address'][$id]['module_sys_dmail_html'] = $this->indata['all_html'];
		}
		if( is_array($this->indata['cat']) && !GeneralUtility::inArray($this->indata['map'], 'cats') ){
			foreach($this->indata['cat'] as $k => $v){
				$data['tt_address'][$id]['module_sys_dmail_category'][$k] = $v;
			}
		}
	}

	/**
	 * make dropdown menu
	 *
	 * @param	string	$name: name of the dropdown
	 * @param	array	$option: array of array (v,k)
	 * @param	string	$selected: set selected flag
	 * @param	string	$disableInput: flag to disable the input field
	 * @return	string	HTML code of the dropdown
	 */
	function makeDropdown($name, $option, $selected, $disableInput=''){
		$opt = array();
		foreach($option as $v){
			if (is_array($v)){
				$opt[] = '<option value="'.htmlspecialchars($v[0]).'" '.($selected==$v[0]?' selected="selected"':'').'>'.htmlspecialchars($v[1]).'</option>';
			}
		}

		$dropdown = '<select name="'.$name.'" '.$disableInput.'>'.implode('',$opt).'</select>';
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
				$hiddenFields[] = '<input type="hidden" name="'.htmlspecialchars($n).'" value="'.htmlspecialchars($v).'" />';
			}
			return implode('',$hiddenFields);
		} else {
			return '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'" />';
		}
	}

	/**
	 * Read in the given CSV file. The function is used during the final file import.
	 * Removes first the first data row if the CSV has fieldnames.
	 *
	 * @return	array		file content in array
	 */
	function readCSV() {
		ini_set('auto_detect_line_endings',TRUE);
		$mydata = array();
		$handle = fopen($this->indata['newFile'], "r");
		$delimiter = $this->indata['delimiter'];
		$encaps = $this->indata['encapsulation'];
		$delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
		$delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
		$delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
		$delimiter = ($delimiter === 'tab') ? TAB : $delimiter;
		$encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
		$encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;
		while (($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== FALSE) {
			//remove empty line in csv
			if((count($data) >= 1)) {
				$mydata[] = $data;
			}
		}
		fclose ($handle);
		reset ($mydata);
		$mydata = $this->convCharset($mydata);
		ini_set('auto_detect_line_endings',FALSE);
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
		ini_set('auto_detect_line_endings',TRUE);

		$mydata = array();
		// TYPO3 6.0 works with relative path, we need absolute here
		if (!is_file($this->indata['newFile']) && (strpos($this->indata['newFile'],PATH_site) === FALSE)) {
			$this->indata['newFile'] = PATH_site . $this->indata['newFile'];
		}
		$handle = fopen($this->indata['newFile'], "r");
		$i = 0;
		$delimiter = $this->indata['delimiter'];
		$encaps = $this->indata['encapsulation'];
		$delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
		$delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
		$delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
		$delimiter = ($delimiter === 'tab') ? TAB : $delimiter;
		$encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
		$encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;
		while ((($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== FALSE)) {
			//remove empty line in csv
			if((count($data) >= 1) ) {
				$mydata[] = $data;
				$i++;
				if($i>=$records)
					break;
			}
		}
		fclose ($handle);
		reset ($mydata);
		$mydata = $this->convCharset($mydata);
		ini_set('auto_detect_line_endings',FALSE);
		return $mydata;
	}

		/**
	 * Convert charset if necessary
	 *
	 * @param	array	$data contains values to convert
	 * @return	array	array of charset-converted values
	 * @see \TYPO3\CMS\Core\Charset\CharsetConverter::convArray()
	 */
	function convCharset($data) {
		$dbCharset = 'utf-8';
		if ( $dbCharset != $this->indata['charset'] )	{
			$GLOBALS['LANG']->csConvObj->convArray( $data, $this->indata['charset'], $dbCharset );
		}
		return $data;
	}


	/**
	 * formating the given array in to HTML table
	 *
	 * @param	array		$tableLines: array of table row -> array of cells
	 * @param	array		$cellParams: cells' parameter
	 * @param	boolean		$header: first tableLines is table header
	 * @param	array		$cellcmd: escaped cells' value
	 * @param	string		$tableParams: table's parameter
	 * @return	string		HTML the table
	 */
	function formatTable($tableLines, $cellParams, $header, $cellcmd = array(), $tableParams='border="0" cellpadding="0" cellspacing="0" class="typo3-dblist"')	{
		$lines = array();
		$first = $header?1:0;
		$c = 0;

		reset($tableLines);
		foreach($tableLines as $r) {
			$rowA = array();
			for($k = 0; $k < count($r); $k++)	{
				$v = $r[$k];
				$v = strlen($v) ? ($cellcmd[$k]?$v:htmlspecialchars($v)) : "&nbsp;";
				if ($first) {
					$v = '<B>'.$v.'</B>';
				}

				$cellParam = array();
				if ($cellParams[$k]) {
					$cellParam[] = $cellParams[$k];
				}

				if ($first && isset($cellParams['first'])) {
					$cellParam[] = $cellParams['first'];
				}
				$rowA[] = '<td '.implode(' ',$cellParam).'>'.$v.'</td>';
			}

			$lines[] = '<tr class="'.($first?'t3-row-header':'db_list_normal').'">'.implode('',$rowA).'</tr>';
			$first = 0;
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
		return $tempFolder = 'fileadmin/_temp_/';
	}

	/**
	 * write CSV Data to a temporary file and will be used for the import
	 *
	 * @return	string		path of the temp file
	 */
	function writeTempFile(){
		$newfile = "";
		$user_perms = $GLOBALS['BE_USER']->getFilePermissions();

		unset($this->fileProcessor);

		//add uploads/tx_directmail to user filemounts
		$GLOBALS['FILEMOUNTS']['tx_directmail'] = array(
			'name' => 'direct_mail',
			'path' => GeneralUtility::getFileAbsFileName('uploads/tx_directmail/'),
			'type'
		);

		// Initializing:
		/** @var $fileProcessor \TYPO3\CMS\Core\Utility\File\ExtendedFileUtility */
		$this->fileProcessor = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Utility\\File\\ExtendedFileUtility');
		$this->fileProcessor->init($GLOBALS['FILEMOUNTS'], $GLOBALS['TYPO3_CONF_VARS']['BE']['fileExtensions']);
		$this->fileProcessor->init_actionPerms($user_perms);
		$this->fileProcessor->dontCheckForUnique = 1;

		if (is_array($GLOBALS['FILEMOUNTS']) && !empty($GLOBALS['FILEMOUNTS'])) {
			// we have a filemount
			// do something here
		} else {
			// we don't have a valid file mount
			// should be fixed

			// this throws a error message because we have no rights to upload files
			// to our extension's own upload folder
			// further investigation needed
			$file['upload']['1']['target'] = GeneralUtility::getFileAbsFileName('uploads/tx_directmail/');
		}

		// Checking referer / executing:
		$refInfo = parse_url(GeneralUtility::getIndpEnv('HTTP_REFERER'));
		$httpHost = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');

		if(empty($this->indata['newFile'])){
				//new file
			$file['newfile']['1']['target']=$this->userTempFolder();
			$file['newfile']['1']['data']='import_'.$GLOBALS['EXEC_TIME'].'.txt';
			if ($httpHost != $refInfo['host'] && $this->vC != $GLOBALS['BE_USER']->veriCode() && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer'])	{
				$this->fileProcessor->writeLog(0,2,1,'Referer host "%s" and server host "%s" did not match!',array($refInfo['host'],$httpHost));
			} else {
				$this->fileProcessor->start($file);
				$newfileObj = $this->fileProcessor->func_newfile($file['newfile']['1']);
				// in TYPO3 6.0 func_newfile returns an object, but we need the path to the new file name later on!
				if(is_object($newfileObj)){
					$storageConfig = $newfileObj->getStorage()->getConfiguration();
					$newfile = $storageConfig['basePath'].ltrim($newfileObj->getIdentifier(),'/');
				}
			}
		} else {
			$newfile = $this->indata['newFile'];
		}

		if($newfile){
			$csvFile['data'] = $this->indata['csv'];
			$csvFile['target'] = $newfile;
			$write = $this->fileProcessor->func_edit($csvFile);
		}
		return $newfile;
	}

	/**
	 * Checks if a file has been uploaded and returns the complete physical fileinfo if so.
	 *
	 * @return	string		the complete physical file name, including path info.
	 */
	function checkUpload()	{
		$file = GeneralUtility::_GP('file');
		$fm = array();

		$tempFolder = $this->userTempFolder();
		$fm = array(
			$GLOBALS['EXEC_TIME'] => array (
				'path' => $tempFolder,
				'name' => array_pop( explode( '/', trim( $tempFolder, '/' ) ) ). '/',
			)
		);

		// Initializing:
		/** @var $fileProcessor TYPO3\CMS\Core\Utility\File\ExtendedFileUtility */
		$this->fileProcessor = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Utility\\File\\ExtendedFileUtility');
		$this->fileProcessor->init($fm, $GLOBALS['TYPO3_CONF_VARS']['BE']['fileExtensions']);
		$this->fileProcessor->setActionPermissions();
		$this->fileProcessor->dontCheckForUnique = 1;

		// Checking referer / executing:
		$refInfo = parse_url(GeneralUtility::getIndpEnv('HTTP_REFERER'));
		$httpHost = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');

		if ($httpHost != $refInfo['host'] && $this->vC != $GLOBALS['BE_USER']->veriCode() && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
			$this->fileProcessor->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!', array($refInfo['host'], $httpHost));
		} else {
			$this->fileProcessor->start($file);
			$newfile = $this->fileProcessor->func_upload($file['upload']['1']);
		}
		return $newfile;
	}
}

?>
