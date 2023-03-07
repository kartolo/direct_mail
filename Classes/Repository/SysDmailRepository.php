<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SysDmailRepository extends MainRepository
{
    protected string $table = 'sys_dmail';

    /**
     * @return array|bool
     */
    public function selectSysDmailById(int $sys_dmail_uid, int $pid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
        ->select('*')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($sys_dmail_uid, Connection::PARAM_INT)
            )
        )
        ->executeQuery()
        ->fetchAssociative();
    }

    public function countSysDmailsByPid(int $pid, bool $scheduled) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
        ->selectLiteral('COUNT(uid) AS count')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            )
        );

        if ($scheduled) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gt(
                    'scheduled',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'issent',
                    $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
                )
            );
        }
        else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'scheduled',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'scheduled_begin',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'issent',
                    $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
                )
            );
        }

        return $queryBuilder->executeQuery()->fetchAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectSysDmailsByPids(array $pids, int $limit = 10) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
        ->select('uid', 'pid', 'subject', 'scheduled')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->in(
                'pid',
                $queryBuilder->createNamedParameter($pids, Connection::PARAM_INT_ARRAY)
            ),
            $queryBuilder->expr()->gt(
                'scheduled',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->orderBy('scheduled', 'DESC')
        ->setMaxResults($limit)
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectSysDmailsByPid(int $pid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
        ->select('uid', 'pid', 'subject', 'scheduled', 'scheduled_begin', 'scheduled_end')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->gt(
                'scheduled',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->orderBy('scheduled', 'DESC')
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectForPageInfo(int $id) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
        ->selectLiteral('sys_dmail.uid', 'sys_dmail.subject', 'sys_dmail.scheduled', 'sys_dmail.scheduled_begin', 'sys_dmail.scheduled_end', 'COUNT(sys_dmail_maillog.mid) AS count')
        ->from($this->table, $this->table)
        ->leftJoin(
            'sys_dmail',
            'sys_dmail_maillog',
            'sys_dmail_maillog',
            $queryBuilder->expr()->eq(
                'sys_dmail.uid',
                $queryBuilder->quoteIdentifier('sys_dmail_maillog.mid')
            )
        )
        ->where(
            $queryBuilder->expr()->eq(
                'sys_dmail.pid',
                $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->in(
                'sys_dmail.type',
                $queryBuilder->createNamedParameter([0, 1], Connection::PARAM_INT_ARRAY)
            ),
            $queryBuilder->expr()->eq(
                'sys_dmail.issent',
                $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->eq(
                'sys_dmail_maillog.response_type',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->gt(
                'sys_dmail_maillog.html_sent',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->groupBy('sys_dmail_maillog.mid')
        ->orderBy('sys_dmail.scheduled', 'DESC')
        ->addOrderBy('sys_dmail.scheduled_begin', 'DESC')
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return array|bool
     */
    public function selectForMkeListDMail(int $id, string $sOrder, string $ascDesc) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
        ->select('uid', 'pid', 'subject', 'tstamp', 'issent', 'renderedsize', 'attachment', 'type')
        ->from($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->eq(
                'scheduled',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->eq(
                'issent',
                $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
            )
        )
        ->orderBy($sOrder, $ascDesc)
        ->executeQuery()
        ->fetchAllAssociative();
    }

    /**
     * @return int
     */
    public function updateSysDmail(int $uid, string $charset, string $mailContent): int
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->update($this->table)
        ->where(
            $queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
            )
        )
        ->set('issent', 0)
        ->set('charset', $charset)
        ->set('mailContent', $mailContent)
        ->set('renderedSize', strlen($mailContent))
        ->executeStatement();
    }

    /**
     * @param int $uid
     * @param array $updateData
     * @return int
     */
    public function updateSysDmailRecord(int $uid, array $updateData)
    {
        $connection = $this->getConnection($this->table);
        return $connection->update(
            $this->table, // table
            $updateData, // value array
            [ 'uid' => $uid ] // where
        );
    }

    public function selectForJumpurl(int $mailId)
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select('mailContent', 'page', 'authcode_fieldList')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($mailId, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    public function dmailerSetBeginEnd(int $mid, string $key): void
    {
        if (in_array($key, ['begin', 'end'])) {
            $queryBuilder = $this->getQueryBuilder($this->table);

            $queryBuilder
                ->update($this->table)
                ->set('scheduled_' . $key, time())
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter($mid, Connection::PARAM_INT)
                    )
                )
                ->executeStatement();
        }
    }

    public function selectForRuncron()
    {
        $queryBuilder = $this->getQueryBuilder($this->table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->neq(
                    'scheduled',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->lt(
                    'scheduled',
                    $queryBuilder->createNamedParameter(time(), Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'scheduled_end',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->notIn(
                    'type',
                    $queryBuilder->createNamedParameter([2, 3], Connection::PARAM_INT_ARRAY)
                )
            )
            ->orderBy('scheduled')
            ->executeQuery()
            ->fetchAssociative();
    }

    public function getDraftsByType(int $type)
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter($type, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function getDraftByUid(int $draftUid)
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select('*')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($draftUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
