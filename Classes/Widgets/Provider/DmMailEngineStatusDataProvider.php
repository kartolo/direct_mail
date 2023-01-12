<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Widgets\Provider;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\WidgetApi;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;

class DmMailEngineStatusDataProvider implements ChartDataProviderInterface
{
    /** @var LanguageService */
    private $languageService;

    /** @var string */
    private $table;

    public function __construct(
        LanguageService $languageService,
        string $table
    ) {

        $this->languageService = $languageService;
    }

    public function getChartData(): array
    {
        return [
            'labels' => [
                'A',
                'B',
            ],
            'datasets' => [
                [
                    'backgroundColor' => WidgetApi::getDefaultChartColors(),
                    'data' => [30, 70]
                ]
            ]
        ];
    }
}

