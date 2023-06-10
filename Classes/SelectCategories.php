<?php

namespace DirectMailTeam\DirectMail;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use DirectMailTeam\DirectMail\Repository\SysLanguageRepository;
use DirectMailTeam\DirectMail\Repository\TempRepository;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Localize categories for backend forms
 *
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 */
class SelectCategories
{
    /**
     * Get the localization of the select field items (right-hand part of form)
     * Referenced by TCA
     * https://docs.typo3.org/m/typo3/reference-tca/12.4/en-us/ColumnsConfig/CommonProperties/ItemsProcFunc.html
     *
     * @param	array $params Array of searched translation
     */
    public function getLocalizedCategories(array &$params): void
    {
        $sys_language_uid = 0;
        $lang = $this->getLang();
        if ($lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $sysPage = GeneralUtility::makeInstance(PageRepository::class);
            $rows = GeneralUtility::makeInstance(SysLanguageRepository::class)->selectSysLanguageForSelectCategories(
                $lang,
                $sysPage->enableFields('sys_language'),
                $sysPage->enableFields('static_languages')
            );
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $sys_language_uid = (int)$row['uid'];
                }
            }
        }

        //@TODO Where can you find 'sys_language_uid' without 'static_info_tables'?
        if (is_array($params['items']) && !empty($params['items'])) {
            $table = (string)$params['config']['itemsProcFunc_config']['table'];
            $tempRepository = GeneralUtility::makeInstance(TempRepository::class);
            foreach ($params['items'] as $k => $item) {
                $rows = $tempRepository->selectRowsByUid($table, (int)$item[1]);
                if (is_array($rows)) {
                    foreach ($rows as $rowCat) {
                        if ($localizedRowCat = $tempRepository->getRecordOverlay($table, $rowCat, $sys_language_uid)) {
                            if(count($localizedRowCat)) {
                                $params['items'][$k][0] = $localizedRowCat['category'];
                            }
                        }
                    }
                }
            }
        }
    }

    protected function getLang(): string
    {
        //initialize backend user language
        $languageService = $this->getLanguageService();
        return $languageService->lang == 'default' ? 'en' : $languageService->lang;
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
