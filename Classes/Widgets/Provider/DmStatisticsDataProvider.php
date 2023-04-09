<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Widgets\Provider;

use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use DirectMailTeam\DirectMail\Widgets\Provider\DmProvider;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\WidgetApi;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SysLog\Type as SystemLogType;

class DmStatisticsDataProvider implements ChartDataProviderInterface
{
    public function __construct(
        $newsletters = 10
    ) {
        $this->newsletters = $newsletters;
    }

    /**
     * @var int
     */
    protected $newsletters = 10;

    /**
     * @var array
     */
    protected $labels = [];

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @inheritDoc
     */
    public function getChartData(): array
    {
        $this->getLastNewsletters();

        return [
            'labels' => $this->labels,
            'datasets' => [
                [
                    'label' => $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang.xlf:widgets.dm.label.numberSentNewsletters'),
                    'backgroundColor' => WidgetApi::getDefaultChartColors()[0],
                    'border' => 0,
                    'data' => $this->data,
                ],
            ],
        ];
    }

    protected function getLastNewsletters(int $number = 10): void
    {
        $pids = [];
        $pages = GeneralUtility::makeInstance(DmProvider::class)->getDmPages();
        if (count($pages) !== 0) {
            foreach($pages as $page) {
                $pids[] = $page['uid'];
            }
            $newsletters = GeneralUtility::makeInstance(SysDmailRepository::class)->selectSysDmailsByPids($pids, $this->newsletters);
            if (count($newsletters) !== 0) {
                foreach($newsletters as $newsletter) {
                    $this->labels[] = $newsletter['subject'] . ' ['. $newsletter['uid'] . ']';
                    $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogByMid($newsletter['uid']);
                    $counter = $res['counter'] ?? 0;
                    $this->data[] = $counter;
                }
            }
        }
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}

