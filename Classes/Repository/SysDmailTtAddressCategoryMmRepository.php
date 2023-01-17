<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class SysDmailTtAddressCategoryMmRepository extends MainRepository
{
    protected string $table = 'sys_dmail_ttaddress_category_mm';

    /**
     * @return array|bool
     */
    public function selectUidsByUidLocal(int $uidLocal) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select(
            'uid_local',
            'uid_foreign'
        )
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'uid_local',
                $queryBuilder->createNamedParameter($uidLocal, Connection::PARAM_INT)
            )
        )
        ->orderBy('sorting')
        ->executeQuery()
        ->fetchAllAssociative();
    }
}
