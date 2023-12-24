<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Widgets\Provider;

use DirectMailTeam\DirectMail\Repository\PagesRepository;
use DirectMailTeam\DirectMail\Widgets\DmDataProviderInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DmProvider implements DmDataProviderInterface
{
    /**
     * @return array
     */
    public function getDmPages(): array
    {
        $dmLinks = [];
        $rows = GeneralUtility::makeInstance(PagesRepository::class)->getDMPages();

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($this->getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', (int)$row['uid']), 2)) {
                    $dmLinks[] = [
                        'uid' => $row['uid'],
                        'title' => $row['title'],
                    ];
                }
            }
        }
        return $dmLinks;
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
