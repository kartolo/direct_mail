<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class PagesRepository extends MainRepository {
    protected string $table = 'pages';
    
    /**
     * @return array|bool
     */
    public function selectPagesForDmail(int $pid, string $permsClause) //: array|bool 
    {
        // Here the list of subpages, news, is rendered
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->select('uid', 'doktype', 'title', 'abstract')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
            ),
            $queryBuilder->expr()->eq('l10n_parent', 0), // Exclude translated page records from list
            $permsClause
        );
        
        /**
         * https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/ApiOverview/PageTypes/TypesOfPages.html
         * typo3/sysext/core/Classes/Domain/Repository/PageRepository.php
         *
         * Regards custom configurations, otherwise ignores spacers (199), recyclers (255) and folders (254)
         *
         **/
        
        return $queryBuilder
        ->andWhere(
            $queryBuilder->expr()->notIn(
                'doktype',
                [199,254,255]
            )
        )
        ->orderBy('sorting')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function selectPageByL10nAndSysLanguageUid(int $pageUid, int $langUid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->select('sys_language_uid')
        ->from($this->table)
        ->where($queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, \PDO::PARAM_INT)))
        ->andWhere($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($langUid, \PDO::PARAM_INT)))
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function selectSubfolders(string $permsClause) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->select('uid', 'title')
        ->from($this->table)
        ->where(
            $permsClause,
            $queryBuilder->expr()->eq(
                'doktype',
                '254'
            )
        )
        ->orderBy('uid')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function selectTitleTranslatedPage(int $pageUid, int $langUid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->select('title')
        ->from($this->table)
        ->where($queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, \PDO::PARAM_INT)))
        ->andWhere($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($langUid, \PDO::PARAM_INT)))
        ->execute()
        ->fetch();
    }
    
    /**
     * 
     * @param int $pageUid
     * @param string $tsConf
     * @return int
     */
    public function updatePageTSconfig(int $pageUid, string $tsConf) {
        $connection = $this->getConnection($this->table);
        return $connection->update(
            $this->table,
            [ 'TSconfig' => $tsConf ],
            [ 'uid' => $pageUid ] // where
        );
    }

    /**
     * @return array|bool
     */
    public function getDMPages() //: array|bool
    {
        $modulName = 'dmail';
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('uid','title')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'module', 
                $queryBuilder->createNamedParameter($modulName, \PDO::PARAM_STR)
            )
        )
        ->execute()
        ->fetchAll();
    }
}