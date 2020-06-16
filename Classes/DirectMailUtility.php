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

use DirectMailTeam\DirectMail\Utility\FlashMessageRenderer;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Static class.
 * Functions in this class are used by more than one modules.
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage	tx_directmail
 */
class DirectMailUtility
{

    /**
     * Get recipient DB record given on the ID
     *
     * @param array $listArr List of recipient IDs
     * @param string $table Table name
     * @param string $fields Field to be selected
     *
     * @return array recipients' data
     */
    public static function fetchRecordsListValues(array $listArr, $table, $fields='uid,name,email')
    {
        $outListArr = array();
        if (is_array($listArr) && count($listArr)) {
            $idlist = implode(',', $listArr);

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

            $fieldArray = GeneralUtility::trimExplode(',', $fields);

            // handle selecting multiple fields
            foreach ($fieldArray as $i => $field) {
                if ($i) {
                    $queryBuilder->addSelect($field);
                } else {
                    $queryBuilder->select($field);
                }
            }

            $res = $queryBuilder->from($table)
                ->where(
                    $queryBuilder->expr()->in(
                        'uid',
                        $queryBuilder->createNamedParameter(
                            GeneralUtility::intExplode(',', $idlist),
                            Connection::PARAM_INT_ARRAY
                        )
                    )
                )
                ->execute();

            while ($row = $res->fetch()) {
                $outListArr[$row['uid']] = $row;
            }
        }
        return $outListArr;
    }

    /**
     * Get the ID of page in a tree
     *
     * @param int $id Page ID
     * @param string $perms_clause Select query clause
     * @return array the page ID, recursively
     */
    public static function getRecursiveSelect($id, $perms_clause)
    {
        // Finding tree and offer setting of values recursively.
        $tree = GeneralUtility::makeInstance(PageTreeView::class);
        $tree->init('AND ' . $perms_clause);
        $tree->makeHTML = 0;
        $tree->setRecs = 0;
        $getLevels = 10000;
        $tree->getTree($id, $getLevels, '');

        return $tree->ids;
    }

    /**
     * Remove double record in an array
     *
     * @param array $plainlist Email of the recipient
     *
     * @return array Cleaned array
     */
    public static function cleanPlainList(array $plainlist)
    {
        /**
         * $plainlist is a multidimensional array.
         * this method only remove if a value has the same array
         * $plainlist = array(
         * 		0 => array(
         * 				name => '',
         * 				email => '',
         * 			),
         * 		1 => array(
         * 				name => '',
         * 				email => '',
         * 			),
         *
         * );
         */
        $plainlist = array_map('unserialize', array_unique(array_map('serialize', $plainlist)));

        return $plainlist;
    }

    /**
     * Return all uid's from $table where the $pid is in $pidList.
     * If $cat is 0 or empty, then all entries (with pid $pid) is returned else only
     * entires which are subscribing to the categories of the group with uid $group_uid is returned.
     * The relation between the recipients in $table and sys_dmail_categories is a true MM relation
     * (Must be correctly defined in TCA).
     *
     * @param string $table The table to select from
     * @param string $pidList The pidList
     * @param int $groupUid The groupUid.
     * @param int $cat The number of relations from sys_dmail_group to sysmail_categories
     *
     * @return	array The resulting array of uid's
     */
    public static function getIdList($table, $pidList, $groupUid, $cat)
    {
        $addWhere = '';

        if ($table == 'fe_groups') {
            $switchTable = 'fe_users';
        } else {
            $switchTable = $table;
        }

		$pidArray = GeneralUtility::intExplode(',', $pidList);

		/** @var \TYPO3\CMS\Core\Database\Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);
        $queryBuilder = $connection->createQueryBuilder();

        if ($switchTable == 'fe_users') {
            //$addWhere = ' AND fe_users.module_sys_dmail_newsletter = 1';
            $addWhere =  $queryBuilder->expr()->eq(
                'fe_users.module_sys_dmail_newsletter',
                1
            );
        }

        // fe user group uid should be in list of fe users list of user groups
        //		$field = $switchTable.'.usergroup';
        //		$command = $table.'.uid';
        // This approach, using standard SQL, does not work,
        // even when fe_users.usergroup is defined as varchar(255) instead of tinyblob
        // $usergroupInList = ' AND ('.$field.' LIKE \'%,\'||'.$command.'||\',%\' OR '.$field.' LIKE '.$command.'||\',%\' OR '.$field.' LIKE \'%,\'||'.$command.' OR '.$field.'='.$command.')';
        // The following will work but INSTR and CONCAT are available only in mySQL



        $mmTable = $GLOBALS['TCA'][$switchTable]['columns']['module_sys_dmail_category']['config']['MM'];
        $cat = intval($cat);
        if ($cat < 1) {
            if ($table == 'fe_groups') {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from($switchTable, $switchTable)
                    ->from($table, $table)
                    ->andWhere(
                        $queryBuilder->expr()->andX()
                            ->add($queryBuilder->expr()->in('fe_groups.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add('INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )')
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            } else {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from($switchTable)
                    ->andWhere(
                        $queryBuilder->expr()->andX()
                            ->add($queryBuilder->expr()->in($switchTable . '.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            }
        } else {
            if ($table == 'fe_groups') {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from('sys_dmail_group', 'sys_dmail_group')
                    ->from('sys_dmail_group_category_mm', 'g_mm')
                    ->from('fe_groups', 'fe_groups')
                    ->from($mmTable, 'mm_1')
                    ->leftJoin(
                        'mm_1',
                        $switchTable,
                        $switchTable,
                        $queryBuilder->expr()->eq($switchTable .'.uid', $queryBuilder->quoteIdentifier('mm_1.uid_local'))
                    )
                    ->andWhere(
                        $queryBuilder->expr()->andX()
                            ->add($queryBuilder->expr()->in('fe_groups.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add('INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )')
                            ->add($queryBuilder->expr()->eq('mm_1.uid_foreign', $queryBuilder->quoteIdentifier('g_mm.uid_foreign')))
                            ->add($queryBuilder->expr()->eq('sys_dmail_group.uid', $queryBuilder->quoteIdentifier('g_mm.uid_local')))
                            ->add($queryBuilder->expr()->eq('sys_dmail_group.uid', $queryBuilder->createNamedParameter($groupUid, \PDO::PARAM_INT)))
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            } else {
                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from('sys_dmail_group', 'sys_dmail_group')
                    ->from('sys_dmail_group_category_mm', 'g_mm')
                    ->from($mmTable, 'mm_1')
                    ->leftJoin(
                        'mm_1',
                        $table,
                        $table,
                        $queryBuilder->expr()->eq($table .'.uid', $queryBuilder->quoteIdentifier('mm_1.uid_local'))
                    )
                    ->andWhere(
                        $queryBuilder->expr()->andX()
                            ->add($queryBuilder->expr()->in($switchTable . '.pid', $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)))
                            ->add($queryBuilder->expr()->eq('mm_1.uid_foreign', $queryBuilder->quoteIdentifier('g_mm.uid_foreign')))
                            ->add($queryBuilder->expr()->eq('sys_dmail_group.uid', $queryBuilder->quoteIdentifier('g_mm.uid_local')))
                            ->add($queryBuilder->expr()->eq('sys_dmail_group.uid', $queryBuilder->createNamedParameter($groupUid, \PDO::PARAM_INT)))
                            ->add(
                                $queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter(''))
                            )
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();
            }
        }
        $outArr = array();
        while (($row = $res->fetch())) {
            $outArr[] = $row['uid'];
        }
        return $outArr;
    }

    /**
     * Return all uid's from $table for a static direct mail group.
     *
     * @param string $table The table to select from
     * @param int $uid The uid of the direct_mail group
     *
     * @return array The resulting array of uid's
     */
    public static function getStaticIdList($table, $uid)
    {
        if ($table == 'fe_groups') {
            $switchTable = 'fe_users';
        } else {
            $switchTable = $table;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);
        $queryBuilder = $connection->createQueryBuilder();

        // fe user group uid should be in list of fe users list of user groups
        // $field = $switchTable.'.usergroup';
        // $command = $table.'.uid';

        // See comment above
        // $usergroupInList = ' AND ('.$field.' LIKE \'%,\'||'.$command.'||\',%\' OR '.$field.' LIKE '.$command.'||\',%\' OR '.$field.' LIKE \'%,\'||'.$command.' OR '.$field.'='.$command.')';

        // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
        if ($switchTable == 'fe_users') {
            $addWhere =  $queryBuilder->expr()->eq(
                $switchTable . '.module_sys_dmail_newsletter',
                1
            );
        }

        if ($table == 'fe_groups') {
            $res = $queryBuilder
                ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                ->from('sys_dmail_group_mm', 'sys_dmail_group_mm')
                ->innerJoin(
                    'sys_dmail_group_mm',
                    'sys_dmail_group',
                    'sys_dmail_group',
                    $queryBuilder->expr()->eq('sys_dmail_group_mm.uid_local', $queryBuilder->quoteIdentifier('sys_dmail_group.uid'))
                )
                ->innerJoin(
                    'sys_dmail_group_mm',
                    $table,
                    $table,
                    $queryBuilder->expr()->eq('sys_dmail_group_mm.uid_foreign', $queryBuilder->quoteIdentifier($table . '.uid'))
                )
                ->innerJoin(
                    $table,
                    $switchTable,
                    $switchTable,
                    $queryBuilder->expr()->inSet($switchTable.'.usergroup', $queryBuilder->quoteIdentifier($table.'.uid'))
                )
                ->andWhere(
                    $queryBuilder->expr()->andX()
                        ->add($queryBuilder->expr()->eq('sys_dmail_group_mm.uid_local', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('sys_dmail_group_mm.tablenames', $queryBuilder->createNamedParameter($table)))
                        ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('sys_dmail_group.deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)))
                        ->add($addWhere)
                )
                ->orderBy($switchTable . '.uid')
                ->addOrderBy($switchTable . '.email')
                ->execute();
        } else {
            $res = $queryBuilder
                ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                ->from('sys_dmail_group_mm', 'sys_dmail_group_mm')
                ->innerJoin(
                    'sys_dmail_group_mm',
                    'sys_dmail_group',
                    'sys_dmail_group',
                    $queryBuilder->expr()->eq('sys_dmail_group_mm.uid_local', $queryBuilder->quoteIdentifier('sys_dmail_group.uid'))
                )
                ->innerJoin(
                    'sys_dmail_group_mm',
                    $switchTable,
                    $switchTable,
                    $queryBuilder->expr()->eq('sys_dmail_group_mm.uid_foreign', $queryBuilder->quoteIdentifier($switchTable . '.uid'))
                )
                ->andWhere(
                    $queryBuilder->expr()->andX()
                        ->add($queryBuilder->expr()->eq('sys_dmail_group_mm.uid_local', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('sys_dmail_group_mm.tablenames', $queryBuilder->createNamedParameter($switchTable)))
                        ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                        ->add($queryBuilder->expr()->eq('sys_dmail_group.deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)))
                        ->add($addWhere)
                )
                ->orderBy($switchTable . '.uid')
                ->addOrderBy($switchTable . '.email')
                ->execute();
        }

        $outArr = array();

        while (($row = $res->fetch())) {
            $outArr[] = $row['uid'];
        }

        if ($table == 'fe_groups') {
            // get the uid of the current fe_group
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable($table);
            $queryBuilder = $connection->createQueryBuilder();

            $res = $queryBuilder
                ->selectLiteral('DISTINCT ' . $table . '.uid')
                ->from($table, $table)
                ->from('sys_dmail_group', 'sys_dmail_group')
                ->leftJoin(
                    'sys_dmail_group',
                    'sys_dmail_group_mm',
                    'sys_dmail_group_mm',
                    $queryBuilder->expr()->eq('sys_dmail_group_mm.uid_local', $queryBuilder->quoteIdentifier('sys_dmail_group.uid'))
                )
                ->andWhere(
                    $queryBuilder->expr()->andX()
                        ->add($queryBuilder->expr()->eq('sys_dmail_group.uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)))
                        ->add($queryBuilder->expr()->eq('fe_groups.uid', $queryBuilder->quoteIdentifier('sys_dmail_group_mm.uid_foreign')))
                        ->add($queryBuilder->expr()->eq('sys_dmail_group_mm.tablenames', $queryBuilder->createNamedParameter($table)))
                )
                ->execute();

            list($groupId) = $res->fetchAll();

            // recursively get all subgroups of this fe_group
            $subgroups = self::getFEgroupSubgroups($groupId);

            if (!empty($subgroups)) {
                $usergroupInList = null;
                foreach ($subgroups as $subgroup) {
                    $usergroupInList .= (($usergroupInList == null) ? null : ' OR') . ' INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',' . intval($subgroup) . ',\') )';
                }
                $usergroupInList = '(' . $usergroupInList . ')';

                // fetch all fe_users from these subgroups
                $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable($table);
                $queryBuilder = $connection->createQueryBuilder();
                // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
                if ($switchTable == 'fe_users') {
                    $addWhere =  $queryBuilder->expr()->eq(
                        $switchTable . '.module_sys_dmail_newsletter',
                        1
                    );
                }

                $res = $queryBuilder
                    ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
                    ->from($table, $table)
                    ->innerJoin(
                        $table,
                        $switchTable,
                        $switchTable
                    )
                    ->orWhere($usergroupInList)
                    ->andWhere(
                        $queryBuilder->expr()->andX()
                            ->add($queryBuilder->expr()->neq($switchTable . '.email', $queryBuilder->createNamedParameter('')))
                            ->add($addWhere)
                    )
                    ->orderBy($switchTable . '.uid')
                    ->addOrderBy($switchTable . '.email')
                    ->execute();

                while ($row = $res->fetch()) {
                    $outArr[]=$row['uid'];
                }
            }
        }

        return $outArr;
    }

    /**
     * Construct the array of uid's from $table selected
     * by special query of mail group of such type
     *
     * @param MailSelect $queryGenerator The query generator object
     * @param string $table The table to select from
     * @param array $group The direct_mail group record
     *
     * @return array The resulting query.
     */
    public static function getSpecialQueryIdList(MailSelect &$queryGenerator, $table, array $group): array
    {
        $outArr = array();
        if ($group['query']) {
            $queryGenerator->init('dmail_queryConfig', $table);
            $queryGenerator->queryConfig = $queryGenerator->cleanUpQueryConfig(unserialize($group['query']));

            $queryGenerator->extFieldLists['queryFields'] = 'uid';
            $select = $queryGenerator->getSelectQuery();
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
            $recipients = $connection->executeQuery($select)->fetchAll();

            foreach ($recipients as $recipient) {
                $outArr[] = $recipient['uid'];
            }
        }
        return $outArr;
    }

    /**
     * Get all group IDs
     *
     * @param string $list Comma-separated ID
     * @param array $parsedGroups Groups ID, which is already parsed
     * @param string $perms_clause Permission clause (Where)
     *
     * @return array the new Group IDs
     */
    public static function getMailGroups($list, array $parsedGroups, $perms_clause)
    {
        $groupIdList = GeneralUtility::intExplode(',', $list);
        $groups = array();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_group');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $res = $queryBuilder->select('sys_dmail_group.*')
            ->from('sys_dmail_group', 'sys_dmail_group')
            ->leftJoin(
                'sys_dmail_group',
                'pages',
                'pages',
                $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('sys_dmail_group.pid'))
            )
            ->add('where', 'sys_dmail_group.uid IN (' . implode(',', $groupIdList) . ')' .
                ' AND ' . $perms_clause)
            ->execute();

        while (($row=$res->fetch())) {
            if ($row['type'] == 4) {
                // Other mail group...
                if (!in_array($row['uid'], $parsedGroups)) {
                    $parsedGroups[] = $row['uid'];
                    $groups = array_merge($groups, self::getMailGroups($row['mail_groups'], $parsedGroups, $perms_clause));
                }
            } else {
                // Normal mail group, just add to list
                $groups[] = $row['uid'];
            }
        }
        return $groups;
    }

    /**
     * Parse CSV lines into array form
     *
     * @param array $lines CSV lines
     * @param string $fieldList List of the fields
     *
     * @return array parsed CSV values
     */
    public static function rearrangeCsvValues(array $lines, $fieldList)
    {
        $out = array();
        if (is_array($lines) && count($lines)>0) {
            // Analyse if first line is fieldnames.
            // Required is it that every value is either
            // 1) found in the list fieldsList in this class,
            // 2) the value is empty (value omitted then) or
            // 3) the field starts with "user_".
            // In addition fields may be prepended with "[code]".
            // This is used if the incoming value is true in which case '+[value]'
            // adds that number to the field value (accummulation) and '=[value]'
            // overrides any existing value in the field
            $first = $lines[0];
            $fieldListArr = explode(',', $fieldList);
            if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']) {
                $fieldListArr = array_merge($fieldListArr, explode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']));
            }
            $fieldName = 1;
            $fieldOrder = array();

            foreach ($first as $v) {
                list($fName, $fConf) = preg_split('|[\[\]]|', $v);
                $fName = trim($fName);
                $fConf = trim($fConf);
                $fieldOrder[] = array($fName,$fConf);
                if ($fName && substr($fName, 0, 5) != 'user_' && !in_array($fName, $fieldListArr)) {
                    $fieldName = 0;
                    break;
                }
            }
            // If not field list, then:
            if (!$fieldName) {
                $fieldOrder = array(array('name'),array('email'));
            }
            // Re-map values
            reset($lines);
            if ($fieldName) {
                // Advance pointer if the first line was field names
                next($lines);
            }

            $c = 0;
            foreach ($lines as $data) {
                // Must be a line with content.
                // This sorts out entries with one key which is empty. Those are empty lines.
                if (count($data)>1 || $data[0]) {
                    // Traverse fieldOrder and map values over
                    foreach ($fieldOrder as $kk => $fN) {
                        // print "Checking $kk::".t3lib_div::view_array($fN).'<br />';
                        if ($fN[0]) {
                            if ($fN[1]) {
                                // If is true
                                if (trim($data[$kk])) {
                                    if (substr($fN[1], 0, 1) == '=') {
                                        $out[$c][$fN[0]] = trim(substr($fN[1], 1));
                                    } elseif (substr($fN[1], 0, 1) == '+') {
                                        $out[$c][$fN[0]] += substr($fN[1], 1);
                                    }
                                }
                            } else {
                                $out[$c][$fN[0]] = trim($data[$kk]);
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
     * Rearrange emails array into a 2-dimensional array
     *
     * @param array $plainMails Recipient emails
     *
     * @return array a 2-dimensional array consisting email and name
     */
    public static function rearrangePlainMails(array $plainMails)
    {
        $out = array();
        if (is_array($plainMails)) {
            $c = 0;
            foreach ($plainMails as $v) {
                $out[$c]['email'] = trim($v);
                $out[$c]['name'] = '';
                $c++;
            }
        }
        return $out;
    }

    /**
     * Compile the categories enables for this $row of this $table.
     *
     * @param string $table Table name
     * @param array $row Row from table
     * @param int $sysLanguageUid User language ID
     *
     * @return array the categories in an array with the cat id as keys
     */
    public static function makeCategories($table, array $row, $sysLanguageUid)
    {
        $categories = array();

        $mmField = 'module_sys_dmail_category';
        if ($table == 'sys_dmail_group') {
            $mmField = 'select_categories';
        }

        $pageTsConfig = BackendUtility::getTCEFORM_TSconfig($table, $row);
        if (is_array($pageTsConfig[$mmField])) {
            $pidList = $pageTsConfig[$mmField]['PAGE_TSCONFIG_IDLIST'];
            if ($pidList) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_category');
                $res = $queryBuilder->select('*')
                    ->from('sys_dmail_category')
                    ->add('where', 'sys_dmail_category.pid IN (' . str_replace(',', "','", $queryBuilder->createNamedParameter($pidList)) . ')' .
                        ' AND l18n_parent=0')
                    ->execute();
                while (($rowCat = $res->fetch())) {
                    if (($localizedRowCat = self::getRecordOverlay('sys_dmail_category', $rowCat, $sysLanguageUid, ''))) {
                        $categories[$localizedRowCat['uid']] = htmlspecialchars($localizedRowCat['category']);
                    }
                }
            }
        }
        return $categories;
    }

    /**
     * Import from t3lib_page in order to create backend version
     * Creates language-overlay for records in general
     * (where translation is found in records from the same table)
     *
     * @param string $table Table name
     * @param array $row Record to overlay. Must contain uid, pid and languageField
     * @param int $sys_language_content Language ID of the content
     * @param string $OLmode Overlay mode. If "hideNonTranslated" then records without translation will not be returned un-translated but unset (and return value is false)
     *
     * @return mixed Returns the input record, possibly overlaid with a translation. But if $OLmode is "hideNonTranslated" then it will return false if no translation is found.
     */
    public static function getRecordOverlay($table, array $row, $sys_language_content, $OLmode = '')
    {
        if ($row['uid']>0 && $row['pid']>0) {
            if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
                if (!$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable']) {
                    // Will try to overlay a record only
                    // if the sys_language_content value is larger that zero.
                    if ($sys_language_content > 0) {
                        // Must be default language or [All], otherwise no overlaying:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]<=0) {
                            // Select overlay record:
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                            $res = $queryBuilder->select('*')
                                ->from($table)
                                ->add('where', 'pid=' . intval($row['pid']) .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . intval($sys_language_content) .
                                    ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . intval($row['uid']))
                                ->setMaxResults(1)/* LIMIT 1*/
                                ->execute();

                            $olrow = $res->fetch();

                            // Merge record content by traversing all fields:
                            if (is_array($olrow)) {
                                foreach ($row as $fN => $fV) {
                                    if ($fN!='uid' && $fN!='pid' && isset($olrow[$fN])) {
                                        if ($GLOBALS['TCA'][$table]['l10n_mode'][$fN]!='exclude' && ($GLOBALS['TCA'][$table]['l10n_mode'][$fN]!='mergeIfNotBlank' || strcmp(trim($olrow[$fN]), ''))) {
                                            $row[$fN] = $olrow[$fN];
                                        }
                                    }
                                }
                            } elseif ($OLmode === 'hideNonTranslated' && $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] == 0) {
                                // Unset, if non-translated records should be hidden.
                                // ONLY done if the source record really is default language and not [All] in which case it is allowed.
                                unset($row);
                            }

                            // Otherwise, check if sys_language_content is different from the value of the record
                            // that means a japanese site might try to display french content.
                        } elseif ($sys_language_content!=$row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
                            unset($row);
                        }
                    } else {
                        // When default language is displayed,
                        // we never want to return a record carrying another language!:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]>0) {
                            unset($row);
                        }
                    }
                }
            }
        }

        return $row;
    }

    /**
     * Print out an array as a table
     *
     * @param array $tableLines Content of the cell
     * @param array $cellParams The additional cell parameter
     * @param bool $header If set, the first arrray is the header of the table
     * @param array $cellcmd If set, the content is HTML escaped
     * @param string $tableParams The additional table parameter
     *
     * @return string HTML table
     */
    public static function formatTable(array $tableLines, array $cellParams, $header, array $cellcmd = array(), $tableParams = 'class="table table-striped table-hover"')
    {
        reset($tableLines);
        $cols = empty($tableLines) ? 0 : count(current($tableLines));

        reset($tableLines);
        $lines = array();
        $first = $header?1:0;

        foreach ($tableLines as $r) {
            $rowA = array();
            for ($k=0; $k<$cols; $k++) {
                $v = $r[$k];
                $v = strlen($v) ? ($cellcmd[$k]?$v:htmlspecialchars($v)) : '&nbsp;';
                if ($first) {
                    $rowA[] = '<td>' . $v . '</td>';
                } else {
                    $rowA[] = '<td' . ($cellParams[$k]?' ' . $cellParams[$k]:'') . '>' . $v . '</td>';
                }
            }
            $lines[] = '<tr class="' . ($first ? 't3-row-header' : 'db_list_normal') . '">' . implode('', $rowA) . '</tr>';
            $first = 0;
        }
        $table = '<table ' . $tableParams . '>' . implode('', $lines) . '</table>';
        return $table;
    }

    /**
     * Get the base URL
     *
     * @param int $pageId
     * @param bool $getFullUrl
     * @param string $htmlParams
     * @param string $plainParams
     * @return array|string Array returns if getFullUrl is true
     * @throws SiteNotFoundException
     * @throws InvalidRouteArgumentsException
     */
    public static function getUrlBase(int $pageId, bool $getFullUrl = false, string $htmlParams = '', string $plainParams = '')
    {
        if ($pageId > 0) {
            $pageInfo = BackendUtility::getRecord('pages', $pageId, '*');
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            if (!empty($siteFinder->getAllSites())) {
                $site = $siteFinder->getSiteByPageId($pageId);
                $base = $site->getBase();

                $baseUrl = sprintf('%s://%s', $base->getScheme(), $base->getHost());
                $htmlUrl = '';
                $plainTextUrl = '';

                if ($getFullUrl === true) {
                    $route = $site->getRouter()->generateUri($pageId, ['_language' => $pageInfo['sys_language_uid']]);
                    $htmlUrl = $route;
                    $plainTextUrl = $route;
                    // Parse htmlUrl as string \TYPO3\CMS\Core\Http\Uri::parseUri()
                    if ($htmlParams !== '') {
                        $htmlUrl .= '?' . $htmlParams;
                    } else {
                        $htmlUrl .= '';
                    }
                    // Parse plainTextUrl as string \TYPO3\CMS\Core\Http\Uri::parseUri()
                    if ($plainParams !== '') {
                        $plainTextUrl .= '?' . $plainParams;
                    } else {
                        $plainTextUrl .= '';
                    }
                }

                return $htmlUrl !== '' ? [ 'baseUrl' => $baseUrl, 'htmlUrl' => $htmlUrl, 'plainTextUrl' => $plainTextUrl] : $baseUrl;
            } else {
                return ''; // No site found in root line of pageId
            }
        } else {
            return ''; // No valid pageId
        }
    }

    /**
     * Get locallang label
     *
     * @param string $name Locallang label index
     *
     * @return string The label
     */
    public static function fName($name)
    {
        return stripslashes($GLOBALS['LANG']->sL(BackendUtility::getItemLabel('sys_dmail', $name)));
    }

    /**
     * Parsing csv-formated text to an array
     *
     * @param string $str String in csv-format
     * @param string $sep Separator
     *
     * @return array Parsed csv in an array
     */
    public static function getCsvValues($str, $sep=',')
    {
        $fh = tmpfile();
        fwrite($fh, trim($str));
        fseek($fh, 0);
        $lines = array();
        if ($sep == 'tab') {
            $sep = "\t";
        }
        while (($data = fgetcsv($fh, 1000, $sep))) {
            $lines[] = $data;
        }

        fclose($fh);
        return $lines;
    }


    /**
     * Show DB record in HTML table format
     *
     * @param array $listArr All DB records to be formated
     * @param string $table Table name
     * @param int $pageId PageID, to which the link points to
     * @param bool|int $editLinkFlag If set, edit link is showed
     * @param int $sys_dmail_uid ID of the sys_dmail object
     *
     * @return	string		list of record in HTML format
     */
    public static function getRecordList(array $listArr, $table, $pageId, $editLinkFlag = 1, $sys_dmail_uid = 0)
    {
        $count = 0;
        $lines = [];
        $out = '';

        // init iconFactory
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $isAllowedDisplayTable = $GLOBALS['BE_USER']->check('tables_select', $table);
        $isAllowedEditTable = $GLOBALS['BE_USER']->check('tables_modify', $table);
        $notAllowedPlaceholder = $GLOBALS['LANG']->getLL('mailgroup_table_disallowed_placeholder');

        if (is_array($listArr)) {
            $count = count($listArr);
            $returnUrl = GeneralUtility::getIndpEnv('REQUEST_URI');
            foreach ($listArr as $row) {
                $tableIcon = '';
                $editLink = '';
                if ($row['uid']) {
                    $tableIcon = sprintf('<td>%s</td>', $iconFactory->getIconForRecord($table, []));
                    if ($editLinkFlag && $isAllowedEditTable) {
                        $urlParameters = [
                            'edit' => [
                                $table => [
                                    $row['uid'] => 'edit'
                                ]
                            ],
                            'returnUrl' => $returnUrl
                        ];

                        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                        $editLink = sprintf(
                            '<td><a class="t3-link" href="%s" title="%s">%s</a></td>',
                            $uriBuilder->buildUriFromRoute('record_edit', $urlParameters),
                            $GLOBALS['LANG']->getLL('dmail_edit'),
                            $iconFactory->getIcon('actions-open', Icon::SIZE_SMALL)
                        );
                    }
                }

                if ($isAllowedDisplayTable) {
                    $exampleData = [
                        'email' => '<td nowrap> ' . htmlspecialchars($row['email']) . ' </td>',
                        'name' => '<td nowrap> ' . htmlspecialchars($row['name']) . ' </td>'
                    ];
                } else {
                    $exampleData = [
                        'email' => '<td nowrap>' . $notAllowedPlaceholder . '</td>',
                        'name' => ''
                    ];
                }

                $lines[] = sprintf(
                    '<tr class="db_list_normal">%s%s<td class="nowrap">%s</td><td class="nowrap">%s</td></tr>',
                    $tableIcon,
                    $editLink,
                    $exampleData['email'],
                    $exampleData['name']
                );
            }
        }
        if (count($lines)) {
            $out = $GLOBALS['LANG']->getLL('dmail_number_records') . '<strong> ' . $count . '</strong><br />';
            $out .= '<table class="table table-striped table-hover">' . implode(LF, $lines) . '</table>';
        }

        return $out;
    }

    /**
     * Get all subsgroups recursively.
     *
     * @param int $groupId Parent fe usergroup
     *
     * @return array The all id of fe_groups
     */
    public static function getFEgroupSubgroups($groupId)
    {
        // get all subgroups of this fe_group
        // fe_groups having this id in their subgroup field

        $table = 'fe_groups';
        $mmTable = 'sys_dmail_group_mm';
        $groupTable = 'sys_dmail_group';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        $res = $queryBuilder->selectLiteral('DISTINCT fe_groups.uid')
            ->from($table, $table)
            ->join(
                $table,
                $mmTable,
                $mmTable,
                $queryBuilder->expr()->eq(
                    $mmTable . '.uid_local',
                    $queryBuilder->quoteIdentifier($table . '.uid')
                )
            )
            ->join(
                $mmTable,
                $groupTable,
                $groupTable,
                $queryBuilder->expr()->eq(
                    $mmTable . '.uid_local',
                    $queryBuilder->quoteIdentifier($groupTable . '.uid')
                )
            )
            ->andWhere('INSTR( CONCAT(\',\',fe_groups.subgroup,\',\'),\',' . intval($groupId) . ',\' )')
            ->execute();
        $groupArr = array();

        while (($row = $res->fetch())) {
            $groupArr[] = $row['uid'];

            // add all subgroups recursively too
            $groupArr = array_merge($groupArr, self::getFEgroupSubgroups($row['uid']));
        }

        return $groupArr;
    }

    /**
     * Creates a directmail entry in th DB.
     * Used only for internal pages
     *
     * @param int $pageUid The page ID
     * @param array $parameters The dmail Parameter
     *
     * @param int $sysLanguageUid
     * @return int|bool new record uid or FALSE if failed
     */
    public static function createDirectMailRecordFromPage($pageUid, array $parameters, $sysLanguageUid = 0)
    {
        $result = false;

        $newRecord = array(
            'type'                    => 0,
            'pid'                    => $parameters['pid'],
            'from_email'            => $parameters['from_email'],
            'from_name'                => $parameters['from_name'],
            'replyto_email'            => $parameters['replyto_email'],
            'replyto_name'            => $parameters['replyto_name'],
            'return_path'            => $parameters['return_path'],
            'priority'                => $parameters['priority'],
            'use_rdct'                => (!empty($parameters['use_rdct']) ? $parameters['use_rdct']:0), /*$parameters['use_rdct'],*/
            'long_link_mode'        => (!empty($parameters['long_link_mode']) ? $parameters['long_link_mode']:0),//$parameters['long_link_mode'],
            'organisation'            => $parameters['organisation'],
            'authcode_fieldList'    => $parameters['authcode_fieldList'],
            'sendOptions'            => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url'    => self::getUrlBase((int)$pageUid),
            'sys_language_uid' => (int)$sysLanguageUid,
            'attachment' => '',
            'mailContent' => ''
        );

        if ($newRecord['sys_language_uid'] > 0) {
            $langParam = self::getLanguageParam($newRecord['sys_language_uid'], $parameters);
            $parameters['plainParams'] .= $langParam;
            $parameters['HTMLParams'] .= $langParam;
        }


        // If params set, set default values:
        $paramsToOverride = array('sendOptions', 'includeMedia', 'flowedFormat', 'HTMLParams', 'plainParams');
        foreach ($paramsToOverride as $param) {
            if (isset($parameters[$param])) {
                $newRecord[$param] = $parameters[$param];
            }
        }
        if (isset($parameters['direct_mail_encoding'])) {
            $newRecord['encoding'] = $parameters['direct_mail_encoding'];
        }

        $pageRecord = BackendUtility::getRecord('pages', $pageUid);
        // Fetch page title from pages_language_overlay
        if ($newRecord['sys_language_uid'] > 0) {
            if (strpos(VersionNumberUtility::getNumericTypo3Version(), '9') === 0) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
                $queryBuilder
                    ->select('title')
                    ->from('pages')
                    ->where($queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, \PDO::PARAM_INT)));
            } else {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages_language_overlay');
                $queryBuilder->select('title')
                    ->from('pages_language_overlay')
                    ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, \PDO::PARAM_INT)));
            }

            $pageRecordOverlay = $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($newRecord['sys_language_uid'], \PDO::PARAM_INT)
                )
            )->execute()->fetch();

            if (is_array($pageRecordOverlay)) {
                $pageRecord['title'] = $pageRecordOverlay['title'];
            }
        }

        if ($pageRecord['doktype']) {
            $newRecord['subject'] = $pageRecord['title'];
            $newRecord['page']    = $pageRecord['uid'];
            $newRecord['charset'] = self::getCharacterSetOfPage($pageRecord['uid']);
        }

        // save to database
        if ($newRecord['page'] && $newRecord['sendOptions']) {
            $tcemainData = array(
                'sys_dmail' => array(
                    'NEW' => $newRecord
                )
            );

            /* @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
            $tce = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
            $tce->stripslashes_values = 0;
            $tce->start($tcemainData, array());
            $tce->process_datamap();
            $result = $tce->substNEWwithIDs['NEW'];
        } elseif (!$newRecord['sendOptions']) {
            $result = false;
        }
        return $result;
    }

    /**
     * Get language param
     *
     * @param string $sysLanguageUid
     * @param array $params direct_mail settings
     * @return string
     */
    public static function getLanguageParam($sysLanguageUid, array $params)
    {
        if (isset($params['langParams.'][$sysLanguageUid])) {
            $param = $params['langParams.'][$sysLanguageUid];

        // fallback: L == sys_language_uid
        } else {
            $param = '&L=' . $sysLanguageUid;
        }

        return $param;
    }


    /**
     * Creates a directmail entry in th DB.
     * Used only for external pages
     *
     * @param string $subject Subject of the newsletter
     * @param string $externalUrlHtml Link to the HTML version
     * @param string $externalUrlPlain Linkt to the text version
     * @param array $parameters Additional newsletter parameters
     *
     * @return	int/bool Error or warning message produced during the process
     */
    public static function createDirectMailRecordFromExternalURL($subject, $externalUrlHtml, $externalUrlPlain, array $parameters)
    {
        $result = false;

        $newRecord = array(
            'type'                    => 1,
            'pid'                    => $parameters['pid'],
            'subject'                => $subject,
            'from_email'            => $parameters['from_email'],
            'from_name'                => $parameters['from_name'],
            'replyto_email'            => $parameters['replyto_email'],
            'replyto_name'            => $parameters['replyto_name'],
            'return_path'            => $parameters['return_path'],
            'priority'                => $parameters['priority'],
            'use_rdct'                => (!empty($parameters['use_rdct']) ? $parameters['use_rdct']:0),
            'long_link_mode'        => $parameters['long_link_mode'],
            'organisation'            => $parameters['organisation'],
            'authcode_fieldList'    => $parameters['authcode_fieldList'],
            'sendOptions'            => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url'    => self::getUrlBase((int)$parameters['page'])
        );


        // If params set, set default values:
        $paramsToOverride = array('sendOptions', 'includeMedia', 'flowedFormat', 'HTMLParams', 'plainParams');
        foreach ($paramsToOverride as $param) {
            if (isset($parameters[$param])) {
                $newRecord[$param] = $parameters[$param];
            }
        }
        if (isset($parameters['direct_mail_encoding'])) {
            $newRecord['encoding'] = $parameters['direct_mail_encoding'];
        }

        $urlParts = @parse_url($externalUrlPlain);
        // No plain text url
        if (!$externalUrlPlain || $urlParts === false || !$urlParts['host']) {
            $newRecord['plainParams'] = '';
            $newRecord['sendOptions']&=254;
        } else {
            $newRecord['plainParams'] = $externalUrlPlain;
        }

        // No html url
        $urlParts = @parse_url($externalUrlHtml);
        if (!$externalUrlHtml || $urlParts === false || !$urlParts['host']) {
            $newRecord['sendOptions']&=253;
        } else {
            $newRecord['HTMLParams'] = $externalUrlHtml;
        }

        // save to database
        if ($newRecord['pid'] && $newRecord['sendOptions']) {
            $tcemainData = array(
                'sys_dmail' => array(
                    'NEW' => $newRecord
                )
            );

            /* @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
            $tce = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
            $tce->stripslashes_values = 0;
            $tce->start($tcemainData, array());
            $tce->process_datamap();
            $result = $tce->substNEWwithIDs['NEW'];
        } elseif (!$newRecord['sendOptions']) {
            $result = false;
        }
        return $result;
    }


    /**
     * Fetch content of a page (only internal and external page)
     *
     * @param array $row Directmail DB record
     * @param array $params Any default parameters (usually the ones from pageTSconfig)
     * @param bool $returnArray Return error or warning message as array instead of string
     *
     * @return string Error or warning message during fetching the content
     */
    public static function fetchUrlContentsForDirectMailRecord(array $row, array $params, $returnArray = false)
    {
        $theOutput = '';
        $errorMsg = array();
        $warningMsg = array();
        $urls = self::getFullUrlsForDirectMailRecord($row);
        $plainTextUrl = $urls['plainTextUrl'];
        $htmlUrl = $urls['htmlUrl'];
        $urlBase = $urls['baseUrl'];

        // Make sure long_link_rdct_url is consistent with baseUrl.
        $row['long_link_rdct_url'] = $urlBase;

        // Compile the mail
        /* @var $htmlmail Dmailer */
        $htmlmail = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Dmailer');
        if ($params['enable_jump_url']) {
            $htmlmail->jumperURL_prefix = $urlBase .
                '&mid=###SYS_MAIL_ID###' .
                (intval($params['jumpurl_tracking_privacy']) ? '' : '&rid=###SYS_TABLE_NAME###_###USER_uid###') .
                '&aC=###SYS_AUTHCODE###' .
                '&jumpurl=';
            $htmlmail->jumperURL_useId = 1;
        }
        if ($params['enable_mailto_jump_url']) {
            $htmlmail->jumperURL_useMailto = 1;
        }

        $htmlmail->start();
        $htmlmail->charset = $row['charset'];
        $htmlmail->http_username = $params['http_username'];
        $htmlmail->http_password = $params['http_password'];
        $htmlmail->simulateUsergroup = $params['simulate_usergroup'];
        $htmlmail->includeMedia = $row['includeMedia'];

        if ($plainTextUrl) {
            $mailContent = GeneralUtility::getURL(self::addUserPass($plainTextUrl, $params), 0, array('User-Agent: Direct Mail'));
            $htmlmail->addPlain($mailContent);
            if (!$mailContent || !$htmlmail->theParts['plain']['content']) {
                $errorMsg[] = $GLOBALS['LANG']->getLL('dmail_no_plain_content');
            } elseif (!strstr($htmlmail->theParts['plain']['content'], '<!--DMAILER_SECTION_BOUNDARY')) {
                $warningMsg[] = $GLOBALS['LANG']->getLL('dmail_no_plain_boundaries');
            }
        }

        // fetch the HTML url
        if ($htmlUrl) {

            // Username and password is added in htmlmail object
            $success = $htmlmail->addHTML(self::addUserPass($htmlUrl, $params));
            // If type = 1, we have an external page.
            if ($row['type'] == 1) {
                // Try to auto-detect the charset of the message
                $matches = array();
                $res = preg_match('/<meta[\s]+http-equiv="Content-Type"[\s]+content="text\/html;[\s]+charset=([^"]+)"/m', $htmlmail->theParts['html_content'], $matches);
                if ($res == 1) {
                    $htmlmail->charset = $matches[1];
                } elseif (isset($params['direct_mail_charset'])) {
                    $htmlmail->charset = $params['direct_mail_charset'];
                } else {
                    $htmlmail->charset = 'iso-8859-1';
                }
            }
            if ($htmlmail->extractFramesInfo()) {
                $errorMsg[] = $GLOBALS['LANG']->getLL('dmail_frames_not allowed');
            } elseif (!$success || !$htmlmail->theParts['html']['content']) {
                $errorMsg[] = $GLOBALS['LANG']->getLL('dmail_no_html_content');
            } elseif (!strstr($htmlmail->theParts['html']['content'], '<!--DMAILER_SECTION_BOUNDARY')) {
                $warningMsg[] = $GLOBALS['LANG']->getLL('dmail_no_html_boundaries');
            }
        }

        if (!count($errorMsg)) {
            // Update the record:
            $htmlmail->theParts['messageid'] = $htmlmail->messageid;
            $mailContent = base64_encode(serialize($htmlmail->theParts));

            $updateData = array(
                'issent'             => 0,
                'charset'            => $htmlmail->charset,
                'mailContent'        => $mailContent,
                'renderedSize'       => strlen($mailContent),
                'long_link_rdct_url' => $urlBase
            );

            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('sys_dmail');
            $connection->update(
                'sys_dmail', // table
                $updateData, // value array
                [ 'uid' => intval($row['uid']) ] // where
            );

            if (count($warningMsg)) {
                foreach ($warningMsg as $warning) {
                    /* @var $flashMessage FlashMessage */
                    $flashMessage = GeneralUtility::makeInstance(
                        'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                        $warning,
                        $GLOBALS['LANG']->getLL('dmail_warning'),
                        FlashMessage::WARNING
                    );
                    $theOutput .= GeneralUtility::makeInstance(FlashMessageRenderer::class)->render($flashMessage);
                }
            }
        } else {
            foreach ($errorMsg as $error) {
                /* @var $flashMessage FlashMessage */
                $flashMessage = GeneralUtility::makeInstance(
                    'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $error,
                    $GLOBALS['LANG']->getLL('dmail_error'),
                    FlashMessage::ERROR
                );
                $theOutput .= GeneralUtility::makeInstance(FlashMessageRenderer::class)->render($flashMessage);
            }
        }
        if ($returnArray) {
            return [
                'errors' => $errorMsg,
                'warnings' => $warningMsg
            ];
        } else {
            return $theOutput;
        }
    }


    /**
     * Add username and password for a password secured page
     * username and password are configured in the configuration module
     *
     * @param string $url The URL
     * @param array $params Parameters from pageTS
     *
     * @return string The new URL with username and password
     */
    protected static function addUserPass($url, array $params)
    {
        $user = $params['http_username'];
        $pass = $params['http_password'];
        $matches = array();
        if ($user && $pass && preg_match('/^(?:http)s?:\/\//', $url, $matches)) {
            $url = $matches[0] . $user . ':' . $pass . '@' . substr($url, strlen($matches[0]));
        }
        if ($params['simulate_usergroup'] && MathUtility::canBeInterpretedAsInteger($params['simulate_usergroup'])) {
            $url = $url . '&dmail_fe_group=' . (int)$params['simulate_usergroup'] . '&access_token=' . self::createAndGetAccessToken();
        }
        return $url;
    }

    /**
     * Create an access token and save it in the Registry
     */
    public static function createAndGetAccessToken(): string
    {
        /* @var \TYPO3\CMS\Core\Registry $registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $accessToken = GeneralUtility::makeInstance(Random::class)->generateRandomHexString(32);
        $registry->set('tx_directmail', 'accessToken', $accessToken);

        return $accessToken;
    }

    /**
     * Create an access token and save it in the Registry
     *
     * @param string $accessToken The access token to validate
     *
     * @return string
     */
    public static function validateAndRemoveAccessToken($accessToken)
    {
        /* @var \TYPO3\CMS\Core\Registry $registry */
        $registry = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
        $registeredAccessToken = $registry->get('tx_directmail', 'accessToken');
        if (!empty($registeredAccessToken) && $registeredAccessToken === $accessToken) {
            $registry->remove('tx_directmail', 'accessToken');
            return true;
        } else {
            $registry->remove('tx_directmail', 'accessToken');
            return false;
        }
    }

    /**
     * Set up URL variables for this $row.
     *
     * @param array $row Directmail DB record
     *
     * @return array $result Url_plain and url_html in an array
     */
    public static function getFullUrlsForDirectMailRecord(array $row)
    {
        $cObj = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        // Finding the domain to use
        $result = [
            'baseUrl' => $cObj->typolink_URL([
                'parameter' => (int)$row['page'],
                'forceAbsoluteUrl' => true,
                'linkAccessRestrictedPages' => true
            ]),
            'htmlUrl' => '',
            'plainTextUrl' => ''
        ];

        // Finding the url to fetch content from
        switch ((string)$row['type']) {
            case 1:
                $result['htmlUrl'] = $row['HTMLParams'];
                $result['plainTextUrl'] = $row['plainParams'];
                break;
            default:
                $params = substr($row['HTMLParams'], 0, 1) == '&' ? substr($row['HTMLParams'], 1) : $row['HTMLParams'];
                $result['htmlUrl'] = $cObj->typolink_URL([
                    'parameter' => 't3://page?uid=' . (int)$row['page'] . '&' . $params,
                    'forceAbsoluteUrl' => true,
                    'linkAccessRestrictedPages' => true
                ]);
                $params = substr($row['plainParams'], 0, 1) == '&' ? substr($row['plainParams'], 1) : $row['plainParams'];
                $result['plainTextUrl'] = $cObj->typolink_URL([
                    'parameter' => 't3://page?uid=' . (int)$row['page'] . '&' . $params,
                    'forceAbsoluteUrl' => true,
                    'linkAccessRestrictedPages' => true
                ]);
        }

        // plain
        if ($result['plainTextUrl']) {
            if (!($row['sendOptions'] & 1)) {
                $result['plainTextUrl'] = '';
            } else {
                $urlParts = @parse_url($result['plainTextUrl']);
                if (!$urlParts['scheme']) {
                    $result['plainTextUrl'] = 'http://' . $result['plainTextUrl'];
                }
            }
        }

        // html
        if ($result['htmlUrl']) {
            if (!($row['sendOptions'] & 2)) {
                $result['htmlUrl'] = '';
            } else {
                $urlParts = @parse_url($result['htmlUrl']);
                if (!$urlParts['scheme']) {
                    $result['htmlUrl'] = 'http://' . $result['htmlUrl'];
                }
            }
        }

        return $result;
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @throws ServiceUnavailableException
     * @throws ImmediateResponseException
     */
    public static function initializeTsfe(int $pageId, int $language = 0, bool $useCache = true): void
    {
        // resetting, a TSFE instance with data from a different page Id could be set already
        unset($GLOBALS['TSFE']);

        $cacheId = $pageId . '|' . $language;

        if (!isset($tsfeCache[$cacheId]) || !$useCache) {
            $GLOBALS['TSFE'] = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0);

            // for certain situations we need to trick TSFE into granting us
            // access to the page in any case to make getPageAndRootline() work
            // see http://forge.typo3.org/issues/42122
            $pageRecord = BackendUtility::getRecord('pages', $pageId);
            $groupListBackup = $GLOBALS['TSFE']->gr_list;
            $GLOBALS['TSFE']->gr_list = $pageRecord['fe_group'];

            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
            $GLOBALS['TSFE']->getPageAndRootlineWithDomain($pageId);


            // restore gr_list
            $GLOBALS['TSFE']->gr_list = $groupListBackup;

            $GLOBALS['TSFE']->forceTemplateParsing = true;
            $GLOBALS['TSFE']->initFEuser();
            $GLOBALS['TSFE']->initUserGroups();

            $GLOBALS['TSFE']->no_cache = true;
            $GLOBALS['TSFE']->initTemplate();
            $GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
            $GLOBALS['TSFE']->no_cache = false;
            $GLOBALS['TSFE']->getConfigArray();

            $GLOBALS['TSFE']->settingLanguage();
            $GLOBALS['TSFE']->newCObj();
            $GLOBALS['TSFE']->absRefPrefix = ($GLOBALS['TSFE']->config['config']['absRefPrefix'] ? trim($GLOBALS['TSFE']->config['config']['absRefPrefix']) : '');

            if ($useCache) {
                $tsfeCache[$cacheId] = $GLOBALS['TSFE'];
            }
        }

        if ($useCache) {
            $GLOBALS['TSFE'] = $tsfeCache[$cacheId];
        }
    }

    /**
     * Get the charset of a page
     *
     * @throws ImmediateResponseException
     * @throws ServiceUnavailableException
     */
    public static function getCharacterSetOfPage(int $pageId): string
    {
        // init a fake TSFE object
        self::initializeTsfe($pageId);

        $characterSet = 'utf-8';

        if ($GLOBALS['TSFE']->tmpl->setup['config.']['metaCharset']) {
            $characterSet = $GLOBALS['TSFE']->tmpl->setup['config.']['metaCharset'];
        } elseif ($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']) {
            $characterSet = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
        }

        // destroy it :)
        unset($GLOBALS['TSFE']);

        return mb_strtolower($characterSet);
    }

    /**
     * Wrapper for the old t3lib_div::intInRange.
     * Forces the integer $theInt into the boundaries of $min and $max.
     * If the $theInt is 'FALSE' then the $zeroValue is applied.
     */
    public static function intInRangeWrapper(int $theInt, int $min, int $max = 2000000000, int $zeroValue = 0): int
    {
        return MathUtility::forceIntegerInRange($theInt, $min, $max, $zeroValue);
    }


    /**
     * Updates Page TSconfig for a page with $id
     * The function seems to take $pageTS as an array with properties
     * and compare the values with those that already exists for the "object string",
     * $TSconfPrefix, for the page, then sets those values which were not present.
     * $impParams can be supplied as already known Page TSconfig, otherwise it's calculated.
     *
     * THIS DOES NOT CHECK ANY PERMISSIONS. SHOULD IT?
     * More documentation is needed.
     *
     * @param int $id Page id
     * @param array $pageTs Page TS array to write
     * @param string $tsConfPrefix Prefix for object paths
     * @param array|string $impParams [Description needed.]
     *
     * @return	void
     *
     * @see implodeTSParams(), getPagesTSconfig()
     */
    public static function updatePagesTSconfig($id, array $pageTs, $tsConfPrefix, $impParams = '')
    {
        $id = intval($id);
        if (is_array($pageTs) && $id > 0) {
            if (!is_array($impParams)) {
                $impParams = DirectMailUtility::implodeTSParams(BackendUtility::getPagesTSconfig($id));
            }
            $set = array();
            foreach ($pageTs as $f => $v) {
                $f = $tsConfPrefix . $f;
                if ((!isset($impParams[$f]) && trim($v)) || strcmp(trim($impParams[$f]), trim($v))) {
                    $set[$f] = trim($v);
                }
            }
            if (count($set)) {
                // Get page record and TS config lines
                $pRec = BackendUtility::getRecord('pages', $id);
                $tsLines = explode(LF, $pRec['TSconfig']);
                $tsLines = array_reverse($tsLines);
                // Reset the set of changes.
                foreach ($set as $f => $v) {
                    $inserted = 0;
                    foreach ($tsLines as $ki => $kv) {
                        if (substr($kv, 0, strlen($f) + 1) == $f . '=') {
                            $tsLines[$ki] = $f . '=' . $v;
                            $inserted = 1;
                            break;
                        }
                    }
                    if (!$inserted) {
                        $tsLines = array_reverse($tsLines);
                        $tsLines[] = $f . '=' . $v;
                        $tsLines = array_reverse($tsLines);
                    }
                }
                $tsLines = array_reverse($tsLines);

                // store those changes
                $tsConf = implode(LF, $tsLines);

                $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
                $connection = $connectionPool->getConnectionForTable('pages');
                $connection->update(
                    'pages', // table
                    [ 'TSconfig' => $tsConf ],  // value array
                    [ 'uid' => intval($id) ] // where
                );
            }
        }
    }

    /**
     * Implodes a multi dimensional TypoScript array, $p,
     * into a one-dimensional array (return value)
     *
     * @param array $p TypoScript structure
     * @param string $k Prefix string
     *
     * @return array Imploded TypoScript objectstring/values
     */
    public static function implodeTSParams(array $p, $k = '')
    {
        $implodeParams = array();
        if (is_array($p)) {
            foreach ($p as $kb => $val) {
                if (is_array($val)) {
                    $implodeParams = array_merge($implodeParams, self::implodeTSParams($val, $k . $kb));
                } else {
                    $implodeParams[$k . $kb] = $val;
                }
            }
        }
        return $implodeParams;
    }

    /**
     * Takes a clear-text message body for a plain text email, finds all 'http://' links and if they are longer than 76 chars they are converted to a shorter URL with a hash parameter. The real parameter is stored in the database and the hash-parameter/URL will be redirected to the real parameter when the link is clicked.
     * This function is about preserving long links in messages.
     *
     * @param string $message Message content
     * @param string $urlmode URL mode; "76" or "all
     * @param string $index_script_url URL of index script (see makeRedirectUrl())
     * @return string Processed message content
     * @see makeRedirectUrl()
     * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8. Use mailer API instead
     */
    public static function substUrlsInPlainText($message, $urlmode = '76', $index_script_url = '')
    {
        switch ((string)$urlmode) {
            case '':
                $lengthLimit = false;
                break;
            case 'all':
                $lengthLimit = 0;
                break;
            case '76':

            default:
                $lengthLimit = (int)$urlmode;
        }
        if ($lengthLimit === false) {
            // No processing
            $messageSubstituted = $message;
        } else {
            $messageSubstituted = preg_replace_callback(
                '/(http|https):\\/\\/.+(?=[\\]\\.\\?]*([\\! \'"()<>]+|$))/iU',
                function (array $matches) use ($lengthLimit, $index_script_url) {
                    $redirects = GeneralUtility::makeInstance(\FoT3\Rdct\Redirects::class);
                    return $redirects->makeRedirectUrl($matches[0], $lengthLimit, $index_script_url);
                },
                $message
            );
        }
        return $messageSubstituted;
    }
}
