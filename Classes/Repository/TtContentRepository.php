<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class TtContentRepository extends MainRepository {
    protected string $table = 'tt_content';
    
    /**
     * @return array|bool
     */
    public function selectTtContentByPidAndSysLanguageUid(int $pid, int $sysLanguageUid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->select('colPos', 'CType', 'list_type', 'uid', 'pid', 'header', 'bodytext', 'module_sys_dmail_category')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
            ),
            $queryBuilder->expr()->eq(
                'sys_language_uid',
                $queryBuilder->createNamedParameter($sysLanguageUid, \PDO::PARAM_INT)
            )
        )
        ->orderBy('colPos')
        ->addOrderBy('sorting')
        ->execute()
        ->fetchAll();
    }
}