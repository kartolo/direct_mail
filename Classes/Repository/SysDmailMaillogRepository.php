<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailMaillogRepository extends MainRepository {
    protected string $table = 'sys_dmail_maillog';
    
    /**
     * @return array|bool
     */
    public function countSysDmailMaillogAllByMid(int $mid) //: array|bool 
    {
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
    
    /**
     * @return array|bool
     */
    public function countSysDmailMaillogHtmlByMid(int $mid) //: array|bool 
    {
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
    
    /**
     * @return array|bool
     */
    public function countSysDmailMaillogPlainByMid(int $mid) //: array|bool 
    {
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
    
    /**
     * @return array|bool
     */
    public function countSysDmailMaillogPingByMid(int $mid) //: array|bool 
    {
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
    
    /**
     * @return array|bool
     */
    public function selectByResponseType(int $responseType) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('uid', 'tstamp')
        ->from($this->table)
        ->where($queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($responseType, \PDO::PARAM_INT)))
        ->orderBy('tstamp','DESC')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function countSysDmailMaillogs(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->count('*')
        ->from($this->table)
        ->add('where', 'mid = ' . intval($uid) .
            ' AND response_type = 0' .
            ' AND html_sent > 0')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function countSysDmailMaillogsResponseTypeByMid(int $uid) //: array|bool 
    {
        $responseTypes = [];
        $queryBuilder = $this->getQueryBuilder($this->table);

        $statement = $queryBuilder->count('*')
            ->addSelect('response_type')
            ->from($this->table)
            ->add('where', 'mid = ' . intval($uid))
            ->groupBy('response_type')
            ->execute();

        while ($row = $statement->fetchAssociative()) {
            $responseTypes[$row['response_type']] = $row;
        }

        return $responseTypes;
    }
    
    /**
     * @return array|bool
     */
    public function selectSysDmailMaillogsCompactView(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('uid')
        ->from($this->table)
        ->add('where', 'mid=' . intval($uid) . 
            ' AND response_type = 0')
        ->orderBy('rid','ASC')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * 
     * @param int $uid
     * @param int $responseType: 1 for html, 2 for plain
     * @return array
     */
    public function selectMostPopularLinks(int $uid, int $responseType = 1): array
    {
        $popularLinks = [];
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        $statement = $queryBuilder->count('*')
            ->addSelect('url_id')
            ->from($this->table)
            ->add('where', 'mid=' . intval($uid) .
            ' AND response_type = '. intval($responseType))
            ->groupBy('url_id')
            ->orderBy('COUNT(*)')
            ->execute();
            
        while ($row = $statement->fetchAssociative()) {
            $popularLinks[$row['url_id']] = $row;
        }
        return $popularLinks;
    }
    
    /**
     * @return array|bool
     */
    public function countReturnCode(int $uid, int $responseType = -127) //: array|bool 
    {
        $returnCodes = [];
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        $statement = $queryBuilder->count('*')
        ->addSelect('return_code')
        ->from($this->table)
        ->add('where', 'mid=' . intval($uid) .
            ' AND response_type = '. intval($responseType))
        ->groupBy('return_code')
        ->orderBy('COUNT(*)')
        ->execute();
        
        while ($row = $statement->fetchAssociative()) {
            $returnCodes[$row['return_code']] = $row;
        }
        
        return $returnCodes;
    }
    
    /**
     * @return array|bool
     */
    public function selectStatTempTableContent(int $uid) //: array|bool 
    {
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
    
    /**
     * @return array|bool
     */
    public function findAllReturnedMail(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function findUnknownRecipient(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND (return_code=550 OR return_code=553)')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function findMailboxFull(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=551')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function findBadHost(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=552')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function findBadHeader(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=554')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function findUnknownReasons(int $uid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder->select('rid','rtbl','email')
        ->from($this->table)
        ->add('where','mid=' . intval($uid) .
            ' AND response_type=-127' .
            ' AND return_code=-1')
        ->execute()
        ->fetchAll();
    }
    
    /**
     * @return array|bool
     */
    public function selectForAnalyzeBounceMail(int $rid, string $rtbl, int $mid) //: array|bool 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        
        return $queryBuilder
        ->select('uid','email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq(
                    'rid',
                    $queryBuilder->createNamedParameter((int)$rid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'rtbl',
                    $queryBuilder->createNamedParameter($rtbl, \PDO::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'mid',
                    $queryBuilder->createNamedParameter((int)$mid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq('response_type', 0)
            )
        )
        ->setMaxResults(1)
        ->execute()
        ->fetchAssociative();
    }

    public function insertForJumpurl(array $mailLogParams) 
    {
        $connection = $this->getConnection($this->table);
        $connection->insert($this->table, $mailLogParams);
    }

    /**
     * Check if an entry exists that is younger than 10 seconds
     *
     * @param array $mailLogParameters
     * @return bool
     */
    public function hasRecentLog(array $mailLogParameters): bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        $query = $queryBuilder
            ->count('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mailLogParameters['mid'], \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('url', $queryBuilder->createNamedParameter($mailLogParameters['url'], \PDO::PARAM_STR)),
                $queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter($mailLogParameters['response_type'], \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('url_id', $queryBuilder->createNamedParameter($mailLogParameters['url_id'], \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($mailLogParameters['rtbl'], \PDO::PARAM_STR)),
                $queryBuilder->expr()->eq('rid', $queryBuilder->createNamedParameter($mailLogParameters['rid'], \PDO::PARAM_INT)),
                $queryBuilder->expr()->lte('tstamp', $queryBuilder->createNamedParameter($mailLogParameters['tstamp'], \PDO::PARAM_INT)),
                $queryBuilder->expr()->gte('tstamp', $queryBuilder->createNamedParameter($mailLogParameters['tstamp']-10, \PDO::PARAM_INT))
            );

        $existingLog = $query->execute()->fetchColumn();

        return (int)$existingLog > 0;
    }

    public function updateSysDmailMaillogForShipOfMail(array $values) 
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->update($this->table)
            ->set('tstamp', time())
            ->set('size', (int)$values['size'])
            ->set('parsetime', (int)$values['parsetime'])
            ->set('html_sent', (int)$values['html_sent'])
            ->where(
                $queryBuilder->expr()->eq(
                    'uid', 
                    $queryBuilder->createNamedParameter($values['logUid'], \PDO::PARAM_INT)
                )
            )
            ->execute();
    }

    /**
     * Find out, if an email has been sent to a recipient
     *
     * @param int $mid Newsletter ID. UID of the sys_dmail record
     * @param int $rid Recipient UID
     * @param string $rtbl Recipient table
     *
     * @return	bool Number of found records
     */
    public function dmailerIsSend(int $mid, int $rid, string $rtbl): bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        $statement = $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('rid', $queryBuilder->createNamedParameter($rid, \PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($rtbl)))
            ->andWhere($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, \PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        return (bool)$statement->rowCount();
    }

    /**
     * Add action to sys_dmail_maillog table
     *
     * @param int $mid Newsletter ID
     * @param string $rid Recipient ID
     * @param int $size Size of the sent email
     * @param int $parsetime Parse time of the email
     * @param int $html Set if HTML email is sent
     * @param string $email Recipient's email
     *
     * @return bool True on success or False on error
     */
    public function dmailerAddToMailLog(int $mid, string $rid, int $size, int $parsetime, int $html, string $email): int
    {
        [$rtbl, $rid] = explode('_', $rid);

        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->insert($this->table)
            ->values([
                'mid' => $mid,
                'rtbl' => (string)$rtbl,
                'rid' => (int)$rid,
                'email' => $email,
                'tstamp' => time(),
                'url' => '',
                'size' => $size,
                'parsetime' => $parsetime,
                'html_sent' => $html,
            ])
            ->execute();

        return (int)$queryBuilder->getConnection()->lastInsertId($this->table);
    }

    /**
     * Get IDs of recipient, which has been sent
     *
     * @param	int $mid Newsletter ID. UID of the sys_dmail record
     * @param	string $rtbl Recipient table
     *
     * @return	string		list of sent recipients
     */
    public function dmailerGetSentMails(int $mid, string $rtbl): string
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $statement = $queryBuilder
            ->select('rid')
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter($mid, \PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($rtbl)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();

        $list = '';

        while ($row = $statement->fetch()) {
            $list .= $row['rid'] . ',';
        }

        return rtrim($list, ',');
    }
}