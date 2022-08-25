<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use DirectMailTeam\DirectMail\MailSelect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TempRepository extends MainRepository {
    
    /**
     * Get recipient DB record given on the ID
     *
     * @param array $listArr List of recipient IDs
     * @param string $table Table name
     * @param string $fields Field to be selected
     *
     * @return array recipients' data
     */
    public function fetchRecordsListValues(array $listArr, $table, $fields = 'uid,name,email')
    {
        $outListArr = [];
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
                } 
                else {
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
    public function getIdList($table, $pidList, $groupUid, $cat)
    {
        $addWhere = '';
        
        $switchTable = $table == 'fe_groups' ? 'fe_users' : $table;
        
        $pidArray = GeneralUtility::intExplode(',', $pidList);
        
        /** @var \TYPO3\CMS\Core\Database\Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
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
            }
            else {
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
        }
        else {
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
            }
            else {
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
        $outArr = [];
        while ($row = $res->fetch()) {
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
    public function getStaticIdList($table, $uid)
    {
        $switchTable = $table == 'fe_groups' ? 'fe_users' : $table;
        
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
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
        }
        else {
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
        
        $outArr = [];
        
        while ($row = $res->fetch()) {
            $outArr[] = $row['uid'];
        }
        
        if ($table == 'fe_groups') {
            // get the uid of the current fe_group
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
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
                        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
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
                                    $outArr[] = $row['uid'];
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
    public function getSpecialQueryIdList(MailSelect &$queryGenerator, $table, array $group): array
    {
        $outArr = [];
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
    public function getMailGroups($list, array $parsedGroups, $permsClause)
    {
        $groupIdList = GeneralUtility::intExplode(',', $list);
        $groups = [];
        
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
                ' AND ' . $permsClause)
        ->execute();
                
        while ($row = $res->fetch()) {
            if ($row['type'] == 4) {
                // Other mail group...
                if (!in_array($row['uid'], $parsedGroups)) {
                    $parsedGroups[] = $row['uid'];
                    $groups = array_merge($groups, $this->getMailGroups($row['mail_groups'], $parsedGroups, $permsClause));
                }
            }
            else {
                // Normal mail group, just add to list
                $groups[] = $row['uid'];
            }
        }
        return $groups;
    }
}