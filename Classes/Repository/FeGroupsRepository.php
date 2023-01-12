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
            $queryBuilder->expr()->andX()
            ->add(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group_mm.uid_local',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->add(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group_mm.tablenames',
                    $queryBuilder->createNamedParameter($this->table)
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
                    'sys_dmail_group.deleted',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
            ->add(
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
            $queryBuilder->expr()->andX()
            ->add(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group.uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->add(
                $queryBuilder->expr()->eq(
                    'fe_groups.uid',
                    $queryBuilder->quoteIdentifier('sys_dmail_group_mm.uid_foreign')
                )
            )
            ->add(
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
            // for fe_users and fe_group, only activated modulde_sys_dmail_newsletter
            $addWhere =  $queryBuilder->expr()->eq(
                $switchTable . '.module_sys_dmail_newsletter',
                1
            );

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
                $queryBuilder->expr()->andX()
                    ->add(
                        $queryBuilder->expr()->neq(
                            $switchTable . '.email',
                            $queryBuilder->createNamedParameter('')
                        )
                    )
                    ->add(
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
}
