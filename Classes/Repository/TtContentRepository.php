<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class TtContentRepository extends MainRepository
{
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
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->eq(
                'sys_language_uid',
                $queryBuilder->createNamedParameter($sysLanguageUid, Connection::PARAM_INT)
            )
        )
        ->orderBy('colPos')
        ->addOrderBy('sorting')
        ->executeQuery()
        ->fetchAllAssociative();
    }
}
