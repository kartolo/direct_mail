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
        private WidgetConfigurationInterface $configuration,
        private DmProvider $dataProvider,
        private readonly BackendViewFactory $backendViewFactory,
        private array $options = []
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $view = $this->backendViewFactory->create($this->request);
        $view->assignMultiple([
            'items' => $this->dataProvider->getDmPages(),
            'options' => $this->options,
            'configuration' => $this->configuration,
        ]);
        return $view->render('Dashboard/Widgets/DmWidget');
    }

    public function getOptions(): array
    {
        return [];
    }
}
