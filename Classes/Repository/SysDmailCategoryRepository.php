<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class SysDmailCategoryRepository extends MainRepository
{
    protected string $table                            = 'sys_dmail_category';
    protected string $tableSysDmailTtcontentCategoryMm = 'sys_dmail_ttcontent_category_mm';

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
        $queryBuilder = $this->getQueryBuilder($this->table);
        return $queryBuilder
            ->select($this->table . '.uid')
            ->from($this->table)
            ->from($this->tableSysDmailTtcontentCategoryMm)
            ->where(
                $queryBuilder->expr()->eq(
                    $this->table . '.uid',
                    $this->tableSysDmailTtcontentCategoryMm . '.uid_foreign'
                )
            )
            ->andWhere(
                $queryBuilder->expr()->in(
                    $this->tableSysDmailTtcontentCategoryMm . '.uid_local',
                    $uid
                )
            )
            ->orderBy($this->table . '.uid')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
