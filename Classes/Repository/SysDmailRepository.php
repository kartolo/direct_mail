<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailRepository extends MainRepository {
    protected string $table = 'sys_dmail';
    
    public function selectSysDmailById(int $sys_dmail_uid, int $pid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder->select('*')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)),
            $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($sys_dmail_uid, \PDO::PARAM_INT))
        )
        //debug($queryBuilder->getSQL());
        //debug($queryBuilder->getParameters());
        ->execute()
        ->fetch();
    }
    
    public function selectSysDmailsByPid(int $pid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('uid', 'pid', 'subject', 'scheduled', 'scheduled_begin', 'scheduled_end')
        ->from($this->table)
        ->add('where','pid = ' . intval($pid) .' AND scheduled > 0')
        ->orderBy('scheduled','DESC')
        ->execute()
        ->fetchAllAssociative();
    }
}