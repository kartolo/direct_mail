<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

use DirectMailTeam\DirectMail\DmQueryGenerator;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TempRepository extends MainRepository
{
    /**
     * Get recipient DB record given on the ID
     *
     * @param array $listArr List of recipient IDs
     * @param string $table Table name
     * @param array $fields Field to be selected
     *
     * @return array recipients' data
     */
    public function fetchRecordsListValues(array $listArr, string $table, array $fields = ['uid', 'name', 'email']): array
    {
        $outListArr = [];
        if (is_array($listArr) && count($listArr)) {
            $idlist = implode(',', $listArr);

            $queryBuilder = $this->getQueryBuilder($table);
            $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

            // handle selecting multiple fields
            foreach ($fields as $i => $field) {
                if ($i) {
                    $queryBuilder->addSelect($field);
                } else {
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
            ->executeQuery();

            while ($row = $res->fetchAssociative()) {
                $outListArr[$row['uid']] = $row;
            }
        }
        return $outListArr;
    }

    /**
     * Return all uid's from $table where the $pid is in $pidList.
     * If $cat is 0 or empty, then all entries (with pid $pid) is returned else only
     * entires which are subscribing to the categories of the group with uid $group_uid is returned.
     * The relation between the recipients in $table and sys_dmail_categories is a true MM relation
     * (Must be correctly defined in TCA).
     *
     * @param string $table The table to select from
     * @param array $pidArray The pidArray
     * @param int $groupUid The groupUid.
     * @param int $cat The number of relations from sys_dmail_group to sysmail_categories
     *
     * @return  array The resulting array of uid's
     */
    public function getIdList(string $table, array $pidArray, int $groupUid, int $cat): array
    {
        $queryBuilder = $this->getQueryBuilder($table);

        if ($cat < 1) {
            $res = $queryBuilder
            ->selectLiteral('DISTINCT ' . $table . '.uid', $table . '.email')
            ->from($table)
            ->andWhere(
                $queryBuilder->expr()->and()
                ->add(
                    $queryBuilder->expr()->in(
                        $table . '.pid',
                        $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)
                    )
                )
                ->add(
                    $queryBuilder->expr()->neq(
                        $table . '.email',
                        $queryBuilder->createNamedParameter('')
                    )
                )
            )
            ->orderBy($table . '.uid')
            ->addOrderBy($table . '.email')
            ->executeQuery();
        } else {
            $mmTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];
            $res = $queryBuilder
            ->selectLiteral('DISTINCT ' . $table . '.uid', $table . '.email')
            ->from('sys_dmail_group', 'sys_dmail_group')
            ->from('sys_dmail_group_category_mm', 'g_mm')
            ->from($mmTable, 'mm_1')
            ->leftJoin(
                'mm_1',
                $table,
                $table,
                $queryBuilder->expr()->eq(
                    $table . '.uid',
                    $queryBuilder->quoteIdentifier('mm_1.uid_local')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->and()
                    ->add(
                        $queryBuilder->expr()->in(
                            $table . '.pid',
                            $queryBuilder->createNamedParameter($pidArray, Connection::PARAM_INT_ARRAY)
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            'mm_1.uid_foreign',
                            $queryBuilder->quoteIdentifier('g_mm.uid_foreign')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            'sys_dmail_group.uid',
                            $queryBuilder->quoteIdentifier('g_mm.uid_local')
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->eq(
                            'sys_dmail_group.uid',
                            $queryBuilder->createNamedParameter($groupUid, Connection::PARAM_INT)
                        )
                    )
                    ->add(
                        $queryBuilder->expr()->neq(
                            $table . '.email',
                            $queryBuilder->createNamedParameter('')
                        )
                    )
            )
            ->orderBy($table . '.uid')
            ->addOrderBy($table . '.email')
            ->executeQuery();
        }

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }

        return $outArr;
    }

    /**
     * Return all uid's from $table for a static direct mail group.
     *
     * @param string $table The table to select from
     * @param int $uid The uid of the direct_mail group
     *
     * @return array The resulting array of uid's
     */
    public function getStaticIdList(string $table, int $uid): array
    {
        $queryBuilder = $this->getQueryBuilder($table);

        $res = $queryBuilder
        ->selectLiteral('DISTINCT ' . $table . '.uid', $table . '.email')
        ->from('sys_dmail_group_mm', 'sys_dmail_group_mm')
        ->innerJoin(
            'sys_dmail_group_mm',
            'sys_dmail_group',
            'sys_dmail_group',
            $queryBuilder->expr()->eq(
                'sys_dmail_group_mm.uid_local',
                $queryBuilder->quoteIdentifier('sys_dmail_group.uid')
            )
        )
        ->innerJoin(
            'sys_dmail_group_mm',
            $table,
            $table,
            $queryBuilder->expr()->eq(
                'sys_dmail_group_mm.uid_foreign',
                $queryBuilder->quoteIdentifier($table . '.uid')
            )
        )
        ->andWhere(
            $queryBuilder->expr()->and()
            ->add(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group_mm.uid_local',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->add(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group_mm.tablenames',
                    $queryBuilder->createNamedParameter($table)
                )
            )
            ->add(
                $queryBuilder->expr()->neq(
                    $table . '.email',
                    $queryBuilder->createNamedParameter('')
                )
            )
            ->add(
                $queryBuilder->expr()->eq(
                    'sys_dmail_group.deleted',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            )
            ->add($addWhere ?? '')
        )
        ->orderBy($table . '.uid')
        ->addOrderBy($table . '.email')
        ->executeQuery();

        $outArr = [];

        while ($row = $res->fetchAssociative()) {
            $outArr[] = $row['uid'];
        }

        return $outArr;
    }

    /**
     * Construct the array of uid's from $table selected
     * by special query of mail group of such type
     *
     * @param string $table The table to select from
     * @param array $group The direct_mail group record
     *
     * @return array The resulting query.
     */
    public function getSpecialQueryIdList(DmQueryGenerator $queryGenerator, string $table, array $group): array
    {
        $outArr = [];
        if ($group['query']) {
            $select = $queryGenerator->getQueryDM((bool)$group['queryLimitDisabled']);
            //$queryGenerator->extFieldLists['queryFields'] = 'uid';
            if ($select) {
                $connection = $this->getConnection($table);
                $recipients = $connection->executeQuery($select)->fetchAllAssociative();
                foreach ($recipients as $recipient) {
                    $outArr[] = $recipient['uid'];
                }
            }
        }
        return $outArr;
    }

    /**
     * Compile the categories enables for this $row of this $table.
     *
     * @param string $table Table name
     * @param array $row Row from table
     * @param int $sysLanguageUid User language ID
     *
     * @return array the categories in an array with the cat id as keys
     */
    public function makeCategories(string $table, array $row, int $sysLanguageUid): array
    {
        $categories = [];

        $mmField = $table == 'sys_dmail_group' ? 'select_categories' : 'module_sys_dmail_category';

        $pageTsConfig = BackendUtility::getTCEFORM_TSconfig($table, $row);
        if (is_array($pageTsConfig[$mmField])) {
            $pidList = $pageTsConfig[$mmField]['PAGE_TSCONFIG_IDLIST'] ?? [];
            if ($pidList) {
                $queryBuilder = $this->getQueryBuilder('sys_dmail_category');
                $res = $queryBuilder->select('*')
                ->from('sys_dmail_category')
                ->add('where', 'sys_dmail_category.pid IN (' . str_replace(',', "','", $queryBuilder->createNamedParameter($pidList)) . ')' .
                    ' AND l18n_parent=0')
                ->executeQuery();
                while ($rowCat = $res->fetchAssociative()) {
                    if ($localizedRowCat = $this->getRecordOverlay('sys_dmail_category', $rowCat, $sysLanguageUid)) {
                        $categories[$localizedRowCat['uid']] = htmlspecialchars($localizedRowCat['category']);
                    }
                }
            }
        }
        return $categories;
    }

    /**
     * Import from t3lib_page in order to create backend version
     * Creates language-overlay for records in general
     * (where translation is found in records from the same table)
     *
     * @param string $table Table name
     * @param array $row Record to overlay. Must contain uid, pid and languageField
     * @param int $sys_language_content Language ID of the content
     *
     * @return mixed Returns the input record, possibly overlaid with a translation.
     */
    public function getRecordOverlay(string $table, array $row, int $sys_language_content)
    {
        if ($row['uid'] > 0 && $row['pid'] > 0) {
            if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
                if (!$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable']) {
                    // Will try to overlay a record only
                    // if the sys_language_content value is larger that zero.
                    if ($sys_language_content > 0) {
                        // Must be default language or [All], otherwise no overlaying:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] <= 0) {
                            // Select overlay record:
                            $queryBuilder = $this->getQueryBuilder($table);
                            $olrow = $queryBuilder->select('*')
                            ->from($table)
                            ->add('where', 'pid=' . (int)$row['pid'] .
                                ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . (int)$sys_language_content .
                                ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . (int)$row['uid'])
                            ->setMaxResults(1)/* LIMIT 1*/
                            ->executeQuery()
                            ->fetchAssociative();

                            // Merge record content by traversing all fields:
                            if (is_array($olrow)) {
                                foreach ($row as $fN => $fV) {
                                    if ($fN != 'uid' && $fN != 'pid' && isset($olrow[$fN])) {
                                        if ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'exclude' && ($GLOBALS['TCA'][$table]['l10n_mode'][$fN] != 'mergeIfNotBlank' || strcmp(trim($olrow[$fN]), ''))) {
                                            $row[$fN] = $olrow[$fN];
                                        }
                                    }
                                }
                            }

                        // Otherwise, check if sys_language_content is different from the value of the record
                        // that means a japanese site might try to display french content.
                        } elseif ($sys_language_content != $row[$GLOBALS['TCA'][$table]['ctrl']['languageField']]) {
                            unset($row);
                        }
                    } else {
                        // When default language is displayed,
                        // we never want to return a record carrying another language!:
                        if ($row[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0) {
                            unset($row);
                        }
                    }
                }
            }
        }

        return $row;
    }

    /**
     * @param string $table
     * @param int $uid
     * @return array|bool
     */
    public function selectRowsByUid(string $table, int $uid)
    {
        $queryBuilder = $this->getQueryBuilder($table);
        return $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function selectForMasssendList(string $table, string $idList, int $sendPerCycle, $sendIds)
    {
        $sendIds = $sendIds ? $sendIds : 0; //@TODO
        $queryBuilder = $this->getQueryBuilder($table);

        return $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->in('uid', $idList)
            )
            ->andWhere(
                $queryBuilder->expr()->notIn('uid', $sendIds)
            )
            ->setMaxResults($sendPerCycle)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function getListOfRecipentCategories(string $table, string $relationTable, int $uid)
    {
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(
                GeneralUtility::makeInstance(DeletedRestriction::class)
            );
        return $queryBuilder
            ->select($relationTable . '.uid_foreign')
            ->from($relationTable, $relationTable)
            ->leftJoin($relationTable, $table, $table, $relationTable . '.uid_local = ' . $table . '.uid')
            ->where(
                $queryBuilder->expr()->eq(
                    $relationTable . '.uid_local',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function getDisplayUserInfo(string $table, int $uid)
    {
        $queryBuilder = $this->getQueryBuilder($table);
        return $queryBuilder
            ->select('uid_foreign')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($uid)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function deleteOldCache(int $uid)
    {
        $table = 'cache_sys_dmail_stat';
        $connection = $this->getConnection($table);
        return $connection->delete(
            $table, // from
            [ 'mid' => $uid ] // where
        );
    }

    public function insertNewCache(array $recRec)
    {
        $table = 'cache_sys_dmail_stat';
        $connection = $this->getConnection($table);
        return $connection->insert(
            $table,
            $recRec
        );
    }

    public function updateRows(string $table, array $uidList, array $values): void
    {
        $connection = $this->getConnection($table);
        foreach ($uidList as $uid) {
            $connection->update(
                $table,
                $values,
                ['uid' => $uid]
            );
        }
    }

    public function seachDMTask()
    {
        $table = 'tx_scheduler_task';
        $queryBuilder = $this->getQueryBuilder($table);

        $searchStrNew = 'directmail:mailingqueue';
        //@TODO remove in v12
        $searchStrOld = '\DirectmailScheduler';

        $queryBuilder
            ->select('uid', 'disable', 'description', 'nextexecution', 'lastexecution_time', 'lastexecution_failure', 'lastexecution_context', 'serialized_task_object', 'serialized_executions')
            ->from($table)
            ->where(
                $queryBuilder->expr()->like(
                    'serialized_task_object',
                    $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($searchStrNew) . '%')
                )
            )
            ->orWhere(
                $queryBuilder->expr()->like(
                    'serialized_task_object',
                    $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($searchStrOld) . '%')
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
                )
            );

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }
}
