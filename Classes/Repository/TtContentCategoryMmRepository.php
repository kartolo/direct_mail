<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class TtContentCategoryMmRepository extends MainRepository
{
    protected string $table = 'sys_dmail_ttcontent_category_mm';

    /**
     * @return array|bool
     */
    public function selectUidForeignByUid(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('uid_foreign')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'uid_local',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->executeQuery()
        ->fetchAllAssociative();
    }
}
