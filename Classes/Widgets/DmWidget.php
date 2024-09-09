<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Widgets;


use DirectMailTeam\DirectMail\Widgets\Provider\DmProvider;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;


class DmWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;
    public function __construct(
        protected readonly WidgetConfigurationInterface $configuration,
        protected readonly DmProvider $dataProvider,
        protected readonly BackendViewFactory $backendViewFactory,
        protected array $options = []
    ) {
    }

    public function renderWidgetContent(): string
    {
        return $this->backendViewFactory
            ->create($this->request, ['typo3/cms-dashboard', 'directmailteam/direct-mail'])
            ->assignMultiple([
                'items' => $this->dataProvider->getDmPages(),
                'configuration' => $this->configuration,
                'options' => $this->options,
            ])
            ->render('Dashboard/Widgets/DmWidget');
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }
}
