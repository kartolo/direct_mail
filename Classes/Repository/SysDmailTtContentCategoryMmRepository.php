<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class SysDmailTtContentCategoryMmRepository extends MainRepository {
    protected string $table = 'sys_dmail_ttcontent_category_mm';

    /**
     * @return array|bool
     */
    public function selectByUidLocal(int $uidLocal) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('uid_foreign')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'uid_local',
                $queryBuilder->createNamedParameter($uidLocal, \PDO::PARAM_INT)
            )
        )
        ->orderBy('sorting')
        ->execute()
        ->fetchAll();
    }
}
