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
    
    public function selectForPageInfo(int $id): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        
        return $queryBuilder->selectLiteral('sys_dmail.uid', 'sys_dmail.subject', 'sys_dmail.scheduled', 'sys_dmail.scheduled_begin', 'sys_dmail.scheduled_end', 'COUNT(sys_dmail_maillog.mid) AS count')
        ->from($this->table, $this->table)
        ->leftJoin(
            'sys_dmail',
            'sys_dmail_maillog',
            'sys_dmail_maillog',
            $queryBuilder->expr()->eq('sys_dmail.uid', $queryBuilder->quoteIdentifier('sys_dmail_maillog.mid'))
        )
        ->add('where','sys_dmail.pid = ' . $id .
                ' AND sys_dmail.type IN (0,1)' .
                ' AND sys_dmail.issent = 1'.
                ' AND sys_dmail_maillog.response_type = 0'.
                ' AND sys_dmail_maillog.html_sent > 0')
        ->groupBy('sys_dmail_maillog.mid')
        ->orderBy('sys_dmail.scheduled','DESC')
        ->addOrderBy('sys_dmail.scheduled_begin','DESC')
        ->execute()
        ->fetchAll();
    }
    
    public function selectForMkeListDMail(int $id, string $sOrder, string $ascDesc): array|bool {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        
        return $queryBuilder->select('uid','pid','subject','tstamp','issent','renderedsize','attachment','type')
        ->from($this->table)
        ->add('where','pid = ' . intval($id) .
            ' AND scheduled=0 AND issent=0')
        ->orderBy($sOrder,$ascDesc)
        ->execute()
        ->fetchAll();
    }
}