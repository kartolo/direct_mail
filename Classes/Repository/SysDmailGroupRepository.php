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
    public function selectSysDmailGroupByPid(int $pid, string $defaultSortBy) //: array|bool 
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

    /**
     * @return array|bool
     */
    public function selectSysDmailGroupForFinalMail(int $pid, int $sysLanguageUid, string $defaultSortBy) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('uid','pid','title')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT))
        )
        ->andWhere(
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    '-1, ' . $sysLanguageUid
                    )
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
    
    /**
     * @return array|bool
     */
    public function selectSysDmailGroupForTestmail(string $intList, string $permsClause) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        
        return $queryBuilder
        ->select($this->table.'.*')
        ->from($this->table)
        ->leftJoin(
            $this->table,
            'pages',
            'pages',
            $queryBuilder->expr()->eq($this->table.'.pid', $queryBuilder->quoteIdentifier('pages.uid'))
        )
        ->add('where', $this->table.'.uid IN (' . $intList . ')' .
                ' AND ' . $permsClause )
        ->execute()
        ->fetchAll();
    }
}