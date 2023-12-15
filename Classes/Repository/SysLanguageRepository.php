<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class SysLanguageRepository extends MainRepository
{
    protected string $table                = 'sys_language';
    protected string $tableStaticLanguages = 'static_languages';

    /**
     * @return array|bool
     */
    public function selectSysLanguageForSelectCategories(string $lang, $sys_language, $static_languages) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
            ->select($this->table . '.uid')
            ->from($this->table)
            ->leftJoin(
                $this->table,
                $this->tableStaticLanguages,
                $this->tableStaticLanguages,
                $queryBuilder->expr()->eq(
                    $this->table . '.language_isocode',
                    $queryBuilder->quoteIdentifier($this->tableStaticLanguages . '.lg_typo3')
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    $this->tableStaticLanguages . '.lg_typo3',
                    $queryBuilder->createNamedParameter($lang . $sys_language . $static_languages)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
