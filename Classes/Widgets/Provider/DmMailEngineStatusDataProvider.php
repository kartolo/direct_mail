<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Widgets\Provider;

use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\WidgetApi;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;

class DmMailEngineStatusDataProvider implements ChartDataProviderInterface
{
    /** @var LanguageService */
    private $languageService;

    public function __construct(
        LanguageService $languageService
    ) {
        $this->languageService = $languageService;
    }

    public function getChartData(): array
    {
        $pages = GeneralUtility::makeInstance(DmProvider::class)->getDmPages();
        $countScheduled = 0;
        $countNotScheduled = 0;
        if (count($pages) !== 0) {
            foreach ($pages as $page) {
                $res = GeneralUtility::makeInstance(SysDmailRepository::class)->countSysDmailsByPid($page['uid'], true);
                if (isset($res['count'])) {
                    $countScheduled += $res['count'];
                }
                $res = GeneralUtility::makeInstance(SysDmailRepository::class)->countSysDmailsByPid($page['uid'], false);
                if (isset($res['count'])) {
                    $countNotScheduled += $res['count'];
                }
            }
        }

        $all = $countScheduled + $countNotScheduled;

        return [
            'labels' => [
                $this->languageService->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang.xlf:widgets.dm.label.sent') . ': ' . $countScheduled,
                $this->languageService->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang.xlf:widgets.dm.label.unsent') . ': ' . $countNotScheduled,
            ],
            'datasets' => [
                [
                    'backgroundColor' => WidgetApi::getDefaultChartColors(),
                    'data' => [
                        round($countScheduled * 100 / $all),
                        round($countNotScheduled * 100 / $all),
                    ],
                ],
            ],
        ];
    }
}
