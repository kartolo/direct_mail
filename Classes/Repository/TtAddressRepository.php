<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class TtAddressRepository extends MainRepository {
    protected string $table = 'tt_address';
    
    /**
     * @return array|bool
     */
    public function selectTtAddressByUid(int $uid, string $permsClause) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select($this->table.'.*')
        ->from($this->table, $this->table)
        ->leftjoin(
            $this->table,
            'pages',
            'pages',
            $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier($this->table.'.pid'))
        )
        ->add('where', $this->table.'.uid = ' . intval($uid) . 
            ' AND ' . $permsClause . ' AND pages.deleted = 0')

//         debug($queryBuilder->getSQL());
//         debug($queryBuilder->getParameters());
        ->execute()
        ->fetchAll();
    }

    /**
     * @return array|bool
     */
    public function selectTtAddressForTestmail(string $intList, string $permsClause) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select($this->table.'.*')
        ->from($this->table)
        ->leftJoin(
            $this->table,
            'pages',
            'pages',
            $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier($this->table.'.pid'))
        )
        ->add('where', $this->table.'.uid IN (' . $intList . ')' .
            ' AND ' . $permsClause)
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function selectTtAddressForSendMailTest(int $ttAddressUid, string $permsClause) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->select('a.*')
        ->from($this->table, 'a')
        ->leftJoin(
            'a', 
            'pages', 
            'pages', 
            $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('a.pid'))
        )
        ->where(
            $queryBuilder->expr()->eq('a.uid', $queryBuilder->createNamedParameter($ttAddressUid, \PDO::PARAM_INT))
        )
        ->andWhere($permsClause)
        ->execute()
        ->fetchAll();
    }
}