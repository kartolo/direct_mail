<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Utility\TsUtility;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\Controller;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Imaging\IconFactory;
use DirectMailTeam\DirectMail\Repository\PagesRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

final class ConfigurationController extends MainController
{
    protected FlashMessageQueue $flashMessageQueue;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly PageRenderer $pageRenderer,

        protected readonly string $moduleName = 'directmail_module_configuration',
        protected readonly string $TSconfPrefix = 'mod.web_modules.dmail.',
        protected readonly string $lllFile = 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf',

        protected ?LanguageService $languageService = null,

        protected array $pageTS = [],
        protected bool $submit = false,
        protected array $implodedParams = [],
        protected array $pageinfo = [],
        protected int $id = 0,
        protected bool $access = false
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->languageService = $this->getLanguageService();
        $this->flashMessageQueue = $this->getFlashMessageQueue('ConfigurationQueue');

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageAccess = BackendUtility::readPageAccess($this->id, $permsClause);
        $this->pageinfo = is_array($pageAccess) ? $pageAccess : [];
        $this->access = is_array($this->pageinfo) ? true : false;

        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->pageTS = $parsedBody['pageTS'] ?? $queryParams['pageTS'] ?? [];
        $this->submit = isset($parsedBody['submit']) ? true : false;

        foreach(['includeMedia', 'flowedFormat', 'use_rdct', 'long_link_mode', 'enable_jump_url', 'jumpurl_tracking_privacy', 'enable_mailto_jump_url', 'showContentTitle', 'prependContentTitle'] as $checkboxName) {
            if(!isset($this->pageTS[$checkboxName])) {
                $this->pageTS[$checkboxName] = '0';
            }
        }

        $this->updatePageTS();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        return $this->indexAction($moduleTemplate);
    }

    public function indexAction(ModuleTemplate $view): ResponseInterface
    {
        // Load JavaScript via PageRenderer
        $this->pageRenderer->loadRequireJs();
        $this->pageRenderer->loadJavaScriptModule('@directmailteam/diractmail/Configuration.js');
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {

            $module = $this->getModulName();

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    // get the config from pageTS
                    $params = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['dmail.'] ?? [];
                    $this->implodedParams = GeneralUtility::makeInstance(TsUtility::class)->implodeTSParams($params);
                    $this->setDefaultValues();
                    $view->assignMultiple([
                        'implodedParams' => $this->implodedParams,
                        'uid' => $this->id
                    ]);
                } elseif ($this->id != 0) {
                    $message = $this->createFlashMessage(
                        $this->languageService->sL($this->lllFile . ':dmail_noRegular'),
                        $this->languageService->sL($this->lllFile . ':dmail_newsletters'),
                        ContextualFeedbackSeverity::WARNING,
                        false
                    );
                    $this->flashMessageQueue->addMessage($message);
                }
            } else {
                $message = $this->createFlashMessage(
                    $this->languageService->sL($this->lllFile . ':select_folder'),
                    $this->languageService->sL($this->lllFile . ':header_conf'),
                    ContextualFeedbackSeverity::WARNING,
                    false
                );
                $this->flashMessageQueue->addMessage($message);
                $view->assignMultiple(
                    [
                        'dmLinks' => $this->getDMPages($this->moduleName),
                    ]
                );
            }
        } else {
            $message = $this->createFlashMessage(
                $this->languageService->sL($this->lllFile . ':mod.main.no_access'),
                $this->languageService->sL($this->lllFile . ':mod.main.no_access.title'),
                ContextualFeedbackSeverity::WARNING,
                false
            );
            $this->flashMessageQueue->addMessage($message);
            return $view->renderResponse('NoAccess');
        }

        return $view->renderResponse('Configuration');
    }

    /**
     * @return ResponseFactory
     */
    protected function getResponseFactory(): ResponseFactoryInterface
    {
        return GeneralUtility::makeInstance(ResponseFactoryInterface::class);
    }

    public function updateConfigAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->id = (int)($request->getParsedBody()['uid'] ?? 0);
        $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageAccess = BackendUtility::readPageAccess($this->id, $permsClause);
        $this->pageinfo = is_array($pageAccess) ? $pageAccess : [];
        $this->access = is_array($this->pageinfo) ? true : false;


        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            if ($this->getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), 2)) {
                $this->languageService = $this->getLanguageService();
                $this->pageTS = $request->getParsedBody()['pageTS'] ?? [];



                foreach(['includeMedia', 'flowedFormat', 'use_rdct', 'long_link_mode', 'enable_jump_url', 'jumpurl_tracking_privacy', 'enable_mailto_jump_url', 'showContentTitle', 'prependContentTitle'] as $checkboxName) {
                    if(!isset($this->pageTS[$checkboxName])) {
                        $this->pageTS[$checkboxName] = '0';
                    }
                }

                $done = false;
                if (is_array($this->pageTS) && count($this->pageTS)) {
                    $done = GeneralUtility::makeInstance(TsUtility::class)->updatePagesTSconfig($this->id, $this->pageTS, $this->TSconfPrefix);
                }

                if ($done) {
                    $title = $this->languageService->sL($this->lllFile . ':mod.configuration.saved.title');
                    $message = $this->languageService->sL($this->lllFile . ':mod.configuration.saved');
                }
                else {
                    $title = $this->languageService->sL($this->lllFile . ':mod.configuration.not_saved');
                    $message = $this->languageService->sL($this->lllFile . ':mod.configuration.not_saved.title');
                }

                $responseFactory = $this->getResponseFactory();
                $response = $responseFactory->createResponse()->withHeader('Content-Type', 'application/json; charset=utf-8');
                $response->getBody()->write(json_encode(['result' => [
                    'title' => $title,
                    'message' => $message,
                    'type' => $done
                ]], JSON_THROW_ON_ERROR));
                return $response;

            }
        }
    }

    protected function setDefaultValues(): void
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
     */
    protected function updatePageTS(): void
    {
        if ($this->getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), 2)) {
            if (is_array($this->pageTS) && count($this->pageTS)) {
                $notificationQueue = $this->getFlashMessageQueue(FlashMessageQueue::NOTIFICATION_QUEUE);
                $done = false;
                if($this->submit) {
                    $done = GeneralUtility::makeInstance(TsUtility::class)->updatePagesTSconfig($this->id, $this->pageTS, $this->TSconfPrefix);
                }
                if($this->submit && $done) {
                    $message = $this->createFlashMessage(
                        $this->languageService->sL($this->lllFile . ':mod.configuration.saved'),
                        $this->languageService->sL($this->lllFile . ':mod.configuration.saved.title'),
                        ContextualFeedbackSeverity::OK,
                        false
                    );
                    $notificationQueue->enqueue($message);
                }
                elseif($this->submit && !$done) {
                    $message = $this->createFlashMessage(
                        $this->languageService->sL($this->lllFile . ':mod.configuration.not_saved'),
                        $this->languageService->sL($this->lllFile . ':mod.configuration.not_saved.title'),
                        ContextualFeedbackSeverity::WARNING,
                        false
                    );
                    $notificationQueue->enqueue($message);
                }
            }
        }
    }
}
