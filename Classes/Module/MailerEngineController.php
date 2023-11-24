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

class MailerEngineController extends MainController
{
    /**
     * for cmd == 'delete'
     * @var int
     */
    protected int $uid = 0;

    protected bool $invokeMailerEngine = false;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame_MailerEngine';

    protected function initMailerEngine(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        $this->invokeMailerEngine = (bool)($queryParams['invokeMailerEngine'] ?? false);
    }

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->view = $this->configureTemplatePaths('MailerEngine');

        $this->init($request);
        $this->initMailerEngine($request);

        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $module = $this->getModulName();

            if ($module == 'dmail') {
                if ($this->cmd == 'delete' && $this->uid) {
                    $this->deleteDMail($this->uid);
                }

                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $mailerEngine = $this->mailerengine();

                    $this->view->assignMultiple(
                        [
                            'schedulerTable' => $this->getSchedulerTable(),
                            'data' => $mailerEngine['data'],
                            'id' => $this->id,
                            'invoke' => $mailerEngine['invoke'],
                            'moduleName' => $this->moduleName,
                            'moduleUrl' => $mailerEngine['moduleUrl'],
                            'show' => true,
                        ]
                    );
                } elseif ($this->id != 0) {
                    $message = $this->createFlashMessage($this->getLanguageService()->getLL('dmail_noRegular'), $this->getLanguageService()->getLL('dmail_newsletters'), 1, false);
                    $this->messageQueue->addMessage($message);
                }
            } else {
                $message = $this->createFlashMessage($this->getLanguageService()->getLL('select_folder'), $this->getLanguageService()->getLL('header_mailer'), 1, false);
                $this->messageQueue->addMessage($message);
                $this->view->assignMultiple(
                    [
                        'dmLinks' => $this->getDMPages($this->moduleName),
                    ]
                );
            }
        } else {
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

    protected function getSchedulerTable(): array
    {
        $schedulerTable = [];
        if (ExtensionManagementUtility::isLoaded('scheduler')) {
            $this->getLanguageService()->includeLLFile('EXT:scheduler/Resources/Private/Language/locallang.xlf');
            $schedulerTable = SchedulerUtility::getDMTable($this->getLanguageService());
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
            $message = $this->createFlashMessage('', $this->getLanguageService()->getLL('dmail_mailerengine_invoked'), -1, false);
            $this->messageQueue->addMessage($message);
        }

        // Invoke engine
        if ($enableTrigger) {
            $moduleUrl = $this->buildUriFromRoute(
                'DirectMailNavFrame_MailerEngine',
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
