<?php
namespace DirectMailTeam\DirectMail\Module;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class MailerEngineController extends MainController
{
    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;
    
    /**
     * @var StandaloneView
     */
    protected $view;
    
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        /**
         * Configure template paths for your backend module
         */
        $this->view = GeneralUtility::makeInstance(StandaloneView::class);
        $this->view->setTemplateRootPaths(['EXT:direct_mail/Resources/Private/Templates/']);
        $this->view->setPartialRootPaths(['EXT:direct_mail/Resources/Private/Partials/']);
        $this->view->setLayoutRootPaths(['EXT:direct_mail/Resources/Private/Layouts/']);
        $this->view->setTemplate('MailerEngine');
        
        /**
         * Render template and return html content
         */
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }
}