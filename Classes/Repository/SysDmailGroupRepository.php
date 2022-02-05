<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailGroupRepository extends MainRepository {
    protected string $table = 'sys_dmail_group';
    
    /**
     * @return array|bool
     */
    public function selecetSysDmailGroupByPid(int $pid, string $defaultSortBy) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        
        return $queryBuilder->select('uid','pid','title','description','type')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT))
        )
        ->orderBy(
            preg_replace(
                '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                $defaultSortBy
            )
        )
        ->execute()
        ->fetchAll();
    }
}