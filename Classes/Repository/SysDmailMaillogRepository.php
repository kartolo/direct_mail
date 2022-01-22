<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

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
    
    public function selectByResponseType(int $responseType): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('uid', 'tstamp')
        ->from($this->table)
        ->where($queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, \PDO::PARAM_INT)))
        ->orderBy('tstamp','DESC')
        ->execute()
        ->fetchAll();
    }
    
    public function countSysDmailMaillogs(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->count('*')
        ->from($this->table)
        ->add('where', 'mid = ' . intval($uid) .
            ' AND response_type = 0' .
            ' AND html_sent > 0')
        ->execute()
        ->fetchAll();
    }
    
    public function countSysDmailMaillogsResponseTypeByMid(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->count('*')
            ->addSelect('response_type')
            ->from($this->table)
            ->add('where', 'mid = ' . intval($uid))
            ->groupBy('response_type')
            ->execute()
            ->fetchAll();
    }
    
    public function selectSysDmailMaillogsCompactView(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('uid')
        ->from($this->table)
        ->add('where', 'mid=' . intval($uid) . 
            ' AND response_type = 0')
        ->orderBy('rid','ASC')
        ->execute()
        ->fetchAll();
    }
    
    public function selectStatTempTableContent(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','tstamp','response_type','url_id','html_sent','size')
        ->from($this->table)
        ->add('where', 'mid=' . intval($uid))
        ->orderBy('rtbl')
        ->addOrderBy('rid')
        ->addOrderBy('tstamp')
        ->execute()
        ->fetchAll();
    }
    
    public function findAllReturnedMail(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127')
        ->execute()
        ->fetchAll();
    }
    
    public function findUnknownRecipient(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND (return_code=550 OR return_code=553)')
        ->execute()
        ->fetchAll();
    }
    
    public function findMailboxFull(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=551')
        ->execute()
        ->fetchAll();
    }
    
    public function findBadHost(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=552')
        ->execute()
        ->fetchAll();
    }
    
    public function findBadHeader(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=554')
        ->execute()
        ->fetchAll();
    }
    
    public function findUnknownReasons(int $uid): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=-1')
        ->execute()
        ->fetchAll();
    }
}