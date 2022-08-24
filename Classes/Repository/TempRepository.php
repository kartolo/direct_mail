<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TempRepository extends MainRepository {
    
    /**
     * Get recipient DB record given on the ID
     *
     * @param array $listArr List of recipient IDs
     * @param string $table Table name
     * @param string $fields Field to be selected
     *
     * @return array recipients' data
     */
    public static function fetchRecordsListValues(array $listArr, $table, $fields = 'uid,name,email')
    {
        $outListArr = [];
        if (is_array($listArr) && count($listArr)) {
            $idlist = implode(',', $listArr);
            
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            
            $fieldArray = GeneralUtility::trimExplode(',', $fields);
            
            // handle selecting multiple fields
            foreach ($fieldArray as $i => $field) {
                if ($i) {
                    $queryBuilder->addSelect($field);
                } 
                else {
                    $queryBuilder->select($field);
                }
            }
            
            $res = $queryBuilder->from($table)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter(
                        GeneralUtility::intExplode(',', $idlist),
                        Connection::PARAM_INT_ARRAY
                        )
                    )
                )
            ->execute();
                
            while ($row = $res->fetch()) {
                $outListArr[$row['uid']] = $row;
            }
        }
        return $outListArr;
    }
}