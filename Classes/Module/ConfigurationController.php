<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use DirectMailTeam\DirectMail\DirectMailUtility;

class ConfigurationController extends MainController
{
    protected string $TSconfPrefix = 'mod.web_modules.dmail.';
    protected array $pageTS = [];
    
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $currentModule = 'Configuration';
        $this->view = $this->configureTemplatePaths($currentModule);
        
        $this->init($request);
        $this->initConfiguration($request);
        $this->updatePageTS();

        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $this->moduleTemplate->addJavaScriptCode($this->getJS($this->sys_dmail_uid));

            $module = $this->getModulName();

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $this->pageRenderer->addJsInlineCode($currentModule, $this->getJS($this->sys_dmail_uid));
                    $this->setDefaultValues();
                    $this->view->assignMultiple(
                        [
                            'show' => true,
                            'implodedParams' => $this->implodedParams
                        ]
                    );
                }
                elseif ($this->id != 0) {
                    $message = $this->createFlashMessage($this->getLanguageService()->getLL('dmail_noRegular'), $this->getLanguageService()->getLL('dmail_newsletters'), 1, false);
                    $this->messageQueue->addMessage($message);
                }
            }
            else {
                $message = $this->createFlashMessage($this->getLanguageService()->getLL('select_folder'), $this->getLanguageService()->getLL('header_conf'), 1, false);
                $this->messageQueue->addMessage($message);
            }
        }
        else {
            // If no access or if ID == zero
            $this->view = $this->configureTemplatePaths('NoAccess');
            $message = $this->createFlashMessage('If no access or if ID == zero', 'No Access', 1, false);
            $this->messageQueue->addMessage($message);
        }

        /**
         * Render template and return html content
         */
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }
    
    protected function initConfiguration(ServerRequestInterface $request): void {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->pageTS = $parsedBody['pageTS'] ?? $queryParams['pageTS'] ?? [];
    }
    
    protected function setDefaultValues()
    {
        if (!isset($this->implodedParams['plainParams'])) {
            $this->implodedParams['plainParams'] = '&type=99';
        }
        if (!isset($this->implodedParams['quick_mail_charset'])) {
            $this->implodedParams['quick_mail_charset'] = 'utf-8';
        }
        if (!isset($this->implodedParams['direct_mail_charset'])) {
            $this->implodedParams['direct_mail_charset'] = 'iso-8859-1';
        }
    }
    
   /**
     * Update the pageTS
     * No return value: sent header to the same page
     *
     * @return void
     */
    protected function updatePageTS()
    {
        if ($this->getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), 2)) {
            if (is_array($this->pageTS) && count($this->pageTS)) {
                DirectMailUtility::updatePagesTSconfig($this->id, $this->pageTS, $this->TSconfPrefix);
                header('Location: ' . GeneralUtility::locationHeaderUrl(GeneralUtility::getIndpEnv('REQUEST_URI')));
            }
        }
    }
}
