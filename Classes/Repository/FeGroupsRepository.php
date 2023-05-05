<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class FeGroupsRepository extends MainRepository
{
    protected string $table = 'fe_groups';

    /**
     * Return all uid's from 'fe_groups' for a static direct mail group.
     *
     * @param int $uid The uid of the direct_mail group
     *
     * @return array The resulting array of uid's
     */
    public function getStaticIdList(int $uid): array
    {
        $switchTable = 'fe_users';
        $queryBuilder = $this->getQueryBuilder($this->table);

        $res = $queryBuilder
        ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
        ->from('sys_dmail_group_mm', 'sys_dmail_group_mm')
        ->innerJoin(
            'sys_dmail_group_mm',
            'sys_dmail_group',
            'sys_dmail_group',
            $queryBuilder->expr()->eq(
                'sys_dmail_group_mm.uid_local',
                $queryBuilder->quoteIdentifier('sys_dmail_group.uid')
            )
        )
        ->innerJoin(
            'sys_dmail_group_mm',
            $this->table,
            $this->table,
            $queryBuilder->expr()->eq(
                'sys_dmail_group_mm.uid_foreign',
                $queryBuilder->quoteIdentifier($this->table . '.uid')
            )
        )
        ->innerJoin(
            $this->table,
            $switchTable,
            $switchTable,
            $queryBuilder->expr()->inSet(
                $switchTable . '.usergroup',
                $queryBuilder->quoteIdentifier($this->table . '.uid')
            )
        )
        ->andWhere(
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group_mm.uid_local',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_dmail_group_mm.tablenames',
                    $queryBuilder->createNamedParameter($this->table)
                ),
                $queryBuilder->expr()->neq(
                    $switchTable . '.email',
                    $queryBuilder->createNamedParameter('')
                ),
                $queryBuilder->expr()->eq(
                    'sys_dmail_group.deleted',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
                $queryBuilder->expr()->eq(
                    $switchTable . '.module_sys_dmail_newsletter',
                    1
                )
            )
        )
        ->orderBy($switchTable . '.uid')
        ->addOrderBy($switchTable . '.email')
        ->executeQuery();

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }

        // get the uid of the current fe_group
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->selectLiteral('DISTINCT ' . $this->table . '.uid')
        ->from($this->table, $this->table)
        ->from('sys_dmail_group', 'sys_dmail_group')
        ->leftJoin(
            'sys_dmail_group',
            'sys_dmail_group_mm',
            'sys_dmail_group_mm',
            $queryBuilder->expr()->eq(
                'sys_dmail_group_mm.uid_local',
                $queryBuilder->quoteIdentifier('sys_dmail_group.uid')
            )
        )
        ->andWhere(
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group.uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'fe_groups.uid',
                    $queryBuilder->quoteIdentifier('sys_dmail_group_mm.uid_foreign')
                ),
                $queryBuilder->expr()->eq(
                    'sys_dmail_group_mm.tablenames',
                    $queryBuilder->createNamedParameter($this->table)
                )
            )
        );

        $res = $queryBuilder->executeQuery();
        $groupId = $res->fetchOne();
        $subgroups = [];

        if ($groupId) {
            // recursively get all subgroups of this fe_group
            $subgroups = $this->getFEgroupSubgroups($groupId);
        }

        if (!empty($subgroups)) {
            $usergroupInList = null;
            foreach ($subgroups as $subgroup) {
                $usergroupInList .= (($usergroupInList == null) ? null : ' OR') . ' INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',' . (int)$subgroup . ',\') )';
            }
            $usergroupInList = '(' . $usergroupInList . ')';

            // fetch all fe_users from these subgroups
            $queryBuilder = $this->getQueryBuilder($this->table);

            $queryBuilder
            ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
            ->from($this->table, $this->table)
            ->innerJoin(
                $this->table,
                $switchTable,
                $switchTable
            )
            ->orWhere($usergroupInList)
            ->andWhere(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->neq(
                        $switchTable . '.email',
                        $queryBuilder->createNamedParameter('')
                    ),
                    // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
                    $queryBuilder->expr()->eq(
                        $switchTable . '.module_sys_dmail_newsletter',
                        1
                    )
                )
            )
            ->orderBy($switchTable . '.uid')
            ->addOrderBy($switchTable . '.email');

            $res = $queryBuilder->executeQuery();

            while ($row = $res->fetchAssociative()) {
                $outArr[] = $row['uid'];
            }
        }

        return $outArr;
    }

    /**
     * Get all subsgroups recursively.
     *
     * @param int $groupId Parent fe usergroup
     *
     * @return array The all id of fe_groups
     */
    public function getFEgroupSubgroups(int $groupId): array
    {
        // get all subgroups of this fe_group
        // fe_groups having this id in their subgroup field

        $mmTable = 'sys_dmail_group_mm';
        $groupTable = 'sys_dmail_group';

        $queryBuilder = $this->getQueryBuilder($this->table);

        $queryBuilder->selectLiteral('DISTINCT fe_groups.uid')
        ->from($this->table, $this->table)
        ->join(
            $this->table,
            $mmTable,
            $mmTable,
            $queryBuilder->expr()->eq(
                $mmTable . '.uid_local',
                $queryBuilder->quoteIdentifier($this->table . '.uid')
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
        ->andWhere('INSTR( CONCAT(\',\',fe_groups.subgroup,\',\'),\',' . $groupId . ',\' )');

        $res = $queryBuilder->executeQuery();

        $groupArr = [];
        while ($row = $res->fetchAssociative()) {
            $groupArr[] = $row['uid'];

            // add all subgroups recursively too
            $groupArr = array_merge($groupArr, $this->getFEgroupSubgroups($row['uid']));
        }

        return $groupArr;
    }

    /**
     * Return all uid's from fe_groups where the $pid is in $pidList.
     * If $cat is 0 or empty, then all entries (with pid $pid) is returned else only
     * entires which are subscribing to the categories of the group with uid $group_uid is returned.
     * The relation between the recipients in fe_groups and sys_dmail_categories is a true MM relation
     * (Must be correctly defined in TCA).
     *
     * @param array $pidArray The pidArray
     * @param int $groupUid The groupUid.
     * @param int $cat The number of relations from sys_dmail_group to sysmail_categories
     *
     * @return	array The resulting array of uid's
     */
    public function getIdList(array $pidArray, int $groupUid, int $cat): array
    {
        $switchTable = 'fe_users';
        $queryBuilder = $this->getQueryBuilder($this->table);

        // fe user group uid should be in list of fe users list of user groups
        //		$field = $switchTable.'.usergroup';
        //		$command = $this->table.'.uid';
        // This approach, using standard SQL, does not work,
        // even when fe_users.usergroup is defined as varchar(255) instead of tinyblob
        // $usergroupInList = ' AND ('.$field.' LIKE \'%,\'||'.$command.'||\',%\' OR '.$field.' LIKE '.$command.'||\',%\' OR '.$field.' LIKE \'%,\'||'.$command.' OR '.$field.'='.$command.')';
        // The following will work but INSTR and CONCAT are available only in mySQL

        if ($cat < 1) {
            $res = $queryBuilder
            ->selectLiteral('DISTINCT ' . $switchTable . '.uid', $switchTable . '.email')
            ->from($switchTable, $switchTable)
            ->from($this->table, $this->table)
            ->andWhere(
                $queryBuilder->expr()->and()
                ->add(
                    $queryBuilder->expr()->in(
                        'fe_groups.pid',
                        $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)
                    )
                )
                ->add('INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )')
                ->add(
                    $queryBuilder->expr()->neq(
                        $switchTable . '.email',
                        $queryBuilder->createNamedParameter('')
                    )
                )
                ->add(
                    $queryBuilder->expr()->eq(
                        'fe_users.module_sys_dmail_newsletter',
                        1
                    )
                )
            )
            ->orderBy($switchTable . '.uid')
            ->addOrderBy($switchTable . '.email')
            ->executeQuery();
        } else {
            $mmTable = $GLOBALS['TCA'][$switchTable]['columns']['module_sys_dmail_category']['config']['MM'];
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
                $queryBuilder->expr()->eq(
                    $switchTable . '.uid',
                    $queryBuilder->quoteIdentifier('mm_1.uid_local')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->and()
                    ->add(
                        $queryBuilder->expr()->in(
                            'fe_groups.pid',
                            $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)
                        )
                    )
                    ->add('INSTR( CONCAT(\',\',fe_users.usergroup,\',\'),CONCAT(\',\',fe_groups.uid ,\',\') )')
                    ->add(
                        $queryBuilder->expr()->eq(
                            'mm_1.uid_foreign',
                            $queryBuilder->quoteIdentifier('g_mm.uid_foreign')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            'sys_dmail_group.uid',
                            $queryBuilder->quoteIdentifier('g_mm.uid_local')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            'sys_dmail_group.uid',
                            $queryBuilder->createNamedParameter($groupUid, Connection::PARAM_INT)
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->neq(
                            $switchTable . '.email',
                            $queryBuilder->createNamedParameter('')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            'fe_users.module_sys_dmail_newsletter',
                            1
                        )
                    )
            )
            ->orderBy($switchTable . '.uid')
            ->addOrderBy($switchTable . '.email')
            ->executeQuery();
        }

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }
        return $outArr;
    }
}
