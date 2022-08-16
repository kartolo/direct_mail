<?php
namespace DirectMailTeam\DirectMail;

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

use DirectMailTeam\DirectMail\Module\RecipientList;
use DirectMailTeam\DirectMail\Repository\PagesRepository;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\File\ExtendedFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;

/**
 * Recipient list module for tx_directmail extension
 *
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage	tx_directmail
 */
class Importer
{
    /**
     * The GET-Data
     * @var array
     */
    public $indata = [];
    public $params = [];

    /**
     * Parent object
     *
     * @var RecipientList
     */
    public $parent;

    /**
     * File Processor FAL
     *
     * @var ExtendedFileUtility
     */
    public $fileProcessor;

    /**
     * Init the class
     *
     * @param $pObj $pObj The parent object
     *
     * @return void
     */
    public function init(&$pObj)
    {
        $this->parent = &$pObj;

        // get some importer default from pageTS
        $this->params = BackendUtility::getPagesTSconfig((int)GeneralUtility::_GP('id'))['mod.']['web_modules.']['dmail.']['importer.'] ?? [];
    }

    /**
     * Import CSV-Data in step-by-step mode
     *
     * @return	array		HTML form
     */
    public function displayImport()
    {
        $output = [
            'title' => '',
            'subtitle' => '',
            'upload' => [
                'show' => false,
                'current' => false,
                'csv' => '',
                'target' => '',
                'target_disabled' => '',
                'newFile' => ''
            ],
            'conf' => [
                'show' => false,
                'remove_existing' => false,
                'first_fieldname' => false,
                'delimiter' => '',
                'encapsulation' => '',
                'valid_email' => false,
                'remove_dublette' => false,
                'update_unique' => false,
                'record_unique' => '',
                'newFile' => '',
                'disableInput' => false
            ]
        ];
        
        $beUser = $this->getBeUser();
        $step = GeneralUtility::_GP('importStep');
        $defaultConf = [
            'remove_existing' => 0,
            'first_fieldname' => 0,
            'valid_email' => 0,
            'remove_dublette' => 0,
            'update_unique' => 0
        ];

        if (GeneralUtility::_GP('CSV_IMPORT')) {
            $importerConfig = GeneralUtility::_GP('CSV_IMPORT');
            if ($step['next'] === 'mapping') {
                $this->indata = $importerConfig + $defaultConf;
            } else {
                $this->indata = $importerConfig;
            }
        }

        if (empty($this->indata)) {
            $this->indata = [];
        }

        if (empty($this->params)) {
            $this->params = [];
        }
        // merge it with inData, but inData has priority.
        $this->indata += $this->params;

        //		$currentFileInfo = BasicFileUtility::getTotalFileInfo($this->indata['newFile']);
        //		$currentFileName = $currentFileInfo['file'];
        //		$currentFileSize = GeneralUtility::formatSize($currentFileInfo['size']);
        //		$currentFileMessage = $currentFileName . ' (' . $currentFileSize . ')';

        if (empty($this->indata['csv']) && !empty($_FILES['upload_1']['name'])) {
            $this->indata['newFile'] = $this->checkUpload();
            // TYPO3 6.0 returns an object...
            if (is_object($this->indata['newFile'][0])) {
                $storageConfig = $this->indata['newFile'][0]->getStorage()->getConfiguration();
                $this->indata['newFile'] = rtrim($storageConfig['basePath'], '/') . '/' . ltrim($this->indata['newFile'][0]->getIdentifier(), '/');
            }
        } elseif (!empty($this->indata['csv']) && empty($_FILES['upload_1']['name'])) {
            if (((strpos($currentFileInfo['file'], 'import') === false) ? 0 : 1) && ($currentFileInfo['realFileext'] === 'txt')) {
                // do nothing
            } else {
                unset($this->indata['newFile']);
            }
        }

        $stepCurrent = '';
        if ($this->indata['back'] ?? false) {
            $stepCurrent = $step['back'];
        } elseif ($this->indata['next'] ?? false) {
            $stepCurrent = $step['next'];
        } elseif ($this->indata['update'] ?? false) {
            $stepCurrent = 'mapping';
        }

        if (strlen($this->indata['csv'] ?? 0) > 0) {
            $this->indata['mode'] = 'csv';
            $this->indata['newFile'] = $this->writeTempFile();
        } elseif (!empty($this->indata['newFile'])) {
            $this->indata['mode'] = 'file';
        } else {
            unset($stepCurrent);
        }

        // check if "email" is mapped
        if (isset($stepCurrent) && $stepCurrent === 'import') {
            $map = $this->indata['map'];
            $error = [];
            // check noMap
            $newMap = ArrayUtility::removeArrayEntryByValue(array_unique($map), 'noMap');
            if (empty($newMap)) {
                $error[] = 'noMap';
            } elseif (!in_array('email', $map)) {
                $error[] = 'email';
            }

            if ($error) {
                $stepCurrent = 'mapping';
            }
        }

        $out = '';
        switch ($stepCurrent) {
            case 'conf':
                $output['conf']['show'] = true;
                $output['conf']['newFile'] = $this->indata['newFile'];
                $pagePermsClause3 = $beUser->getPagePermsClause(3);
                $pagePermsClause1 = $beUser->getPagePermsClause(1);
                // get list of sysfolder
                // TODO: maybe only subtree von this->id??

                $optStorage = [];
                $subfolders = GeneralUtility::makeInstance(PagesRepository::class)->selectSubfolders($pagePermsClause3);
                if($subfolders && count($subfolders)) {
                    foreach($subfolders as $subfolder) {
                        if (BackendUtility::readPageAccess($subfolder['uid'], $pagePermsClause1)) {
                            $optStorage[] = [$subfolder['uid'], $subfolder['title'] . ' [uid:' . $subfolder['uid'] . ']'];
                        }
                    }
                }

                $optDelimiter = [
                    ['comma', $this->getLanguageService()->getLL('mailgroup_import_separator_comma')],
                    ['semicolon', $this->getLanguageService()->getLL('mailgroup_import_separator_semicolon')],
                    ['colon', $this->getLanguageService()->getLL('mailgroup_import_separator_colon')],
                    ['tab', $this->getLanguageService()->getLL('mailgroup_import_separator_tab')]
                ];

                $optEncap = [
                    ['doubleQuote', ' " '],
                    ['singleQuote', " ' "],
                ];

                // TODO: make it variable?
                $optUnique = [
                    ['email', 'email'],
                    ['name', 'name']
                ];

                $output['conf']['disableInput'] = $this->params['inputDisable'] == 1 ? true : false;
                
                ($this->params['inputDisable'] == 1) ? $disableInput = 'disabled="disabled"' : $disableInput = '';

                // show configuration
                $output['subtitle'] = $this->getLanguageService()->getLL('mailgroup_import_header_conf');

                // get the all sysfolder
                $output['conf']['storage'] = $this->makeDropdown('CSV_IMPORT[storage]', $optStorage, $this->indata['storage']);

                // remove existing option
                $output['conf']['remove_existing'] = !$this->indata['remove_existing'] ? false : true;

                // first line in csv is to be ignored
                $output['conf']['first_fieldname'] = !$this->indata['first_fieldname'] ? false : true;

                // csv separator
                $output['conf']['delimiter'] = $this->makeDropdown('CSV_IMPORT[delimiter]', $optDelimiter, $this->indata['delimiter'], $disableInput);

                // csv encapsulation
                $output['conf']['encapsulation'] = $this->makeDropdown('CSV_IMPORT[encapsulation]', $optEncap, $this->indata['encapsulation'], $disableInput);

                // import only valid email
                $output['conf']['valid_email'] = !$this->indata['valid_email'] ? false : true;

                // only import distinct records
                $output['conf']['remove_dublette'] = !$this->indata['remove_dublette'] ? false : true;

                // update the record instead renaming the new one
                $output['conf']['update_unique'] = !$this->indata['update_unique'] ? false : true;

                // which field should be use to show uniqueness of the records
                $output['conf']['record_unique'] = $this->makeDropdown('CSV_IMPORT[record_unique]', $optUnique, $this->indata['record_unique'], $disableInput);

                break;

            case 'mapping':
                // show charset selector
                $cs = array_unique(array_values(mb_list_encodings()));
                $charSets = [];
                foreach ($cs as $charset) {
                    $charSets[] = [$charset, $charset];
                }

                if (!isset($this->indata['charset'])) {
                    $this->indata['charset'] = 'ISO-8859-1';
                }
                $output['subtitle'] = $this->getLanguageService()->getLL('mailgroup_import_mapping_charset');

                $tblLines = [];
                $tblLines[] = [$this->getLanguageService()->getLL('mailgroup_import_mapping_charset_choose'), $this->makeDropdown('CSV_IMPORT[charset]', $charSets, $this->indata['charset'])];
                $out .= $this->formatTable($tblLines, ['nowrap', 'nowrap'], 0, [1, 1], 'border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover"');
                $out .= '<input type="submit" name="CSV_IMPORT[update]" value="' . $this->getLanguageService()->getLL('mailgroup_import_update') . '"/>';
                unset($tblLines);

                // show mapping form
                $out .= '<hr /><h3>' . $this->getLanguageService()->getLL('mailgroup_import_mapping_conf') . '</h3>';

                if ($this->indata['first_fieldname']) {
                    // read csv
                    $csvData = $this->readExampleCSV(4);
                    $csv_firstRow = $csvData[0];
                    $csvData = array_slice($csvData, 1);
                } else {
                    // read csv
                    $csvData = $this->readExampleCSV(3);
                    $fieldsAmount = count($csvData[0]);
                    $csv_firstRow = [];
                    for ($i=0; $i<$fieldsAmount; $i++) {
                        $csv_firstRow[] = 'field_' . $i;
                    }
                }

                // read tt_address TCA
                $no_map = ['image', 'sys_language_uid', 'l10n_parent', 'l10n_diffsource', 't3_origuid', 'cruser_id', 'crdate', 'tstamp'];
                $ttAddressFields = array_keys($GLOBALS['TCA']['tt_address']['columns']);
                foreach ($no_map as $v) {
                    $ttAddressFields = ArrayUtility::removeArrayEntryByValue($ttAddressFields, $v);
                }
                $mapFields = [];
                foreach ($ttAddressFields as $map) {
                    $mapFields[] = [$map, str_replace(':', '', $this->getLanguageService()->sL($GLOBALS['TCA']['tt_address']['columns'][$map]['label']))];
                }
                // add 'no value'
                array_unshift($mapFields, ['noMap', $this->getLanguageService()->getLL('mailgroup_import_mapping_mapTo')]);
                $mapFields[] = ['cats', $this->getLanguageService()->getLL('mailgroup_import_mapping_categories')];
                reset($csv_firstRow);
                reset($csvData);

                $tblLines = [];
                $tblLines[] = [$this->getLanguageService()->getLL('mailgroup_import_mapping_number'),$this->getLanguageService()->getLL('mailgroup_import_mapping_description'),$this->getLanguageService()->getLL('mailgroup_import_mapping_mapping'),$this->getLanguageService()->getLL('mailgroup_import_mapping_value')];
                for ($i = 0; $i < (count($csv_firstRow)); $i++) {
                    // example CSV
                    $exampleLines = [];
                    for ($j = 0; $j < (count($csvData)); $j++) {
                        $exampleLines[] = [$csvData[$j][$i]];
                    }
                    $tblLines[] = [$i+1, $csv_firstRow[$i], $this->makeDropdown('CSV_IMPORT[map][' . ($i) . ']', $mapFields, $this->indata['map'][$i]), $this->formatTable($exampleLines, ['nowrap'], 0, [0], 'border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover" style="width:100%; border:0px; margin:0px;"')];
                }

                if ($error) {
                    $out .= '<h3>' . $this->getLanguageService()->getLL('mailgroup_import_mapping_error') . '</h3>';
                    $out .= $this->getLanguageService()->getLL('mailgroup_import_mapping_error_detail') . '<br /><ul>';
                    foreach ($error as $errorDetail) {
                        $out .= '<li>' . $this->getLanguageService()->getLL('mailgroup_import_mapping_error_' . $errorDetail) . '</li>';
                    }
                    $out.= '</ul>';
                }

                // additional options
                $tblLinesAdd = [];

                // header
                $tblLinesAdd[] = [$this->getLanguageService()->getLL('mailgroup_import_mapping_all_html'), '<input type="checkbox" name="CSV_IMPORT[all_html]" value="1"' . (!$this->indata['all_html']?'':' checked="checked"') . '/> '];
                // get categories
                $temp['value'] = BackendUtility::getPagesTSconfig($this->parent->id)['TCEFORM.']['sys_dmail_group.']['select_categories.']['PAGE_TSCONFIG_IDLIST'] ?? null;
                if (is_numeric($temp['value'])) {
                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_category');
                    $rowCat = $queryBuilder
                        ->select('*')
                        ->from('sys_dmail_category')
                        ->where(
                            $queryBuilder->expr()->in(
                                'pid',
                                $temp
                            )
                        )
                        ->execute()
                        ->fetchAll();

                    if (!empty($rowCat)) {
                        $tblLinesAdd[] = [$this->getLanguageService()->getLL('mailgroup_import_mapping_cats'), ''];
                        if ($this->indata['update_unique']) {
                            $tblLinesAdd[] = [$this->getLanguageService()->getLL('mailgroup_import_mapping_cats_add'), '<input type="checkbox" name="CSV_IMPORT[add_cat]" value="1"' . ($this->indata['add_cat']?' checked="checked"':'') . '/> '];
                        }
                        foreach ($rowCat as $k => $v) {
                            $tblLinesAdd[] = ['&nbsp;&nbsp;&nbsp;' . htmlspecialchars($v['category']), '<input type="checkbox" name="CSV_IMPORT[cat][' . $k . ']" value="' . $v['uid'] . '"' . (($this->indata['cat'][$k] != $v['uid']) ? '' :' checked="checked"') . '/> '];
                        }
                    }
                }

                $out .= $this->formatTable($tblLines, ['nowrap', 'nowrap', 'nowrap', 'nowrap'], 1, [0, 0, 1, 1], 'border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover"');
                $out .= '<br /><br />';
                // additional options
                $out .= '<hr /><h3>' . $this->getLanguageService()->getLL('mailgroup_import_mapping_conf_add') . '</h3>';
                $out .= $this->formatTable($tblLinesAdd, ['nowrap', 'nowrap'], 0, [1, 1], 'border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover"');
                $out .= '<br /><br />';
                $out .= '<input type="submit" name="CSV_IMPORT[back]" value="' . $this->getLanguageService()->getLL('mailgroup_import_back') . '"/>
						<input type="submit" name="CSV_IMPORT[next]" value="' . $this->getLanguageService()->getLL('mailgroup_import_next') . '"/>' .
                        $this->makeHidden([
                            'cmd' => 'displayImport',
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
                        ]);
                break;

            case 'import':
                // show import messages
                $output['subtitle'] = $this->getLanguageService()->getLL('mailgroup_import_ready_import');

                $out .= $this->getLanguageService()->getLL('mailgroup_import_ready_import_label') . '<br /><br />';

                $out .= '<input type="submit" name="CSV_IMPORT[back]" value="' . $this->getLanguageService()->getLL('mailgroup_import_back') . '" />
						<input type="submit" name="CSV_IMPORT[next]" value="' . $this->getLanguageService()->getLL('mailgroup_import_import') . '" />' .
                        $this->makeHidden([
                            'cmd' => 'displayImport',
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
                        ]);
                $hiddenMapped = [];
                foreach ($this->indata['map'] as $fieldNr => $fieldMapped) {
                    $hiddenMapped[]    = $this->makeHidden('CSV_IMPORT[map][' . $fieldNr . ']', $fieldMapped);
                }
                if (is_array($this->indata['cat'])) {
                    foreach ($this->indata['cat'] as $k => $catUid) {
                        $hiddenMapped[] = $this->makeHidden('CSV_IMPORT[cat][' . $k . ']', $catUid);
                    }
                }
                $out.=implode('', $hiddenMapped);
                break;

            case 'startImport':
                // starting import & show errors
                // read csv
                if ($this->indata['first_fieldname']) {
                    // read csv
                    $csvData = $this->readCSV();
                    $csvData = array_slice($csvData, 1);
                } else {
                    // read csv
                    $csvData = $this->readCSV();
                }

                // show not imported record and reasons,
                $result = $this->doImport($csvData);
                $output['subtitle'] = $this->getLanguageService()->getLL('mailgroup_import_done');

                $defaultOrder = ['new','update','invalid_email','double'];

                if (!empty($this->params['resultOrder'])) {
                    $resultOrder = GeneralUtility::trimExplode(',', $this->params['resultOrder']);
                } else {
                    $resultOrder = [];
                }

                $diffOrder = array_diff($defaultOrder, $resultOrder);
                $endOrder = array_merge($resultOrder, $diffOrder);

                foreach ($endOrder as $order) {
                    $tblLines = [];
                    $tblLines[] = [$this->getLanguageService()->getLL('mailgroup_import_report_' . $order)];
                    if (is_array($result[$order])) {
                        foreach ($result[$order] as $k => $v) {
                            $mapKeys = array_keys($v);
                            $tblLines[] = [$k+1, $v[$mapKeys[0]], $v['email']];
                        }
                    }
                    $out .= $this->formatTable($tblLines, ['nowrap', 'first' => 'colspan="3"'], 1, [1]);
                }

                // back button
                $out .= $this->makeHidden([
                            'cmd' => 'displayImport',
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
                        ]);
                $hiddenMapped = [];
                foreach ($this->indata['map'] as $fieldNr => $fieldMapped) {
                    $hiddenMapped[]    = $this->makeHidden('CSV_IMPORT[map][' . $fieldNr . ']', $fieldMapped);
                }
                if (is_array($this->indata['cat'])) {
                    foreach ($this->indata['cat'] as $k => $catUid) {
                        $hiddenMapped[] = $this->makeHidden('CSV_IMPORT[cat][' . $k . ']', $catUid);
                    }
                }
                $out .= implode('', $hiddenMapped);
                break;

            case 'upload':
            default:
                // show upload file form
                $output['subtitle'] = $this->getLanguageService()->getLL('mailgroup_import_header_upload');

                if (($this->indata['mode'] === 'file') && !(((strpos($currentFileInfo['file'], 'import') === false) ? 0 : 1) && ($currentFileInfo['realFileext'] === 'txt'))) {
                    $output['upload']['current'] = true;
                    // @TODO add $currentFileMessage after 'mailgroup_import_current_file'
                }

                if (((strpos(($currentFileInfo['file'] ?? ''), 'import') === false) ? 0 : 1) && (($currentFileInfo['realFileext'] ?? '') === 'txt')) {
                    $handleCsv = fopen($this->indata['newFile'], 'r');
                    $this->indata['csv'] = fread($handleCsv, filesize($this->indata['newFile']));
                    fclose($handleCsv);
                }
                
                $output['upload']['show'] = true;
                $output['upload']['csv'] = htmlspecialchars($this->indata['csv'] ?? '');
                $output['upload']['target'] = htmlspecialchars($this->userTempFolder());
                $output['upload']['target_disabled'] = GeneralUtility::_POST('importNow') ? 'disabled' : '';
                $output['upload']['newFile'] = $this->indata['newFile'];
        }

        $output['title'] = $this->getLanguageService()->getLL('mailgroup_import') . BackendUtility::cshItem($this->cshTable ?? '', 'mailgroup_import');
        $theOutput = sprintf('%s', $out);

        /**
         *  Hook for displayImport
         *  use it to manipulate the steps in the import process
         */
        $hookObjectsArr = [];
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['displayImport']) && 
            is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['displayImport'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['displayImport'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }
        }
        if (count($hookObjectsArr)) {
            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'displayImport')) {
                    $theOutput = $hookObj->displayImport($this);
                }
            }
        }

        return ['output' => $output, 'theOutput' => $theOutput];
    }

    /*****
     * function for importing tt_address
     *****/

    /**
     * Filter doublette from input csv data
     *
     * @param array $mappedCsv Mapped csv
     *
     * @return array Filtered csv and double csv
     */
    public function filterCSV(array $mappedCsv)
    {
        $cmpCsv = $mappedCsv;
        $remove = [];
        $filtered = [];
        $double = [];

        foreach ($mappedCsv as $k => $csvData) {
            if (!in_array($k, $remove)) {
                $found=0;
                foreach ($cmpCsv as $kk =>$cmpData) {
                    if ($k != $kk) {
                        if ($csvData[$this->indata['record_unique']] == $cmpData[$this->indata['record_unique']]) {
                            $double[] = $mappedCsv[$kk];
                            if (!$found) {
                                $filtered[] = $csvData;
                            }
                            $remove[]=$kk;
                            $found=1;
                        }
                    }
                }
                if (!$found) {
                    $filtered[] = $csvData;
                }
            }
        }
        $csv['clean'] = $filtered;
        $csv['double'] = $double;

        return $csv;
    }

    /**
     * Start importing users
     *
     * @param array $csvData The csv raw data
     *
     * @return array Array containing doublette, updated and invalid-email records
     */
    public function doImport(array $csvData)
    {
        $resultImport = [];
        $filteredCSV = [];

        //empty table if flag is set
        if ($this->indata['remove_existing']) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_address');
            $queryBuilder
                ->delete('tt_address')
                ->where(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter($this->indata['storage'])
                    )
                )
                ->execute();
        }

        $mappedCSV = [];
        $invalidEmailCSV = [];
        foreach ($csvData as $dataArray) {
            $tempData = [];
            $invalidEmail = 0;
            foreach ($dataArray as $kk => $fieldData) {
                if ($this->indata['map'][$kk] !== 'noMap') {
                    if (($this->indata['valid_email']) && ($this->indata['map'][$kk] === 'email')) {
                        $invalidEmail = GeneralUtility::validEmail(trim($fieldData))?0:1;
                        $tempData[$this->indata['map'][$kk]] = trim($fieldData);
                    } else {
                        if ($this->indata['map'][$kk] !== 'cats') {
                            $tempData[$this->indata['map'][$kk]] = $fieldData;
                        } else {
                            $tempCats = explode(',', $fieldData);
                            foreach ($tempCats as $catC => $tempCat) {
                                $tempData['module_sys_dmail_category'][$catC] = $tempCat;
                            }
                        }
                    }
                }
            }
            if ($invalidEmail) {
                $invalidEmailCSV[] = $tempData;
            } else {
                $mappedCSV[]=$tempData;
            }
        }

        // remove doublette from csv data
        if ($this->indata['remove_dublette']) {
            $filteredCSV = $this->filterCSV($mappedCSV);
            unset($mappedCSV);
            $mappedCSV = $filteredCSV['clean'];
        }

        // array for the process_datamap();
        $data = [];
        if ($this->indata['update_unique']) {
            $user = [];
            $userID = [];

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_address');
            // only add deleteClause
            $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

            $statement = $queryBuilder
                ->select(
                    'uid',
                    $this->indata['record_unique']
                )
                ->from('tt_address')
                ->where(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $this->indata['storage']
                    )
                )
                ->execute();

            while (($row = $statement->fetch())) {
                $user[] = $row[1];
                $userID[] = $row[0];
            }

            // check user one by one, new or update
            $c = 1;
            foreach ($mappedCSV as $dataArray) {
                $foundUser = array_keys($user, $dataArray[$this->indata['record_unique']]);
                if (is_array($foundUser) && !empty($foundUser)) {
                    if (count($foundUser) == 1) {
                        $data['tt_address'][$userID[$foundUser[0]]] =  $dataArray;
                        $data['tt_address'][$userID[$foundUser[0]]]['pid'] = $this->indata['storage'];
                        if ($this->indata['all_html']) {
                            $data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_html'] = $this->indata['all_html'];
                        }
                        if (is_array($this->indata['cat']) && !in_array('cats', $this->indata['map'])) {
                            if ($this->indata['add_cat']) {
                                // Load already assigned categories
                                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_ttaddress_category_mm');
                                $statement = $queryBuilder
                                    ->select(
                                        'uid_local',
                                        'uid_foreign'
                                    )
                                    ->from('sys_dmail_ttaddress_category_mm')
                                    ->where(
                                        $queryBuilder->expr()->eq(
                                            'uid_local',
                                            $userID[$foundUser[0]]
                                        )
                                    )
                                    ->orderBy('sorting')
                                    ->execute();

                                while (($row = $statement->fetch())) {
                                    $data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_category'][] = $row[1];
                                }
                            }
                            // Add categories
                            foreach ($this->indata['cat'] as $v) {
                                $data['tt_address'][$userID[$foundUser[0]]]['module_sys_dmail_category'][] = $v;
                            }
                        }
                        $resultImport['update'][]=$dataArray;
                    } else {
                        // which one to update? all?
                        foreach ($foundUser as $kk => $_) {
                            $data['tt_address'][$userID[$foundUser[$kk]]]= $dataArray;
                            $data['tt_address'][$userID[$foundUser[$kk]]]['pid'] = $this->indata['storage'];
                        }
                        $resultImport['update'][]=$dataArray;
                    }
                } else {
                    // write new user
                    $this->addDataArray($data, 'NEW' . $c, $dataArray);
                    $resultImport['new'][] = $dataArray;
                    // counter
                    $c++;
                }
            }
        } else {
            // no update, import all
            $c = 1;
            foreach ($mappedCSV as $dataArray) {
                $this->addDataArray($data, 'NEW' . $c, $dataArray);
                $resultImport['new'][] = $dataArray;
                $c++;
            }
        }

        $resultImport['invalid_email'] = $invalidEmailCSV;
        $resultImport['double'] = (is_array($filteredCSV['double']))?$filteredCSV['double']: [];

        // start importing
        /* @var $tce DataHandler */
        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->stripslashes_values = 0;
        $tce->enableLogging = 0;
        $tce->start($data, []);
        $tce->process_datamap();

        /**
         * Hook for doImport Mail
         * will be called every time a record is inserted
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['doImport'])) {
            $hookObjectsArr = [];
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail/mod3/class.tx_directmail_recipient_list.php']['doImport'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }

            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'doImport')) {
                    $hookObj->doImport($this, $data, $c);
                }
            }
        }

        return $resultImport;
    }

    /**
     * Prepare insert array for the TCE
     *
     * @param array $data Array for the TCE
     * @param string $id Record ID
     * @param array $dataArray The data to be imported
     *
     * @return void
     */
    public function addDataArray(array &$data, $id, array $dataArray)
    {
        $data['tt_address'][$id] = $dataArray;
        $data['tt_address'][$id]['pid'] = $this->indata['storage'];
        if ($this->indata['all_html']) {
            $data['tt_address'][$id]['module_sys_dmail_html'] = $this->indata['all_html'];
        }
        if (is_array($this->indata['cat']) && !in_array('cats', $this->indata['map'])) {
            foreach ($this->indata['cat'] as $k => $v) {
                $data['tt_address'][$id]['module_sys_dmail_category'][$k] = $v;
            }
        }
    }

    /**
     * Make dropdown menu
     *
     * @param string $name Name of the dropdown
     * @param array $option Array of array (v,k)
     * @param string $selected Set selected flag
     * @param string $disableInput Flag to disable the input field
     *
     * @return	string	HTML code of the dropdown
     */
    public function makeDropdown($name, array $option, $selected, $disableInput='')
    {
        $opt = [];
        foreach ($option as $v) {
            if (is_array($v)) {
                $opt[] = '<option value="' . htmlspecialchars($v[0]) . '" ' . ($selected==$v[0]?' selected="selected"':'') . '>' .
                    htmlspecialchars($v[1]) .
                    '</option>';
            }
        }

        return '<select name="' . $name . '" ' . $disableInput . '>' . implode('', $opt) . '</select>';
    }

    /**
     * Make hidden field
     *
     * @param mixed $name Name of the hidden field (string) or name => value (array)
     * @param string $value Value of the hidden field
     *
     * @return	string		HTML code
     */
    public function makeHidden($name, $value='')
    {
        if (is_array($name)) {
            $hiddenFields = [];
            foreach ($name as $n=>$v) {
                $hiddenFields[] = '<input type="hidden" name="' . htmlspecialchars($n) . '" value="' . htmlspecialchars($v) . '" />';
            }
            $inputFields = implode('', $hiddenFields);
        } else {
            $inputFields = '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '" />';
        }

        return $inputFields;
    }

    /**
     * Read in the given CSV file. The function is used during the final file import.
     * Removes first the first data row if the CSV has fieldnames.
     *
     * @return	array		file content in array
     */
    public function readCSV()
    {
        ini_set('auto_detect_line_endings', true);
        $mydata = [];
        $handle = fopen($this->indata['newFile'], 'r');
	if($handle === false) {
            return $mydata;
        }
        $delimiter = $this->indata['delimiter'];
        $encaps = $this->indata['encapsulation'];
        $delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
        $delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
        $delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
        $delimiter = ($delimiter === 'tab') ? "\t" : $delimiter;
        $encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
        $encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;
        while (($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== false) {
            // remove empty line in csv
            if ((count($data) >= 1)) {
                $mydata[] = $data;
            }
        }
        fclose($handle);
        reset($mydata);
        $mydata = $this->convCharset($mydata);
        ini_set('auto_detect_line_endings', false);
        return $mydata;
    }

    /**
     * Read in the given CSV file. Only showed a couple of the CSV values as example
     * Removes first the first data row if the CSV has fieldnames.
     *
     * @param	int $records Number of example values
     *
     * @return	array File content in array
     */
    public function readExampleCSV($records=3)
    {
        ini_set('auto_detect_line_endings', true);

        $mydata = [];
        // TYPO3 6.0 works with relative path, we need absolute here
        if (!is_file($this->indata['newFile']) && (strpos($this->indata['newFile'], Environment::getPublicPath() . '/') === false)) {
            $this->indata['newFile'] = Environment::getPublicPath() . '/' . $this->indata['newFile'];
        }
        $handle = fopen($this->indata['newFile'], 'r');
        if($handle === false) {
            return $mydata;
        }
        $i = 0;
        $delimiter = $this->indata['delimiter'];
        $encaps = $this->indata['encapsulation'];
        $delimiter = ($delimiter === 'comma') ? ',' : $delimiter;
        $delimiter = ($delimiter === 'semicolon') ? ';' : $delimiter;
        $delimiter = ($delimiter === 'colon') ? ':' : $delimiter;
        $delimiter = ($delimiter === 'tab') ? "\t" : $delimiter;
        $encaps = ($encaps === 'singleQuote') ? "'" : $encaps;
        $encaps = ($encaps === 'doubleQuote') ? '"' : $encaps;
        while ((($data = fgetcsv($handle, 10000, $delimiter, $encaps)) !== false)) {
            // remove empty line in csv
            if ((count($data) >= 1)) {
                $mydata[] = $data;
                $i++;
                if ($i>=$records) {
                    break;
                }
            }
        }
        fclose($handle);
        reset($mydata);
        $mydata = $this->convCharset($mydata);
        ini_set('auto_detect_line_endings', false);
        return $mydata;
    }

    /**
     * Convert charset if necessary
     *
     * @param array $data Contains values to convert
     *
     * @return	array	array of charset-converted values
     * @see \TYPO3\CMS\Core\Charset\CharsetConverter::conv[]
     */
    public function convCharset(array $data)
    {
        $dbCharset = 'utf-8';
        if ($dbCharset != $this->indata['charset']) {
            $converter = GeneralUtility::makeInstance(CharsetConverter::class);
            foreach ($data as $k => $v) {
                $data[$k] = $converter->conv($v, strtolower($this->indata['charset']), $dbCharset);
            }
        }
        return $data;
    }


    /**
     * Formating the given array in to HTML table
     *
     * @param array $tableLines Array of table row -> array of cells
     * @param array $cellParams Cells' parameter
     * @param bool $header First tableLines is table header
     * @param array $cellcmd Escaped cells' value
     * @param string $tableParams Table's parameter
     *
     * @return	string		HTML the table
     */
    public function formatTable(
        array $tableLines, 
        array $cellParams, 
        $header, 
        array $cellcmd = [], 
        $tableParams = 'border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover"')
    {
        $lines = [];
        $first = $header?1:0;
        $c = 0;

        reset($tableLines);
        foreach ($tableLines as $r) {
            $rowA = [];
            for ($k = 0, $kMax = count($r); $k < $kMax; $k++) {
                $v = $r[$k];
                $v = strlen($v) ? ($cellcmd[$k]?$v:htmlspecialchars($v)) : '&nbsp;';
                if ($first) {
                    $v = '<b>' . $v . '</b>';
                }

                $cellParam = [];
                if ($cellParams[$k]) {
                    $cellParam[] = $cellParams[$k];
                }

                if ($first && isset($cellParams['first'])) {
                    $cellParam[] = $cellParams['first'];
                }
                $rowA[] = '<td ' . implode(' ', $cellParam) . '>' . $v . '</td>';
            }

            $lines[] = '<tr class="' . ($first ? 't3-row-header' : 'db_list_normal') . '">' . implode('', $rowA) . '</tr>';
            $first = 0;
            $c++;
        }
        return '<table ' . $tableParams . '>' . implode('', $lines) . '</table>';
    }

    /**
     * Returns first temporary folder of the user account (from $FILEMOUNTS)
     *
     * @return	string Absolute path to first "_temp_" folder of the current user, otherwise blank.
     */
    public function userTempFolder()
    {
        /** @var \TYPO3\CMS\Core\Resource\Folder $folder */
        $folder = $GLOBALS['BE_USER']->getDefaultUploadTemporaryFolder();
        return $folder->getPublicUrl();
    }

    /**
     * Write CSV Data to a temporary file and will be used for the import
     *
     * @return	string		path of the temp file
     */
    public function writeTempFile()
    {
        $newfile = '';
        $userPermissions = $GLOBALS['BE_USER']->getFilePermissions();

        unset($this->fileProcessor);

        // add uploads/tx_directmail to user filemounts
        $GLOBALS['FILEMOUNTS']['tx_directmail'] = [
            'name' => 'direct_mail',
            'path' => GeneralUtility::getFileAbsFileName('uploads/tx_directmail/'),
            'type'
        ];

        // Initializing:
        /* @var $fileProcessor ExtendedFileUtility */
        $this->fileProcessor = GeneralUtility::makeInstance(ExtendedFileUtility::class);
        $this->fileProcessor->setActionPermissions($userPermissions);
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

        if (empty($this->indata['newFile'])) {
            // new file
            $file['newfile']['1']['target']=$this->userTempFolder();
            $file['newfile']['1']['data']='import_' . $GLOBALS['EXEC_TIME'] . '.txt';
            if ($httpHost != $refInfo['host'] && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
                $this->fileProcessor->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!', [$refInfo['host'], $httpHost]);
            } else {
                $this->fileProcessor->start($file);
                $newfileObj = $this->fileProcessor->func_newfile($file['newfile']['1']);
                // in TYPO3 6.0 func_newfile returns an object, but we need the path to the new file name later on!
                if (is_object($newfileObj)) {
                    $storageConfig = $newfileObj->getStorage()->getConfiguration();
                    $newfile = $storageConfig['basePath'] . ltrim($newfileObj->getIdentifier(), '/');
                }
            }
        } else {
            $newfile = $this->indata['newFile'];
        }

        if ($newfile) {
            $csvFile['data'] = $this->indata['csv'] ?? '';
            $csvFile['target'] = $newfile;
            $write = $this->fileProcessor->func_edit($csvFile);
        }
        return $newfile;
    }

    /**
     * Checks if a file has been uploaded and returns the complete physical fileinfo if so.
     *
     * @return	string		the complete physical file name, including path info.
     * @throws \Exception
     */
    public function checkUpload()
    {
        $file = GeneralUtility::_GP('file');
        $fm = [];

        $tempFolder = $this->userTempFolder();
        $array = explode('/', trim($tempFolder, '/'));
        $fm = [
            $GLOBALS['EXEC_TIME'] => [
                'path' => $tempFolder,
                'name' => array_pop($array) .  '/',
            ]
        ];

        // Initializing:
        /* @var $fileProcessor ExtendedFileUtility */
        $this->fileProcessor = GeneralUtility::makeInstance(ExtendedFileUtility::class);
        $this->fileProcessor->setActionPermissions();
        $this->fileProcessor->dontCheckForUnique = 1;

        // Checking referer / executing:
        $refInfo = parse_url(GeneralUtility::getIndpEnv('HTTP_REFERER'));
        $httpHost = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');

        if ($httpHost != $refInfo['host'] && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
            $this->fileProcessor->writeLog(0, 2, 1, 'Referer host "%s" and server host "%s" did not match!', [$refInfo['host'], $httpHost]);
        } else {
            $this->fileProcessor->start($file);
            $this->fileProcessor->setExistingFilesConflictMode(DuplicationBehavior::cast(DuplicationBehavior::REPLACE));
            $newfile = $this->fileProcessor->func_upload($file['upload']['1']);
        }
        return $newfile;
    }

    /**
     * Returns LanguageService
     *
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
    
    /**
     * 
     * @return BackendUserAuthentication
     */
    protected function getBeUser()
    {
        return $GLOBALS['BE_USER'];
    }
}
