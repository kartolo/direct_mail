<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class FeUsersRepository extends MainRepository {
    protected string $table = 'fe_users';
    
    /**
     * @return array|bool
     */
    public function selectFeUsersByUid(int $uid, string $permsClause) //: array|bool 
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
}