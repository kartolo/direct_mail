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
            $queryBuilder->expr()->eq(
                'pages.uid',
                $queryBuilder->quoteIdentifier($this->table.'.pid')
            )
        )
        ->add('where', $this->table.'.uid = ' . intval($uid) .
            ' AND ' . $permsClause . ' AND pages.deleted = 0')

//         debug($queryBuilder->getSQL());
//         debug($queryBuilder->getParameters());
        ->execute()
        ->fetchAll();
    }

        /**
     * Returns record no matter what - except if record is deleted
     *
     * @param int $uid The uid to look up in $table
     *
     * @return mixed Returns array (the record) if found, otherwise blank/0 (zero)
     * @see getPage_noCheck()
     */
    public function getRawRecord(int $uid)
    {
        if ($uid > 0) {
            $queryBuilder = $this->getQueryBuilder($this->table);
            $queryBuilder->select('*')->from($this->table);
            $queryBuilder->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0)
                )
            );

            $rows = $queryBuilder->execute()->fetchAll();

            if ($rows) {
                if (is_array($rows[0])) {
                    return $rows[0];
                }
            }
        }
        return 0;
    }
}
