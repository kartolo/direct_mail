<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Dmailer;
use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Utility\SchedulerUtility;
use DirectMailTeam\DirectMail\Utility\Typo3ConfVarsUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Messaging\FlashMessage;
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

final class MailerEngineController extends MainController
{

    protected FlashMessageQueue $flashMessageQueue;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,

        protected readonly string $moduleName = 'directmail_module_mailerengine',
        protected readonly string $lllFile = 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf',

        protected ?LanguageService $languageService = null,

        protected array $pageinfo = [],
        protected int $id = 0,
        protected int $uid = 0, //for cmd == 'delete'
        protected int $currentPageNumber = 1,
        protected bool $access = false,
        protected bool $invokeMailerEngine = false,
        protected string $cmd = '',
        // ...
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->languageService = $this->getLanguageService();
        $this->flashMessageQueue = $this->getFlashMessageQueue('MailerEngineQueue');

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        $this->cmd = (string)($parsedBody['cmd'] ?? $queryParams['cmd'] ?? '');
        $this->invokeMailerEngine = (bool)($queryParams['invokeMailerEngine'] ?? false);
        $this->currentPageNumber = (int)($queryParams['currentPageNumber'] ?? 1);
        $this->currentPageNumber = $this->currentPageNumber > 0 ? $this->currentPageNumber : 1;
        $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageAccess = BackendUtility::readPageAccess($this->id, $permsClause);
        $this->pageinfo = is_array($pageAccess) ? $pageAccess : [];
        $this->access = is_array($this->pageinfo) ? true : false;

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        return $this->indexAction($moduleTemplate);
    }

    public function indexAction(ModuleTemplate $view): ResponseInterface
    {
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {

            $module = $this->getModulName();

            if ($module == 'dmail') {
                if ($this->cmd == 'delete' && $this->uid) {
                    $this->deleteDMail($this->uid);
                }

                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $mailerEngine = $this->mailerengine();

                    $itemsPerPage = 100; //@TODO
                    $paginator = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Pagination\ArrayPaginator::class, $mailerEngine['data'], $this->currentPageNumber, $itemsPerPage);

                    $tasks = $this->getSchedulerTable();
                    $view->assignMultiple(
                        [
                            'tasks' => $tasks['taskGroupsWithTasks'],
                            'data' => $mailerEngine['data'],
                            'pagination' => [
                                'numberOfPages' => $paginator->getNumberOfPages(),
                                'currentPageNumber' => $paginator->getCurrentPageNumber(),
                                'keyOfFirstPaginatedItem' => $paginator->getKeyOfFirstPaginatedItem(),
                                'keyOfLastPaginatedItem' => $paginator->getKeyOfLastPaginatedItem(),
                                'paginatedItems' => $paginator->getPaginatedItems(),
                                'links' =>  array_fill(0, $paginator->getNumberOfPages(), '')
                            ],
                            'id' => $this->id,
                            'invoke' => $mailerEngine['invoke'],
                            'moduleName' => $this->moduleName,
                            'moduleUrl' => $mailerEngine['moduleUrl'],
                            'show' => true,
                        ]
                    );
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
                    $this->languageService->sL($this->lllFile . ':header_mailer'),
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

        return $view->renderResponse('MailerEngine');
    }

    protected function getSchedulerTable(): array
    {
        $schedulerTable = ['taskGroupsWithTasks' => [], 'errorClasses' => []];
        if (ExtensionManagementUtility::isLoaded('scheduler')) {
            $schedulerTable = SchedulerUtility::getDMTable();
        }
        return $schedulerTable;
    }

    /**
     * Shows the status of the mailer engine.
     * TODO: Should really only show some entries, or provide a browsing interface.
     *
     * @return	string		List of the mailing status
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function mailerengine(): array
    {
        $invoke = false;
        $moduleUrl = '';

        // enable manual invocation of mailer engine; enabled by default
        $enableTrigger = ! (isset($this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger']) && $this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger']);

        if ($enableTrigger && $this->invokeMailerEngine) {
            $this->invokeMEngine();
            $message = $this->createFlashMessage(
                '',
                $this->languageService->sL($this->lllFile . ':dmail_mailerengine_invoked'),
                ContextualFeedbackSeverity::INFO,
                false
            );
            $this->flashMessageQueue->addMessage($message);
        }

        // Invoke engine
        if ($enableTrigger) {
            $moduleUrl = $this->buildUriFromRoute(
                $this->moduleName,
                [
                    'id' => $this->id,
                    'invokeMailerEngine' => 1,
                ]
            );

            $invoke = true;
        }

        $data = [];
        $rows = GeneralUtility::makeInstance(SysDmailRepository::class)->selectSysDmailsByPid($this->id);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $data[] = [
                    'uid'             => $row['uid'],
                    'icon'            => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
                    'subject'         => $this->linkDMailRecord(htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['subject'], 100)), $row['uid']),
                    'scheduled'       => BackendUtility::datetime($row['scheduled']),
                    'scheduled_begin' => $row['scheduled_begin'] ? BackendUtility::datetime($row['scheduled_begin']) : '',
                    'scheduled_end'   => $row['scheduled_end'] ? BackendUtility::datetime($row['scheduled_end']) : '',
                    'sent'            => $this->getSysDmailMaillogsCountres($row['uid']),
                    'delete'          => $this->canDelete($row['uid']),
                ];
            }
        }
        unset($rows);

        return ['invoke' => $invoke, 'moduleUrl' => $moduleUrl, 'data' => $data];
    }

    protected function getSysDmailMaillogsCountres(int $uid): int
    {
        $countres = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogs($uid);
        $count = 0;
        //@TODO
        if (is_array($countres)) {
            foreach ($countres as $cRow) {
                $count = (int)$cRow['COUNT(*)'];
            }
        }

        return $count;
    }

    /**
     * Checks if the record can be deleted
     *
     * @param int $uid Uid of the record
     * @return bool
     */
    protected function canDelete(int $uid): bool
    {
        $dmail = BackendUtility::getRecord('sys_dmail', $uid);

        // show delete icon if newsletter hasn't been sent, or not yet finished sending
        return $dmail['scheduled_begin'] === 0 || $dmail['scheduled_end'] === 0;
    }

    /**
     * Delete existing dmail record
     *
     * @param int $uid Record uid to be deleted
     */
    protected function deleteDMail(int $uid): void
    {
        $table = 'sys_dmail';
        if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
            $done = GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmailRecord($uid, [$GLOBALS['TCA'][$table]['ctrl']['delete'] => 1]);
        }
    }

    /**
     * Invoking the mail engine
     * This method no longer returns logs in backend modul directly
     *
     * @see		Dmailer::start
     * @see		Dmailer::runcron
     */
    protected function invokeMEngine(): void
    {
        // TODO: remove htmlmail
        /* @var $htmlmail \DirectMailTeam\DirectMail\Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->setNonCron(true);
        $htmlmail->start();
        $htmlmail->runcron();
    }

    /**
     * Wrapping a string with a link
     *
     * @param string $str String to be wrapped
     * @param int $uid Uid of the record
     *
     * @return string wrapped string as a link
     */
    protected function linkDMailRecord(string $str, int $uid): string
    {
        return $str;
        //TODO: Link to detail page for the new queue
        //return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct">'.$str.'</a>';
    }
}
