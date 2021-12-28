<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

#use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailMaillogRepository extends MainRepository {
    protected string $table = 'sys_dmail_maillog';
    
    public function countSysDmailMaillogAllByMid(int $mid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->count('*')
        ->addSelect('html_sent')
        ->from($this->table)
        ->add('where','mid=' . $mid . ' AND response_type=0')
        ->groupBy('html_sent')
        ->execute()
        ->fetchAll();
    }
    
    public function countSysDmailMaillogHtmlByMid(int $mid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->count('*')
        ->from($this->table)
        ->add('where','mid=' . $mid . ' AND response_type=1')
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAll();
    }
    
    public function countSysDmailMaillogPlainByMid(int $mid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->count('*')
        ->from($this->table)
        ->add('where','mid=' . $mid . ' AND response_type=2')
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAll();
    }
    
    public function countSysDmailMaillogPingByMid(int $mid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->count('*')
        ->from($this->table)
        ->add('where','mid=' . $mid . ' AND response_type=-1')
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAll();
    }
}