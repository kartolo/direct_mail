<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Dmailer;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class MailerEngineController extends MainController
{
    /**
     * for cmd == 'delete'
     * @var integer
     */
    protected int $uid = 0;

    protected bool $invokeMailerEngine = false;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame_MailerEngine';

    protected function initMailerEngine(ServerRequestInterface $request): void {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        $this->invokeMailerEngine = (bool)($queryParams['invokeMailerEngine'] ?? false);
    }

    public function indexAction(ServerRequestInterface $request) : ResponseInterface
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
                    $cronMonitor = $this->cronMonitor();
                    $mailerEngine = $this->mailerengine();

                    $this->view->assignMultiple(
                        [
                            'cronMonitor' => $cronMonitor,
                            'data' => $mailerEngine['data'],
                            'id' => $this->id,
                            'invoke' => $mailerEngine['invoke'],
                            'moduleName' => $this->moduleName,
                            'moduleUrl' => $mailerEngine['moduleUrl'],
                            'show' => true
                        ]
                    );
                }
                elseif ($this->id != 0) {
                    $message = $this->createFlashMessage($this->getLanguageService()->getLL('dmail_noRegular'), $this->getLanguageService()->getLL('dmail_newsletters'), 1, false);
                    $this->messageQueue->addMessage($message);
                }
            }
            else {
                $message = $this->createFlashMessage($this->getLanguageService()->getLL('select_folder'), $this->getLanguageService()->getLL('header_mailer'), 1, false);
                $this->messageQueue->addMessage($message);
                $this->view->assignMultiple(
                    [
                        'dmLinks' => $this->getDMPages($this->moduleName)
                    ]
                );
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

    /**
     * Monitor the cronjob.
     *
     * @return	string		status of the cronjob in HTML Tableformat
     */
    public function cronMonitor()
    {
        $mailerStatus = 0;
        $lastExecutionTime = 0;
        $logContent = '';
        $error = '';

        // seconds
        $cronInterval = $this->getValueFromTYPO3_CONF_VARS('cronInt') * 60;
        $lastCronjobShouldBeNewThan = (time() - $cronInterval);
        $filename = $this->getDmailerLogFilePath();
        if (file_exists($filename)) {
            $logContent = file_get_contents($filename);
            $lastExecutionTime = substr($logContent, 0, 10);
        }

        /*
         * status:
         * 	1 = ok
         * 	0 = check
         * 	-1 = cron stopped
         *
         * cron running or error (die function in dmailer_log)
         */
        if (file_exists($this->getDmailerLockFilePath())) {
            $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->selectByResponseType(0);
            if(is_array($res)) {
                foreach($res as $lastSend) {
                    if (($lastSend['tstamp'] < time()) && ($lastSend['tstamp'] > $lastCronjobShouldBeNewThan)) {
                        // cron is sending
                        $mailerStatus = 1;
                    } else {
                        // there's lock file but cron is not sending
                        $mailerStatus = -1;
                    }
                }
            }
            // cron is idle or no cron
        } elseif (strpos($logContent, 'error')) {
            // error in log file
            $mailerStatus = -1;
            $error = substr($logContent, strpos($logContent, 'error') + 7);
        } elseif (!strlen($logContent) || ($lastExecutionTime < $lastCronjobShouldBeNewThan)) {
            // cron is not set or not running
            $mailerStatus = 0;
        } else {
            // last run of cron is in the interval
            $mailerStatus = 1;
        }

        $currentDate = ' / ' . $this->getLanguageService()->getLL('dmail_mailerengine_current_time') . ' ' . BackendUtility::datetime(time()) . '. ';
        $lastRun = ' ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_lastrun') . ($lastExecutionTime ? BackendUtility::datetime($lastExecutionTime) : '-') . $currentDate;
        switch ($mailerStatus) {
            case -1:
                $message = $this->createFlashMessage(
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_warning') . ': ' . ($error ? $error : $this->getLanguageService()->getLL('dmail_mailerengine_cron_warning_msg')) . $lastRun,
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                    2,
                    false
                );
                $this->messageQueue->addMessage($message);
                break;
            case 0:
                $message = $this->createFlashMessage(
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_caution') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_caution_msg') . $lastRun,
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                    1,
                    false
                );
                $this->messageQueue->addMessage($message);
                break;
            case 1:
                $message = $this->createFlashMessage(
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_ok') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_ok_msg') . $lastRun,
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                    0,
                    false
                );
                $this->messageQueue->addMessage($message);
                break;
            default:
        }
    }

    /**
     * Shows the status of the mailer engine.
     * TODO: Should really only show some entries, or provide a browsing interface.
     *
     * @return	string		List of the mailing status
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    public function mailerengine()
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
                    'invokeMailerEngine' => 1
                ]
            );

            $invoke = true;
        }

        $data = [];
        $rows = GeneralUtility::makeInstance(SysDmailRepository::class)->selectSysDmailsByPid($this->id);
        if(is_array($rows)) {
            foreach($rows as $row) {
                $data[] = [
                    'uid'             => $row['uid'],
                    'icon'            => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
                    'subject'         => $this->linkDMail_record(htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['subject'], 100)), $row['uid']),
                    'scheduled'       => BackendUtility::datetime($row['scheduled']),
                    'scheduled_begin' => $row['scheduled_begin'] ? BackendUtility::datetime($row['scheduled_begin']) : '',
                    'scheduled_end'   => $row['scheduled_end'] ? BackendUtility::datetime($row['scheduled_end']) : '',
                    'sent'            => $this->getSysDmailMaillogsCountres($row['uid']),
                    'delete'          => $this->canDelete($row['uid'])
                ];
            }
        }
        unset($rows);

        return ['invoke' => $invoke, 'moduleUrl' => $moduleUrl, 'data' => $data];
    }

    private function getSysDmailMaillogsCountres(int $uid): int
    {
        $countres = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogs($uid);
        $count = 0;
        //@TODO
        if(is_array($countres)) {
            foreach($countres as $cRow) {
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
    public function canDelete($uid)
    {
        $dmail = BackendUtility::getRecord('sys_dmail', $uid);

        // show delete icon if newsletter hasn't been sent, or not yet finished sending
        return ($dmail['scheduled_begin'] === 0 || $dmail['scheduled_end'] === 0);
    }

    /**
     * Delete existing dmail record
     *
     * @param int $uid Record uid to be deleted
     *
     * @return void
     */
    public function deleteDMail($uid)
    {
        $table = 'sys_dmail';
        if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
            $connection = $this->getConnection($table);

            $connection->update(
                $table, // table
                [ $GLOBALS['TCA'][$table]['ctrl']['delete'] => 1 ],
                [ 'uid' => $uid ] // where
            );
        }
    }

    /**
     * Invoking the mail engine
     * This method no longer returns logs in backend modul directly
     *
     * @see		Dmailer::start
     * @see		Dmailer::runcron
     */
    public function invokeMEngine()
    {
        // TODO: remove htmlmail
        /* @var $htmlmail \DirectMailTeam\DirectMail\Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->nonCron = 1;
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
    public function linkDMail_record($str, $uid)
    {
        return $str;
        //TODO: Link to detail page for the new queue
        #return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct">'.$str.'</a>';
    }
}
