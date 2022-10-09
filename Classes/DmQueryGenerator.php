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

/*
 * https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.0/Deprecation-92080-DeprecatedQueryGeneratorAndQueryView.html
 * https://docs.typo3.org/c/typo3/cms-core/11.5/en-us/Changelog/8.4/Deprecation-77839-MoveTYPO3CMSCoreQueryGeneratorIntoEXTlowlevelAndDeprecateTheOldModule.html
 * https://api.typo3.org/11.5/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_database_1_1_query_generator.html
 * https://api.typo3.org/11.5/class_t_y_p_o3_1_1_c_m_s_1_1_lowlevel_1_1_database_1_1_query_generator.html
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lowlevel\Database\QueryGenerator;

/**
 * Used to generate queries for selecting users in the database
 *
 * @author		Kasper Skårhøj <kasper@typo3.com>
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class DmQueryGenerator extends QueryGenerator
{
    public $allowedTables = ['tt_address', 'fe_users'];

    /**
     * Make table select
     *
     * @param string $name
     * @param string $cur
     * @return string
     */
    //protected function mkTableSelect($name, $cur)
    public function mkTableSelect($name, $cur)
    {
        $out = [];
        $out[] = '<select class="form-select t3js-submit-change" name="' . $name . '">';
        $out[] = '<option value=""></option>';
        foreach ($GLOBALS['TCA'] as $tN => $value) {
            //if ($this->getBackendUserAuthentication()->check('tables_select', $tN)) {
            if ($this->getBackendUserAuthentication()->check('tables_select', $tN) && in_array($tN, $this->allowedTables)) {
                $label = $this->getLanguageService()->sL($GLOBALS['TCA'][$tN]['ctrl']['title']);
                if ($this->showFieldAndTableNames) {
                    $label .= ' [' . $tN . ']';
                }
                $out[] = '<option value="' . htmlspecialchars($tN) . '"' . ($tN == $cur ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
            }
        }
        $out[] = '</select>';
        return implode(LF, $out);
    }

    /**
     * Query marker
     *
     * @return string
     */
    public function queryMakerDM()
    {
        $output = '';

        // Query Maker:
        $this->init('queryConfig', $this->settings['queryTable'] ?? '', '', $this->settings);
        if ($this->formName) {
            $this->setFormName($this->formName);
        }
        $tmpCode = $this->makeSelectorTableDM($this->settings);
        $output .= '<div id="query"></div><h2>Make query</h2><div>' . $tmpCode . '</div>';
        $mQ = $this->settings['search_query_makeQuery'];
        // Make form elements:
        if ($this->table && is_array($GLOBALS['TCA'][$this->table])) {
            if ($mQ) {
                // Show query
                $this->enablePrefix = true;
                $queryString = $this->getQuery($this->queryConfig);
                $selectQueryString = $this->getSelectQuery($queryString);
                $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($this->table);

                $isConnectionMysql = strpos($connection->getServerVersion(), 'MySQL') === 0;
                $fullQueryString = '';
                try {
                    $fullQueryString = $selectQueryString;
                    $dataRows = $connection->executeQuery($selectQueryString)->fetchAllAssociative();
                    $output .= '<h2>SQL query</h2><div><code>' . htmlspecialchars($fullQueryString) . '</code></div>';
                    $cPR = $this->getQueryResultCode($mQ, $dataRows, $this->table);
                    $output .= '<h2>' . ($cPR['header'] ?? '') . '</h2><div>' . $cPR['content'] . '</div>';
                } catch (DBALException $e) {
                    $output .= '<h2>SQL query</h2><div><code>' . htmlspecialchars($fullQueryString) . '</code></div>';
                    $out = '<p><strong>Error: <span class="text-danger">'
                        . htmlspecialchars($e->getMessage())
                        . '</span></strong></p>';
                    $output .= '<h2>SQL error</h2><div>' . $out . '</div>';
                }
            }
        }
        return '<div class="database-query-builder">' . $output . '</div>';
    }

    /**
     * Make selector table
     *
     * @param array $modSettings
     * @param string $enableList
     * @return string
     */
    protected function makeSelectorTableDM($modSettings, $enableList = 'table,fields,query,group,order,limit')
    {
        $out = [];
        $enableArr = explode(',', $enableList);
        $userTsConfig = $this->getBackendUserAuthentication()->getTSConfig();

        // Make output
        if (in_array('table', $enableArr) && !($userTsConfig['mod.']['dbint.']['disableSelectATable'] ?? false)) {
            $out[] = '<div class="form-group">';
            $out[] =     '<label for="SET[queryTable]">Select a table:</label>';
            $out[] =     '<div class="row row-cols-auto">';
            $out[] =         '<div class="col">';
            $out[] =             $this->mkTableSelect('SET[queryTable]', $this->table);
            $out[] =         '</div>';
            $out[] =     '</div>';
            $out[] = '</div>';
        }
        if ($this->table) {
            // Init fields:
            $this->setAndCleanUpExternalLists('queryFields', $modSettings['queryFields'] ?? '', 'uid,' . $this->getLabelCol());
            $this->setAndCleanUpExternalLists('queryGroup', $modSettings['queryGroup'] ?? '');
            $this->setAndCleanUpExternalLists('queryOrder', ($modSettings['queryOrder'] ?? '') . ',' . ($modSettings['queryOrder2'] ?? ''));
            // Limit:
            $this->extFieldLists['queryLimit'] = $modSettings['queryLimit'] ?? '';
            if (!$this->extFieldLists['queryLimit']) {
                $this->extFieldLists['queryLimit'] = 100;
            }
            $parts = GeneralUtility::intExplode(',', $this->extFieldLists['queryLimit']);
            $limitBegin = 0;
            $limitLength = (int)($this->extFieldLists['queryLimit'] ?? 0);
            if ($parts[1] ?? null) {
                $limitBegin = (int)$parts[0];
                $limitLength = (int)$parts[1];
            }
            $this->extFieldLists['queryLimit'] = implode(',', array_slice($parts, 0, 2));
            // Insert Descending parts
            if ($this->extFieldLists['queryOrder']) {
                $descParts = explode(',', ($modSettings['queryOrderDesc'] ?? '') . ',' . ($modSettings['queryOrder2Desc'] ?? ''));
                $orderParts = explode(',', $this->extFieldLists['queryOrder']);
                $reList = [];
                foreach ($orderParts as $kk => $vv) {
                    $reList[] = $vv . ($descParts[$kk] ? ' DESC' : '');
                }
                $this->extFieldLists['queryOrder_SQL'] = implode(',', $reList);
            }
            // Query Generator:
            $this->procesData(($modSettings['queryConfig'] ?? false) ? unserialize($modSettings['queryConfig'] ?? '', ['allowed_classes' => false]) : []);
            $this->queryConfig = $this->cleanUpQueryConfig($this->queryConfig);
            $this->enableQueryParts = (bool)($modSettings['search_query_smallparts'] ?? false);
            $codeArr = $this->getFormElements();
            $queryCode = $this->printCodeArray($codeArr);

            if (in_array('query', $enableArr) && !($userTsConfig['mod.']['dbint.']['disableMakeQuery'] ?? false)) {
                $out[] = '<div class="form-group">';
                $out[] = '	<label>Make Query:</label>';
                $out[] =    $queryCode;
                $out[] = '</div>';
            }

            if (in_array('order', $enableArr) && !($userTsConfig['mod.']['dbint.']['disableOrderBy'] ?? false)) {
                $orderByArr = explode(',', $this->extFieldLists['queryOrder']);
                $orderBy = [];
                $orderBy[] = '<div class="row row-cols-auto align-items-center">';
                $orderBy[] =     '<div class="col">';
                $orderBy[] =         $this->mkTypeSelect('SET[queryOrder]', $orderByArr[0], '');
                $orderBy[] =     '</div>';
                $orderBy[] =     '<div class="col mt-2">';
                $orderBy[] =         '<div class="form-check">';
                $orderBy[] =              BackendUtility::getFuncCheck(0, 'SET[queryOrderDesc]', $modSettings['queryOrderDesc'] ?? '', '', '', 'id="checkQueryOrderDesc"');
                $orderBy[] =              '<label class="form-check-label" for="checkQueryOrderDesc">Descending</label>';
                $orderBy[] =         '</div>';
                $orderBy[] =     '</div>';
                $orderBy[] = '</div>';

                if ($orderByArr[0]) {
                    $orderBy[] = '<div class="row row-cols-auto align-items-center mt-2">';
                    $orderBy[] =     '<div class="col">';
                    $orderBy[] =         '<div class="input-group">';
                    $orderBy[] =             $this->mkTypeSelect('SET[queryOrder2]', $orderByArr[1] ?? '', '');
                    $orderBy[] =         '</div>';
                    $orderBy[] =     '</div>';
                    $orderBy[] =     '<div class="col mt-2">';
                    $orderBy[] =         '<div class="form-check">';
                    $orderBy[] =             BackendUtility::getFuncCheck(0, 'SET[queryOrder2Desc]', $modSettings['queryOrder2Desc'] ?? false, '', '', 'id="checkQueryOrder2Desc"');
                    $orderBy[] =             '<label class="form-check-label" for="checkQueryOrder2Desc">Descending</label>';
                    $orderBy[] =         '</div>';
                    $orderBy[] =     '</div>';
                    $orderBy[] = '</div>';
                }
                $out[] = '<div class="form-group">';
                $out[] = '	<label>Order By:</label>';
                $out[] =     implode(LF, $orderBy);
                $out[] = '</div>';
            }
            if (in_array('limit', $enableArr) && !($userTsConfig['mod.']['dbint.']['disableLimit'] ?? false)) {
                $limit = [];
                $limit[] = '<div class="input-group">';
                $limit[] = '	<span class="input-group-btn">';
                $limit[] = $this->updateIcon();
                $limit[] = '	</span>';
                $limit[] = '	<input type="text" class="form-control" value="' . htmlspecialchars($this->extFieldLists['queryLimit']) . '" name="SET[queryLimit]" id="queryLimit">';
                $limit[] = '</div>';

                $prevLimit = $limitBegin - $limitLength < 0 ? 0 : $limitBegin - $limitLength;
                $prevButton = '';
                $nextButton = '';

                if ($limitBegin) {
                    $prevButton = '<input type="button" class="btn btn-default" value="previous ' . htmlspecialchars((string)$limitLength) . '" data-value="' . htmlspecialchars($prevLimit . ',' . $limitLength) . '">';
                }
                if (!$limitLength) {
                    $limitLength = 100;
                }

                $nextLimit = $limitBegin + $limitLength;
                if ($nextLimit < 0) {
                    $nextLimit = 0;
                }
                if ($nextLimit) {
                    $nextButton = '<input type="button" class="btn btn-default" value="next ' . htmlspecialchars((string)$limitLength) . '" data-value="' . htmlspecialchars($nextLimit . ',' . $limitLength) . '">';
                }

                $out[] = '<div class="form-group">';
                $out[] = '	<label>Limit:</label>';
                $out[] = '	<div class="row row-cols-auto">';
                $out[] = '   <div class="col">';
                $out[] =        implode(LF, $limit);
                $out[] = '   </div>';
                $out[] = '   <div class="col">';
                $out[] = '		<div class="btn-group t3js-limit-submit">';
                $out[] =            $prevButton;
                $out[] =            $nextButton;
                $out[] = '		</div>';
                $out[] = '   </div>';
                $out[] = '   <div class="col">';
                $out[] = '		<div class="btn-group t3js-limit-submit">';
                $out[] = '			<input type="button" class="btn btn-default" data-value="10" value="10">';
                $out[] = '			<input type="button" class="btn btn-default" data-value="20" value="20">';
                $out[] = '			<input type="button" class="btn btn-default" data-value="50" value="50">';
                $out[] = '			<input type="button" class="btn btn-default" data-value="100" value="100">';
                $out[] = '		</div>';
                $out[] = '   </div>';
                $out[] = '	</div>';
                $out[] = '</div>';
            }
        }
        return implode(LF, $out);
    }
}
