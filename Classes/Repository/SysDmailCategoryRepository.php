<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class SysDmailCategoryRepository extends MainRepository
{
    protected string $table = 'sys_dmail_category';

    /**
     * @return array|bool
     */
    public function selectSysDmailCategoryByPid(int $pid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        return $queryBuilder
        ->select('*')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->in(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            )
        )
        ->executeQuery()
        ->fetchAllAssociative();
    }

    public function selectSysDmailCategoryForContainer(int $uid) //: array|bool
    {
        $select = $this->table . '.uid';
        $mmTable = 'sys_dmail_ttcontent_category_mm';
        $orderBy = $this->table . '.uid';

        $queryBuilder = $this->getQueryBuilder($this->table);
        return $queryBuilder
            ->select($select)
            ->from($this->table)
            ->from($mmTable)
            ->where(
                $queryBuilder->expr()->eq(
                    $this->table . '.uid',
                    $mmTable . '.uid_foreign'
                )
            )
            ->andWhere(
                $queryBuilder->expr()->in(
                    $mmTable . '.uid_local',
                    $uid
                )
            )
            ->orderBy($orderBy)
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
