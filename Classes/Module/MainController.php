<?php
namespace DirectMailTeam\DirectMail\Module;


use TYPO3\CMS\Core\Localization\LanguageService;


class MainController {
    
    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}