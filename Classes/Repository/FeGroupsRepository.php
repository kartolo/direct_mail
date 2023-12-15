<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class FeGroupsRepository extends MainRepository
{
    protected string $table                        = 'fe_groups';
    protected string $tableFeUsers                 = 'fe_users';
    protected string $tableSysDmailGroup           = 'sys_dmail_group';
    protected string $tableSysDmailGroupMm         = 'sys_dmail_group_mm';
    protected string $tableSysDmailGroupCategoryMm = 'sys_dmail_group_category_mm';

    /**
     * Return all uid's from 'fe_groups' for a static direct mail group.
     *
     * @param int $uid The uid of the direct_mail group
     *
     * @return array The resulting array of uid's
     */
    public function getStaticIdList(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        $res = $queryBuilder
        ->selectLiteral('DISTINCT ' . $this->tableFeUsers . '.uid', $this->tableFeUsers . '.email')
        ->from($this->tableSysDmailGroupMm, $this->tableSysDmailGroupMm)
        ->innerJoin(
            $this->tableSysDmailGroupMm,
            $this->tableSysDmailGroup,
            $this->tableSysDmailGroup,
            $queryBuilder->expr()->eq(
                $this->tableSysDmailGroupMm . '.uid_local',
                $queryBuilder->quoteIdentifier($this->tableSysDmailGroup . '.uid')
            )
        )
        ->innerJoin(
            $this->tableSysDmailGroupMm,
            $this->table,
            $this->table,
            $queryBuilder->expr()->eq(
                $this->tableSysDmailGroupMm . '.uid_foreign',
                $queryBuilder->quoteIdentifier($this->table . '.uid')
            )
        )
        ->innerJoin(
            $this->table,
            $this->tableFeUsers,
            $this->tableFeUsers,
            $queryBuilder->expr()->inSet(
                $this->tableFeUsers . '.usergroup',
                $queryBuilder->quoteIdentifier($this->table . '.uid')
            )
        )
        ->andWhere(
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq(
                    $this->tableSysDmailGroupMm . '.uid_local',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $this->tableSysDmailGroupMm . '.tablenames',
                    $queryBuilder->createNamedParameter($this->table)
                ),
                $queryBuilder->expr()->neq(
                    $this->tableFeUsers . '.email',
                    $queryBuilder->createNamedParameter('')
                ),
                $queryBuilder->expr()->eq(
                    $this->tableSysDmailGroup . '.deleted',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
                $queryBuilder->expr()->eq(
                    $this->tableFeUsers . '.module_sys_dmail_newsletter',
                    1
                )
            )
        )
        ->orderBy($this->tableFeUsers . '.uid')
        ->addOrderBy($this->tableFeUsers . '.email')
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
        ->from($this->tableSysDmailGroup, $this->tableSysDmailGroup)
        ->leftJoin(
            $this->tableSysDmailGroup,
            $this->tableSysDmailGroupMm,
            $this->tableSysDmailGroupMm,
            $queryBuilder->expr()->eq(
                $this->tableSysDmailGroupMm . '.uid_local',
                $queryBuilder->quoteIdentifier($this->tableSysDmailGroup . '.uid')
            )
        )
        ->andWhere(
            $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq(
                    $this->tableSysDmailGroup . '.uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $this->table . '.uid',
                    $queryBuilder->quoteIdentifier($this->tableSysDmailGroupMm . '.uid_foreign')
                ),
                $queryBuilder->expr()->eq(
                    $this->tableSysDmailGroupMm. '.tablenames',
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
                $usergroupInList .= (($usergroupInList == null) ? null : ' OR') . ' INSTR( CONCAT(\',\',' . $this->tableFeUsers . '.usergroup,\',\'),CONCAT(\',' . (int)$subgroup . ',\') )';
            }
            $usergroupInList = '(' . $usergroupInList . ')';

            // fetch all fe_users from these subgroups
            $queryBuilder = $this->getQueryBuilder($this->table);

            $queryBuilder
            ->selectLiteral('DISTINCT ' . $this->tableFeUsers . '.uid', $this->tableFeUsers . '.email')
            ->from($this->table, $this->table)
            ->innerJoin(
                $this->table,
                $this->tableFeUsers,
                $this->tableFeUsers
            )
            ->orWhere($usergroupInList)
            ->andWhere(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->neq(
                        $this->tableFeUsers . '.email',
                        $queryBuilder->createNamedParameter('')
                    ),
                    // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
                    $queryBuilder->expr()->eq(
                        $this->tableFeUsers . '.module_sys_dmail_newsletter',
                        1
                    )
                )
            )
            ->orderBy($this->tableFeUsers . '.uid')
            ->addOrderBy($this->tableFeUsers . '.email');

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
        $queryBuilder = $this->getQueryBuilder($this->table);

        $queryBuilder->selectLiteral('DISTINCT ' . $this->table. '.uid')
        ->from($this->table, $this->table)
        ->join(
            $this->table,
            $this->tableSysDmailGroupMm,
            $this->tableSysDmailGroupMm,
            $queryBuilder->expr()->eq(
                $this->tableSysDmailGroupMm . '.uid_local',
                $queryBuilder->quoteIdentifier($this->table . '.uid')
            )
        )
        ->join(
            $this->tableSysDmailGroupMm,
            $this->tableSysDmailGroup,
            $this->tableSysDmailGroup,
            $queryBuilder->expr()->eq(
                $this->tableSysDmailGroupMm . '.uid_local',
                $queryBuilder->quoteIdentifier($this->tableSysDmailGroup . '.uid')
            )
        )
        ->andWhere('INSTR( CONCAT(\',\', ' . $this->table . '.subgroup,\',\'),\',' . $groupId . ',\' )');

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
        $queryBuilder = $this->getQueryBuilder($this->table);

        // fe user group uid should be in list of fe users list of user groups
        //		$field = $this->tableFeUsers.'.usergroup';
        //		$command = $this->table.'.uid';
        // This approach, using standard SQL, does not work,
        // even when fe_users.usergroup is defined as varchar(255) instead of tinyblob
        // $usergroupInList = ' AND ('.$field.' LIKE \'%,\'||'.$command.'||\',%\' OR '.$field.' LIKE '.$command.'||\',%\' OR '.$field.' LIKE \'%,\'||'.$command.' OR '.$field.'='.$command.')';
        // The following will work but INSTR and CONCAT are available only in mySQL

        if ($cat < 1) {
            $res = $queryBuilder
            ->selectLiteral('DISTINCT ' . $this->tableFeUsers . '.uid', $this->tableFeUsers . '.email')
            ->from($this->tableFeUsers, $this->tableFeUsers)
            ->from($this->table, $this->table)
            ->andWhere(
                $queryBuilder->expr()->and()
                ->add(
                    $queryBuilder->expr()->in(
                        $this->table . '.pid',
                        $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)
                    )
                )
                ->add('INSTR( CONCAT(\',\',' . $this->tableFeUsers . '.usergroup,\',\'),CONCAT(\',\',' . $this->table . '.uid ,\',\') )')
                ->add(
                    $queryBuilder->expr()->neq(
                        $this->tableFeUsers . '.email',
                        $queryBuilder->createNamedParameter('')
                    )
                )
                ->add(
                    $queryBuilder->expr()->eq(
                        $this->tableFeUsers . '.module_sys_dmail_newsletter',
                        1
                    )
                )
            )
            ->orderBy($this->tableFeUsers . '.uid')
            ->addOrderBy($this->tableFeUsers . '.email')
            ->executeQuery();
        } else {
            $mmTable = $GLOBALS['TCA'][$this->tableFeUsers]['columns']['module_sys_dmail_category']['config']['MM'];
            $res = $queryBuilder
            ->selectLiteral('DISTINCT ' . $this->tableFeUsers . '.uid', $this->tableFeUsers . '.email')
            ->from($this->tableSysDmailGroup, $this->tableSysDmailGroup)
            ->from($this->tableSysDmailGroupCategoryMm, 'g_mm')
            ->from($this->table, $this->table)
            ->from($mmTable, 'mm_1')
            ->leftJoin(
                'mm_1',
                $this->tableFeUsers,
                $this->tableFeUsers,
                $queryBuilder->expr()->eq(
                    $this->tableFeUsers . '.uid',
                    $queryBuilder->quoteIdentifier('mm_1.uid_local')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->and()
                    ->add(
                        $queryBuilder->expr()->in(
                            $this->table . '.pid',
                            $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)
                        )
                    )
                    ->add('INSTR( CONCAT(\',\',' . $this->tableFeUsers . '.usergroup,\',\'),CONCAT(\',\',' . $this->table . '.uid ,\',\') )')
                    ->add(
                        $queryBuilder->expr()->eq(
                            'mm_1.uid_foreign',
                            $queryBuilder->quoteIdentifier('g_mm.uid_foreign')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            $this->tableSysDmailGroup . '.uid',
                            $queryBuilder->quoteIdentifier('g_mm.uid_local')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            $this->tableSysDmailGroup . '.uid',
                            $queryBuilder->createNamedParameter($groupUid, Connection::PARAM_INT)
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->neq(
                            $this->tableFeUsers . '.email',
                            $queryBuilder->createNamedParameter('')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            $this->tableFeUsers . '.module_sys_dmail_newsletter',
                            1
                        )
                    )
            )
            ->orderBy($this->tableFeUsers . '.uid')
            ->addOrderBy($this->tableFeUsers . '.email')
            ->executeQuery();
        }

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }
        return $outArr;
    }
}
