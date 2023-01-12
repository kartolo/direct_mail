<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

class TtAddressRepository extends MainRepository
{
    protected string $table = 'tt_address';

    /**
     * @return array|bool
     */
    public function selectTtAddressByUid(int $uid, string $permsClause) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select($this->table . '.*')
        ->from($this->table, $this->table)
        ->leftjoin(
            $this->table,
            'pages',
            'pages',
            $queryBuilder->expr()->eq(
                'pages.uid',
                $queryBuilder->quoteIdentifier($this->table . '.pid')
            )
        )
        ->where(
            $queryBuilder->expr()->eq(
                $this->table . '.uid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->eq(
                'pages.deleted',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $permsClause
        )
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectTtAddressByPid(int $pid, string $recordUnique) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        // only add deleteClause
        //https://github.com/FriendsOfTYPO3/tt_address/blob/master/Configuration/TCA/tt_address.php
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
        ->select(
            'uid',
            $recordUnique
        )
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            )
        )
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectTtAddressForTestmail(array $intList, string $permsClause) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select($this->table . '.*')
        ->from($this->table)
        ->leftJoin(
            $this->table,
            'pages',
            'pages',
            $queryBuilder->expr()->eq(
                'pages.uid',
                $queryBuilder->quoteIdentifier($this->table . '.pid')
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
     * @return array|bool
     */
    public function selectTtAddressForSendMailTest(int $ttAddressUid, string $permsClause) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('a.*')
        ->from($this->table, 'a')
        ->leftJoin(
            'a',
            'pages',
            'pages',
            $queryBuilder->expr()->eq(
                'pages.uid',
                $queryBuilder->quoteIdentifier('a.pid')
            )
        )
        ->where(
            $queryBuilder->expr()->eq(
                'a.uid',
                $queryBuilder->createNamedParameter($ttAddressUid, Connection::PARAM_INT)
            )
        )
        ->andWhere($permsClause)
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function deleteRowsByPid(int $pid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->delete($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            )
        )
        ->executeStatement();
    }

    /**
     * Returns record no matter what - except if record is deleted
     *
     * @param int $uid The uid to look up in $table
     *
     * @return mixed Returns array (the record) if found, otherwise blank/0 (zero)
     * @see getPage_noCheck()
     */
    public function getRawRecord(int $uid)
    {
        if ($uid > 0) {
            $v = VersionNumberUtility::convertVersionNumberToInteger(ExtensionManagementUtility::getExtensionVersion('tt_address'));

            $queryBuilder = $this->getQueryBuilder($this->table);
            $queryBuilder->select('*')->from($this->table);

            if ($v <= VersionNumberUtility::convertVersionNumberToInteger('6.0.0')) {
                $queryBuilder->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'deleted',
                        $queryBuilder->createNamedParameter(0)
                    )
                );
            } else {
                $queryBuilder->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                    )
                );
            }
            $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

            if ($rows) {
                if (is_array($rows[0])) {
                    return $rows[0];
                }
            }
        }
        return 0;
    }

        /**
     * Return all uid's from 'tt_address' for a static direct mail group.
     *
     * @param int $uid The uid of the direct_mail group
     *
     * @return array The resulting array of uid's
     */
    public function getStaticIdList(int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        $res = $queryBuilder
        ->selectLiteral('DISTINCT ' . $this->table . '.uid', $this->table . '.email')
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
                    $this->table . '.email',
                    $queryBuilder->createNamedParameter('')
                )
            )
            ->add(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group.deleted',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
        )
        ->orderBy($this->table . '.uid')
        ->addOrderBy($this->table . '.email')
        ->executeQuery();

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }

        return $outArr;
    }
}
