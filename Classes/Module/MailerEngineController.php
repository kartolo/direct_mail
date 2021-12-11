<?php
namespace DirectMailTeam\DirectMail\Module;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class MailerEngineController extends MainController
{
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $this->view = $this->configureTemplatePaths('MailerEngine');
        
        $this->init($request);

        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $cronMonitor = $this->cmd_cronMonitor();
            $mailerEngine = $this->cmd_mailerengine();
            
            $this->view->assignMultiple(
                [
                    'cronMonitor' => $cronMonitor,
                    'mailerEngine' => $mailerEngine
                ]
            );
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
    public function cmd_cronMonitor()
    {
        $content = '';
        $mailerStatus = 0;
        $lastExecutionTime = 0;
        $logContent = '';
        
        
        // seconds
        $cronInterval = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cronInt'] * 60;
        $lastCronjobShouldBeNewThan = (time() - $cronInterval);
        
        $filename = Environment::getPublicPath() . '/typo3temp/tx_directmail_dmailer_log.txt';
        if (file_exists($filename)) {
            $logContent = file_get_contents($filename);
            $lastExecutionTime = substr($logContent, 0, 10);
        }
        
        /*
         * status:
         * 	1 = ok
         * 	0 = check
         * 	-1 = cron stopped
         */
        
        // cron running or error (die function in dmailer_log)
        if (file_exists(Environment::getPublicPath() . '/typo3temp/tx_directmail_cron.lock')) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
            $res = $queryBuilder
            ->select('uid')
            ->from('sys_dmail_maillog')
            ->where($queryBuilder->expr()->eq('response_type', $queryBuilder->createNamedParameter(0)))
            ->orderBy('tstamp','DESC')
            ->execute()
            ->fetchAll();
            
            foreach($res as $lastSend) {
                if (($lastSend['tstamp'] < time()) && ($lastSend['tstamp'] > $lastCronjobShouldBeNewThan)) {
                    // cron is sending
                    $mailerStatus = 1;
                } else {
                    // there's lock file but cron is not sending
                    $mailerStatus = -1;
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
                $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->getLanguageService()->getLL('dmail_mailerengine_cron_warning') . ': ' . ($error ? $error : $this->getLanguageService()->getLL('dmail_mailerengine_cron_warning_msg')) . $lastRun,
                $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                FlashMessage::ERROR
                );
                break;
            case 0:
                $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->getLanguageService()->getLL('dmail_mailerengine_cron_caution') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_caution_msg') . $lastRun,
                $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                FlashMessage::WARNING
                );
                break;
            case 1:
                $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                $this->getLanguageService()->getLL('dmail_mailerengine_cron_ok') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_ok_msg') . $lastRun,
                $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                FlashMessage::OK
                );
                break;
            default:
        }
        return GeneralUtility::makeInstance(FlashMessageRendererResolver::class)
        ->resolve()
        ->render([$flashMessage]);
    }
    
    /**
     * Shows the status of the mailer engine.
     * TODO: Should really only show some entries, or provide a browsing interface.
     *
     * @return	string		List of the mailing status
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    public function cmd_mailerengine()
    {
        $invokeMessage = '';
        
        // enable manual invocation of mailer engine; enabled by default
        $enableTrigger = ! (isset($this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger']) && $this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger']);
        if ($enableTrigger && GeneralUtility::_GP('invokeMailerEngine')) {
            $this->invokeMEngine();
            $invokeMessage = GeneralUtility::makeInstance(FlashMessageRendererResolver::class)
            ->resolve()
            ->render([
                GeneralUtility::makeInstance(
                    FlashMessage::class,
                    '',
                    $this->getLanguageService()->getLL('dmail_mailerengine_invoked'),
                    FlashMessage::INFO
                    )
            ]);
        }
        
        // Invoke engine
        if ($enableTrigger) {
            /** @var UriBuilder $uriBuilder */
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $moduleUrl = $uriBuilder->buildUriFromRoute(
                'DirectMailNavFrame_MailerEngine',
                [
                    'id' => $this->id,
                    'invokeMailerEngine' => 1
                ]
                );
            $invokeMessage .= '<h3>' . $this->getLanguageService()->getLL('dmail_mailerengine_manual_invoke') . '</h3>' .
                '<p>' . $this->getLanguageService()->getLL('dmail_mailerengine_manual_explain') . '<br /><br />' .
                '<a class="t3-link" href="' . $moduleUrl . '"><strong>' . $this->getLanguageService()->getLL('dmail_mailerengine_invoke_now') . '</strong></a>'.
                '</p>';
            $invokeMessage .= '<div style="padding-top: 20px;"></div>';
        }
        
        // Display mailer engine status
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail');
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $res = $queryBuilder->select('uid', 'pid', 'subject', 'scheduled', 'scheduled_begin', 'scheduled_end')
        ->from('sys_dmail')
        ->add('where','pid=' . intval($this->id) .' AND scheduled>0')
        ->orderBy('scheduled','DESC')
        ->execute()
        ->fetchAll();
        
        
        $out = '<tr class="t3-row-header">
				<td>&nbsp;</td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_subject') . '&nbsp;&nbsp;</b></td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_scheduled') . '&nbsp;&nbsp;</b></td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_delivery_begun') . '&nbsp;&nbsp;</b></td>
				<td><b>' . $this->getLanguageService()->getLL('dmail_mailerengine_delivery_ended') . '&nbsp;&nbsp;</b></td>
				<td style="text-align: center;"><b>&nbsp;' . $this->getLanguageService()->getLL('dmail_mailerengine_number_sent') . '&nbsp;</b></td>
				<td style="text-align: center;"><b>&nbsp;' . $this->getLanguageService()->getLL('dmail_mailerengine_delete') . '&nbsp;</b></td>
			</tr>';
        
        
        
        foreach ($res as $row) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
            $countres = $queryBuilder->count('*')
            ->from('sys_dmail_maillog')
            ->add('where','mid=' . intval($row['uid']) .
                ' AND response_type=0' .
                ' AND html_sent>0')
                ->execute();
                
                foreach($countres as $cRow) $count = $cRow['COUNT(*)'];
                
                $out .='<tr class="db_list_normal">
						<td>' .  $this->moduleTemplate->getIconFactory()->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render() . '</td>
						<td>' . $this->linkDMail_record(htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['subject'], 100)) . '&nbsp;&nbsp;', $row['uid']) . '</td>
						<td>' . BackendUtility::datetime($row['scheduled']) . '&nbsp;&nbsp;</td>
						<td>' . ($row['scheduled_begin']?BackendUtility::datetime($row['scheduled_begin']):'') . '&nbsp;&nbsp;</td>
						<td>' . ($row['scheduled_end']?BackendUtility::datetime($row['scheduled_end']):'') . '&nbsp;&nbsp;</td>
						<td style="text-align: center;">' . ($count?$count:'&nbsp;') . '</td>
						<td style="text-align: center;">' . $this->deleteLink($row['uid']) . '</td>
					</tr>';
        }
        
        $out = $invokeMessage . '<table class="table table-striped table-hover">' . $out . '</table>';
        return '<h3>' .  $this->getLanguageService()->getLL('dmail_mailerengine_status') .'</h3>' . $out;
    }
}