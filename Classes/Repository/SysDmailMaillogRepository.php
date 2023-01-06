<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;

class SysDmailMaillogRepository extends MainRepository
{
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
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->groupBy('html_sent')
        ->execute()
        ->fetchAllAssociative();
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
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
            )
        )
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAllAssociative();
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
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(2, Connection::PARAM_INT)
            )
        )
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAllAssociative();
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
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(-1, Connection::PARAM_INT)
            )
        )
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAllAssociative();
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
        ->where(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter($responseType, Connection::PARAM_INT)
            )
        )
        ->orderBy('tstamp', 'DESC')
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function countSysDmailMaillogs(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->count('*')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->gt(
                'html_sent',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->execute()
        ->fetchAllAssociative();
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
            ->where(
                $queryBuilder->expr()->eq(
                    'mid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
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
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->orderBy('rid', 'ASC')
        ->execute()
        ->fetchAllAssociative();
    }

    /**
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
            ->where(
                $queryBuilder->expr()->eq(
                    'mid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'response_type',
                    $queryBuilder->createNamedParameter($responseType, Connection::PARAM_INT)
                )
            )
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
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter($responseType, Connection::PARAM_INT)
            )
        )
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

        return $queryBuilder
        ->select('rid', 'rtbl', 'tstamp', 'response_type', 'url_id', 'html_sent', 'size')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->orderBy('rtbl')
        ->addOrderBy('rid')
        ->addOrderBy('tstamp')
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function findAllReturnedMail(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('rid', 'rtbl', 'email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(-127, Connection::PARAM_INT)
            )
        )
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function findUnknownRecipient(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('rid', 'rtbl', 'email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(-127, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->or(
                $queryBuilder->expr()->eq(
                    'return_code',
                    $queryBuilder->createNamedParameter(550, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'return_code',
                    $queryBuilder->createNamedParameter(553, Connection::PARAM_INT)
                )
            )
        )
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function findMailboxFull(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('rid', 'rtbl', 'email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(-127, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'return_code',
                $queryBuilder->createNamedParameter(551, Connection::PARAM_INT)
            )
        )
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function findBadHost(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('rid', 'rtbl', 'email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(-127, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'return_code',
                $queryBuilder->createNamedParameter(552, Connection::PARAM_INT)
            )
        )
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function findBadHeader(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('rid', 'rtbl', 'email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(-127, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'return_code',
                $queryBuilder->createNamedParameter(554, Connection::PARAM_INT)
            )
        )
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function findUnknownReasons(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder->select('rid', 'rtbl', 'email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'mid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'response_type',
                $queryBuilder->createNamedParameter(-127, Connection::PARAM_INT)
            )
        )
        ->andWhere(
            $queryBuilder->expr()->eq(
                'return_code',
                $queryBuilder->createNamedParameter(-1, Connection::PARAM_INT)
            )
        )
        ->execute()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectForAnalyzeBounceMail(int $rid, string $rtbl, int $mid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('uid', 'email')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq(
                    'rid',
                    $queryBuilder->createNamedParameter($rid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'rtbl',
                    $queryBuilder->createNamedParameter($rtbl, Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'mid',
                    $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'response_type',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
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
                $queryBuilder->expr()->eq(
                    'mid',
                    $queryBuilder->createNamedParameter($mailLogParameters['mid'], Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'url',
                    $queryBuilder->createNamedParameter($mailLogParameters['url'], Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'response_type',
                    $queryBuilder->createNamedParameter($mailLogParameters['response_type'], Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'url_id',
                    $queryBuilder->createNamedParameter($mailLogParameters['url_id'], Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'rtbl',
                    $queryBuilder->createNamedParameter($mailLogParameters['rtbl'], Connection::PARAM_STR)
                ),
                $queryBuilder->expr()->eq(
                    'rid',
                    $queryBuilder->createNamedParameter($mailLogParameters['rid'], Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->lte(
                    'tstamp',
                    $queryBuilder->createNamedParameter($mailLogParameters['tstamp'], Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->gte(
                    'tstamp',
                    $queryBuilder->createNamedParameter($mailLogParameters['tstamp']-10, Connection::PARAM_INT)
                )
            );

        $existingLog = $query->execute()->fetchOne();

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
                    $queryBuilder->createNamedParameter($values['logUid'], Connection::PARAM_INT)
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
            ->where(
                $queryBuilder->expr()->eq(
                    'rid',
                    $queryBuilder->createNamedParameter($rid, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'rtbl',
                    $queryBuilder->createNamedParameter($rtbl)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'mid',
                    $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'response_type',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
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
     * @return int True on success or False on error
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

    public function analyzeBounceMailAddToMailLog(
        int $tstamp,
        array $midArray,
        int $returnCode,
        string $returnContent
    ) {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->insert($this->table)
            ->values([
                'tstamp' => $tstamp,
                'response_type' => -127,
                'mid' => (int)$midArray['mid'],
                'rid' => (int)$midArray['rid'],
                'email' => $midArray['email'],
                'rtbl' => $midArray['rtbl'],
                'return_content' => $returnContent,
                'return_code' => $returnCode,
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
            ->where(
                $queryBuilder->expr()->eq(
                    'mid',
                    $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'rtbl',
                    $queryBuilder->createNamedParameter($rtbl)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'response_type',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            ->execute();

        $list = '';

        while ($row = $statement->fetchAssociative()) {
            $list .= $row['rid'] . ',';
        }

        return rtrim($list, ',');
    }
}
