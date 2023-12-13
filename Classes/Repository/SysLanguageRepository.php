<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class SysLanguageRepository extends MainRepository
{
    protected string $table = 'sys_language';

    /**
     * @return array|bool
     */
    public function selectSysLanguageForSelectCategories(string $lang, $sys_language, $static_languages) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select('sys_language.uid')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                'static_languages',
                'static_languages',
                $queryBuilder->expr()->eq(
                    'sys_language.language_isocode',
                    $queryBuilder->quoteIdentifier('static_languages.lg_typo3')
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'static_languages.lg_typo3',
                    $queryBuilder->createNamedParameter($lang . $sys_language . $static_languages)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
