<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class RdctUtility
{
    public function installed(): bool
    {
        return class_exists('\FoT3\Rdct\Redirects');
    }

    public function getRedirects(): \FoT3\Rdct\Redirects
    {
        return GeneralUtility::makeInstance(\FoT3\Rdct\Redirects::class);
    }
}
