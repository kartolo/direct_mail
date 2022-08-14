<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Repository;

class TtContentCategoryMmRepository extends MainRepository {
    protected string $table = 'sys_dmail_ttcontent_category_mm';
    
    /**
     * @return array|bool
     */
    public function selectUidForeignByUid(int $uid) //: array|bool
    {
        $queryBuilder = $this->getQueryBuilder($this->table);

        return $queryBuilder
        ->select('uid_foreign')
        ->from('sys_dmail_ttcontent_category_mm')
        ->add('where', 'uid_local=' . $uid)
        ->execute()
        ->fetchAll();
    }
}