<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Widgets;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface as Cache;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

class DmWidget implements WidgetInterface
{
    /**
     * @var WidgetConfigurationInterface
     */
    private $configuration;

    /**
     * @var StandaloneView
     */
    private $view;

    /**
     * @var Cache
     */
    #private $cache;

    /**
     * @var array
     */
    private $options;

    /**
     * @var ButtonProviderInterface|null
     */
    #private $buttonProvider;

    public function __construct(
        WidgetConfigurationInterface $configuration,
        #Cache $cache,
        StandaloneView $view,
        #ButtonProviderInterface $buttonProvider = null,
        array $options = []
    ) {
        $this->configuration = $configuration;
        $this->view = $view;
        #$this->cache = $cache;
        $this->options = [
            'limit' => 5,
        ] + $options;
        #$this->buttonProvider = $buttonProvider;
    }

    public function renderWidgetContent(): string
    {
        $this->view->setTemplate('DmWidget');
        $this->view->assignMultiple([
            'items' => [], //$this->getRssItems(),
            'options' => $this->options,
            'button' => '', //$this->getButton(),
            'configuration' => $this->configuration,
        ]);
        return $this->view->render();
    }

    protected function getRssItems(): array
    {
        $items = [];

        // Logic to populate $items array

        return $items;
    }
}
