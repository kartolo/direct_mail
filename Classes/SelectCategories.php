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
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class SelectCategories
{
    /**
     * Get the localization of the select field items (right-hand part of form)
     * Referenced by TCA
     *
     * @param	array $params Array of searched translation
     *
     * @return	void
     */
    public function get_localized_categories(array $params)
    {
        $sys_language_uid = 0;
        $languageService = $this->getLanguageService();
        //initialize backend user language
        $lang = $languageService->lang == 'default' ? 'en' : $languageService->lang;

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

        if (is_array($params['items']) && !empty($params['items'])) {
            $table = (string)$params['config']['itemsProcFunc_config']['table'];
            $tempRepository = GeneralUtility::makeInstance(TempRepository::class);

            foreach ($params['items'] as $k => $item) {
                $rows = $tempRepository->selectRowsByUid($table, intval($item[1]));
                if (is_array($rows)) {
                    foreach ($rows as $rowCat) {
                        if ($localizedRowCat = $tempRepository->getRecordOverlay($table, $rowCat, $sys_language_uid)) {
                            $params['items'][$k][0] = $localizedRowCat['category'];
                        }
                    }
                }
            }
        }
    }

    /**
     * @return LanguageService
     */
    public function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
