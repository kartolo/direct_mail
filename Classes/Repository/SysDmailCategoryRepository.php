<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailCategoryRepository extends MainRepository {
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
                $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
            )
        )
        ->execute()
//         debug($queryBuilder->getSQL());
//         debug($queryBuilder->getParameters());
        ->fetchAll();
    }
}