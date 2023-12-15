<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailGroupRepository extends MainRepository
{
    protected string $table      = 'sys_dmail_group';
    protected string $tablePages = 'pages';

    /**
     * @return array|bool
     */
    public function selectSysDmailGroupByPid(int $pid, string $defaultSortBy) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('uid', 'pid', 'title', 'description', 'type')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            )
        )
        ->orderBy(
            preg_replace(
                '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i',
                '',
                $defaultSortBy
            )
        )
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectSysDmailGroupForFinalMail(int $pid, int $sysLanguageUid, string $defaultSortBy) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('uid', 'pid', 'title')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->in(
                'sys_language_uid',
                '-1, ' . $sysLanguageUid
            )
        )
        ->orderBy(
            preg_replace(
                '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i',
                '',
                $defaultSortBy
            )
        )
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectSysDmailGroupForTestmail(array $intList, string $permsClause) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
        ->select($this->table . '.*')
        ->from($this->table)
        ->leftJoin(
            $this->table,
            $this->tablePages,
            $this->tablePages,
            $queryBuilder->expr()->eq(
                $this->table . '.pid',
                $queryBuilder->quoteIdentifier($this->tablePages . '.uid')
            )
        )
        ->where(
            $queryBuilder->expr()->in(
                $this->table . '.uid',
                $queryBuilder->createNamedParameter($intList, Connection::PARAM_INT_ARRAY)
            )
        )
        ->andWhere(
            $permsClause
        )
        ->executeQuery()
        ->fetchAllAssociative();
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
    public function getMailGroups(string $list, array $parsedGroups, string $permsClause): array
    {
        $groupIdList = GeneralUtility::intExplode(',', $list);
        $groups = [];

        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $res = $queryBuilder->select($this->table . '.*')
            ->from($this->table, $this->table)
            ->leftJoin(
                $this->table,
                $this->tablePages,
                $this->tablePages,
                $queryBuilder->expr()->eq(
                    $this->tablePages . '.uid',
                    $queryBuilder->quoteIdentifier($this->table . '.pid')
                )
            )
            ->where(
                $queryBuilder->expr()->in(
                    $this->table . '.uid',
                    $queryBuilder->createNamedParameter($groupIdList, Connection::PARAM_INT_ARRAY)
                )
            )
            ->andWhere(
                $permsClause
            )
            ->executeQuery();

        while ($row = $res->fetchAssociative()) {
            if ($row['type'] == 4) {
                // Other mail group...
                if (!in_array($row['uid'], $parsedGroups)) {
                    $parsedGroups[] = $row['uid'];
                    $groups = array_merge($groups, $this->getMailGroups($row['mail_groups'] ?? '', $parsedGroups, $permsClause));
                }
            } else {
                // Normal mail group, just add to list
                $groups[] = $row['uid'];
            }
        }
        return $groups;
    }

    /**
     * @param int $uid
     * @param array $updateData
     * @return int
     */
    public function updateSysDmailGroupRecord(int $uid, array $updateData): int
    {
        $connection = $this->getConnection($this->table);
        return $connection->update(
            $this->table, // table
            $updateData, // value array
            [ 'uid' => $uid ] // where
        );
    }
}
