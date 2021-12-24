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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Container class for auxilliary functions of tx_directmail
 *
 * @author		Kasper Sk�rh�j <kasperYYYY>@typo3.com>
 * @author		Thorsten Kahler <thorsten.kahler@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class Container
{
    public $boundaryStartWrap = '<!--DMAILER_SECTION_BOUNDARY_ | -->';
    public $boundaryEnd = '<!--DMAILER_SECTION_BOUNDARY_END-->';

    /**
     * @var TypoScriptFrontendController
     */
    public $cObj;

    /**
     * This function wraps HTML comments around the content.
     * The comments contain the uids of assigned direct mail categories.
     * It is called as "USER_FUNC" from TS.
     *
     * @param    string $content Incoming HTML code which will be wrapped
     * @param    array|null $conf Pointer to the conf array (TS)
     *
     * @return    string        content of the email with dmail boundaries
     */
    public function insert_dMailer_boundaries($content, $conf = [])
    {
        if (isset($conf['useParentCObj']) && $conf['useParentCObj']) {
            $this->cObj = $conf['parentObj']->cObj;
        }

        // this check could probably be moved to TS
        if ($GLOBALS['TSFE']->config['config']['insertDmailerBoundaries']) {
            if ($content != '') {
                // setting the default
                $categoryList = '';
                if (intval($this->cObj->data['module_sys_dmail_category']) >= 1) {
                    // if content type "RECORDS" we have to strip off
                    // boundaries from indcluded records
                    if ($this->cObj->data['CType'] == 'shortcut') {
                        $content = $this->stripInnerBoundaries($content);
                    }

                    // get categories of tt_content element
                    $foreignTable = 'sys_dmail_category';
                    $select = "$foreignTable.uid";
                    $localTableUidList = intval($this->cObj->data['uid']);
                    $mmTable = 'sys_dmail_ttcontent_category_mm';
                    $whereClause = '';
                    $orderBy = $foreignTable . '.uid';

                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($foreignTable);
                    $statement = $queryBuilder
                        ->select($select)
                        ->from($foreignTable)
                        ->from($mmTable)
                        ->where(
                            $queryBuilder->expr()->eq(
                                $foreignTable . '.uid',
                                $mmTable . '.uid_foreign'
                            )
                        )
                        ->andWhere(
                            $queryBuilder->expr()->in(
                                $mmTable . '.uid_local',
                                $localTableUidList
                            )
                        )
                        ->orderBy($orderBy)
                        ->execute();


                    while (($row = $statement->fetch())) {
                        $categoryList .= $row['uid'] . ',';
                    }
                    $categoryList = rtrim($categoryList, ',');
                }
                // wrap boundaries around content
                $content = $this->cObj->wrap($categoryList, $this->boundaryStartWrap) . $content . $this->boundaryEnd;
            }
        }
        return $content;
    }

    /**
     * Remove boundaries from TYPO3 content
     *
     * @param string $content the content with boundaries in comment
     *
     * @return string the content without boundaries
     */
    public function stripInnerBoundaries($content)
    {
        // only dummy code at the moment
        $searchString = $this->cObj->wrap('[\d,]*', $this->boundaryStartWrap);
        $content = preg_replace('/' . $searchString . '/', '', $content);
        $content = preg_replace('/' . $this->boundaryEnd . '/', '', $content);
        return $content;
    }

    /**
     * Breaking lines into fixed length lines, using GeneralUtility::breakLinesForEmail()
     *
     * @param string $content The string to break
     * @param array $conf Configuration options: linebreak, charWidth; stdWrap enabled
     *
     * @return string Processed string
     * @see GeneralUtility::breakLinesForEmail()
     */
    public function breakLines($content, array $conf)
    {
        $linebreak = $GLOBALS['TSFE']->cObj->stdWrap(($conf['linebreak'] ? $conf['linebreak'] : chr(32) . LF), $conf['linebreak.']);
        $charWidth = $GLOBALS['TSFE']->cObj->stdWrap(($conf['charWidth'] ? intval($conf['charWidth']) : 76), $conf['charWidth.']);

        return MailUtility::breakLinesForEmail($content, $linebreak, $charWidth);
    }

    /**
     * Inserting boundaries for each sitemap point.
     *
     * @param string $content The content string
     * @param array $conf The TS conf
     *
     * @return string $content: the string wrapped with boundaries
     */
    public function insertSitemapBoundaries($content, array $conf)
    {
        $uid = $this->cObj->data['uid'];
        $content = '';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_ttcontent_category_mm');
        $categories = $queryBuilder
            ->select('*')
            ->from('sys_dmail_ttcontent_category_mm')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    (int) $uid
                )
            )
            ->orderBy('sorting')
            ->execute()
            ->fetchAll();


        if (count($categories) > 0) {
            $categoryList = [];
            foreach ($categories as $category) {
                $categoryList[] = $category['uid_foreign'];
            }
            $content = '<!--DMAILER_SECTION_BOUNDARY_' . implode(',', $categoryList) . '-->|<!--DMAILER_SECTION_BOUNDARY_END-->';
        }

        return $content;
    }
}
