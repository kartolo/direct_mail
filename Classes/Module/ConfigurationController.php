<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Utility\TsUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Backend\Attribute\Controller;
// the module template will be initialized in handleRequest()
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Imaging\IconFactory;
use DirectMailTeam\DirectMail\Repository\PagesRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

final class ConfigurationController #extends MainController
{
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,

        protected readonly string $moduleName = 'directmail_module_configuration',
        protected readonly string $TSconfPrefix = 'mod.web_modules.dmail.',

        protected ?FlashMessageService $flashMessageService = null,
        protected ?LanguageService $languageService = null,

        protected array $pageTS = [],
        protected array $implodedParams = [],
        protected array $pageinfo = [],
        protected int $id = 0,
        protected bool $access = false,

        // ...
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->languageService = $this->getLanguageService();
        $this->languageService->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
        $this->languageService->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');

        $this->flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageAccess = BackendUtility::readPageAccess($this->id, $permsClause);
        $this->pageinfo = is_array($pageAccess) ? $pageAccess : [];
        $this->access = is_array($this->pageinfo) ? true : false;

        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->pageTS = $parsedBody['pageTS'] ?? $queryParams['pageTS'] ?? [];

        $this->updatePageTS();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        return $this->indexAction($moduleTemplate);
    }

    public function indexAction(ModuleTemplate $view): ResponseInterface
    {
        $messageQueue = $this->flashMessageService->getMessageQueueByIdentifier('ConfigurationQueue');

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
                    ]);
                } elseif ($this->id != 0) {
                    $message = $this->createFlashMessage(
                        $this->languageService->getLL('dmail_noRegular'),
                        $this->languageService->getLL('dmail_newsletters'),
                        ContextualFeedbackSeverity::WARNING,
                        false
                    );
                    $messageQueue->addMessage($message);
                }
            } else {
                $message = $this->createFlashMessage(
                    $this->languageService->getLL('select_folder'),
                    $this->languageService->getLL('header_conf'),
                    ContextualFeedbackSeverity::WARNING,
                    false
                );
                $messageQueue->addMessage($message);
                $view->assignMultiple(
                    [
                        'dmLinks' => $this->getDMPages($this->moduleName),
                    ]
                );
            }
        } else {
            $message = $this->createFlashMessage(
                $this->languageService->getLL('mod.main.no_access'),
                $this->languageService->getLL('mod.main.no_access.title'),
                ContextualFeedbackSeverity::WARNING,
                false
            );
            $messageQueue->addMessage($message);
            return $view->renderResponse('NoAccess');
        }

        return $view->renderResponse('Configuration');
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
                $notificationQueue = $this->flashMessageService->getMessageQueueByIdentifier(FlashMessageQueue::NOTIFICATION_QUEUE);
                if(GeneralUtility::makeInstance(TsUtility::class)->updatePagesTSconfig($this->id, $this->pageTS, $this->TSconfPrefix)) {
                    $message = $this->createFlashMessage(
                        $this->languageService->getLL('mod.configuration.saved'),
                        $this->languageService->getLL('mod.configuration.saved.title'),
                        ContextualFeedbackSeverity::OK,
                        false
                    );
                }
                else {
                    $message = $this->createFlashMessage(
                        $this->languageService->getLL('mod.configuration.not_saved'),
                        $this->languageService->getLL('mod.configuration.not_saved.title'),
                        ContextualFeedbackSeverity::WARNING,
                        false
                    );
                }
                $notificationQueue->enqueue($message);
            }
        }
    }

    protected function getDMPages(string $moduleName): array
    {
        $dmLinks = [];
        $rows = GeneralUtility::makeInstance(PagesRepository::class)->getDMPages();

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($this->getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', (int)$row['uid']), 2)) {
                    $dmLinks[] = [
                        'id' => $row['uid'],
                        'url' => $this->buildUriFromRoute($this->moduleName, ['id' => $row['uid'], 'updatePageTree' => '1']),
                        'title' => $row['title'],
                    ];
                }
            }
        }
        return $dmLinks;
    }

    protected function buildUriFromRoute(string $name, array $parameters = []): Uri
    {
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return $uriBuilder->buildUriFromRoute(
            $name,
            $parameters
        );
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function isAdmin(): bool
    {
        return $GLOBALS['BE_USER']->isAdmin();
    }

    protected function getModulName()
    {
        $module = $this->pageinfo['module'] ?? false;

        if (!$module && isset($this->pageinfo['pid'])) {
            $pidrec = BackendUtility::getRecord('pages', (int)$this->pageinfo['pid']);
            $module = $pidrec['module'] ?? false;
        }

        return $module;
    }

    /**
        https://api.typo3.org/main/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_messaging_1_1_abstract_message.html
        const 	NOTICE = -2
        const 	INFO = -1
        const 	OK = 0
        const 	WARNING = 1
        const 	ERROR = 2
     * @param string $messageText
     * @param string $messageHeader
     * @param int $messageType
     * @param bool $storeInSession
     */
    protected function createFlashMessage(
        string $messageText,
        string $messageHeader = '',
        ContextualFeedbackSeverity $messageType,
        bool $storeInSession = false): FlashMessage
    {
        return GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageText,
            $messageHeader, // [optional] the header
            $messageType, // [optional] the severity defaults to \TYPO3\CMS\Core\Messaging\FlashMessage::OK
            $storeInSession // [optional] whether the message should be stored in the session or only in the \TYPO3\CMS\Core\Messaging\FlashMessageQueue object (default is false)
        );
    }
}
