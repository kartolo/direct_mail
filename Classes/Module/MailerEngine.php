<?php
namespace DirectMailTeam\DirectMail\Module;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use DirectMailTeam\DirectMail\Utility\FlashMessageRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;


/**
 * Module Mailer-Engine for tx_directmail extension
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail

 */
class MailerEngine extends BaseScriptClass
{
    public $extKey = 'direct_mail';
    public $TSconfPrefix = 'mod.web_modules.dmail.';
    // Internal
    public $params=array();
    public $perms_clause='';
    public $pageinfo='';
    public $sys_dmail_uid;
    public $pages_uid;
    public $id;
    public $implodedParams=array();
    // If set a valid user table is around
    public $userTable;
    public $sys_language_uid = 0;
    public $allowedTables = array('tt_address','fe_users');
    public $MCONF;
    public $cshTable;
    public $formname = 'dmailform';

    /**
     * IconFactory for skinning
     * @var \TYPO3\CMS\Core\Imaging\IconFactory
     */
    protected $iconFactory;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame_MailerEngine';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->MCONF = [
            'name' => $this->moduleName
        ];
    }

    /**
     * Entrance from the backend module. This replace the _dispatch
     *
     * @param ServerRequestInterface $request The request object from the backend
     *
     * @return ResponseInterface Return the response object
     */
    public function mainAction(ServerRequestInterface $request) : ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = func_num_args() === 2 ? func_get_arg(1) : null;

        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');

        $this->init();

        $this->main();
        $this->printContent();

        if ($response !== null) {
            $response->getBody()->write($this->content);
        } else {
            // Behaviour in TYPO3 v9
            $response = new HtmlResponse($this->content);
        }
        return $response;
    }

    /**
     * The main function.
     *
     * @return	void
     */
    public function main()
    {
        $this->CMD = GeneralUtility::_GP('CMD');
        $this->pages_uid = intval(GeneralUtility::_GP('pages_uid'));
        $this->sys_dmail_uid = intval(GeneralUtility::_GP('sys_dmail_uid'));
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $access = is_array($this->pageinfo) ? 1 : 0;

        if (($this->id && $access) || ($GLOBALS['BE_USER']->user['admin'] && !$this->id)) {
            // Draw the header.
            $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
            $this->doc->backPath = $GLOBALS['BACK_PATH'];
            $this->doc->setModuleTemplate('EXT:direct_mail/Resources/Private/Templates/Module.html');
            $this->doc->form='<form action="" method="post" name="' . $this->formname . '" enctype="multipart/form-data">';

            // JavaScript
            $this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid=' . $this->sys_dmail_uid . '";
					}
				</script>
			';

            $this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = ' . intval($this->id) . ';
				</script>
			';

            $markers = array(
                'FLASHMESSAGES' => '',
                'CONTENT' => '',
            );

            $docHeaderButtons = array(
                'PAGEPATH' => $this->getLanguageService()->getLL('labels.path') . ': ' . GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
                'SHORTCUT' => '',
                'CSH' => BackendUtility::cshItem($this->cshTable, '', $GLOBALS['BACK_PATH'])
            );
            // shortcut icon
            if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
                $docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
            }

            $module = $this->pageinfo['module'];
            if (!$module) {
                $pidrec=BackendUtility::getRecord('pages', intval($this->pageinfo['pid']));
                $module=$pidrec['module'];
            }
            // Render content:
            if ($module == 'dmail') {
                if (GeneralUtility::_GP('cmd') == 'delete') {
                    $this->deleteDMail(GeneralUtility::_GP('uid'));
                }

                // Direct mail module
                if ($this->pageinfo['doktype'] == 254 && $this->pageinfo['module'] == 'dmail') {
                    $markers['CONTENT'] = '<h1>' . $this->getLanguageService()->getLL('header_mailer') . '</h1>' .
                    $this->cmd_cronMonitor() . $this->cmd_mailerengine();
                } elseif ($this->id != 0) {
                    /* @var $flashMessage FlashMessage */
                    $flashMessage = GeneralUtility::makeInstance(
                        'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                        $this->getLanguageService()->getLL('dmail_noRegular'),
                        $this->getLanguageService()->getLL('dmail_newsletters'),
                        FlashMessage::WARNING
                    );
                    $markers['FLASHMESSAGES'] = GeneralUtility::makeInstance(FlashMessageRenderer::class)->render($flashMessage);
                }
            } else {
                /* @var $flashMessage FlashMessage */
                $flashMessage = GeneralUtility::makeInstance(
                    'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $this->getLanguageService()->getLL('select_folder'),
                    $this->getLanguageService()->getLL('header_mailer'),
                    FlashMessage::WARNING
                );
                $markers['FLASHMESSAGES'] = GeneralUtility::makeInstance(FlashMessageRenderer::class)->render($flashMessage);
            }

            $this->content = $this->doc->startPage($this->getLanguageService()->getLL('title'));
            $this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());
        } else {
            // If no access or if ID == zero

            $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
            $this->doc->backPath = $GLOBALS['BACK_PATH'];

            $this->content .= $this->doc->startPage($this->getLanguageService()->getLL('title'));
            $this->content .= '<h1 class="t3js-title-inlineedit">' . htmlspecialchars($this->getLanguageService()->getLL('title')) . '</h1>'; //$this->doc->header
            $this->content .= '<div style="padding-top: 15px;"></div>';
        }
    }

    /**
     * Prints out the module HTML
     *
     * @return	void
     */
    public function printContent()
    {
        $this->content.=$this->doc->endPage();
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
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable($table);

            $connection->update(
                $table, // table
                [ $GLOBALS['TCA'][$table]['ctrl']['delete'] => 1 ],
                [ 'uid' => $uid ] // where
            );
        }
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
                    'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_warning') . ': ' . ($error ? $error : $this->getLanguageService()->getLL('dmail_mailerengine_cron_warning_msg')) . $lastRun,
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                    FlashMessage::ERROR
                );
                break;
            case 0:
                $flashMessage = GeneralUtility::makeInstance(
                    'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_caution') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_caution_msg') . $lastRun,
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                    FlashMessage::WARNING
                );
                break;
            case 1:
                $flashMessage = GeneralUtility::makeInstance(
                    'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_ok') . ': ' . $this->getLanguageService()->getLL('dmail_mailerengine_cron_ok_msg') . $lastRun,
                    $this->getLanguageService()->getLL('dmail_mailerengine_cron_status'),
                    FlashMessage::OK
                );
                break;
            default:
        }
        return GeneralUtility::makeInstance(FlashMessageRenderer::class)->render($flashMessage);
    }

    /**
     * Shows the status of the mailer engine.
     * TODO: Should really only show some entries, or provide a browsing interface.
     *
     * @return	string		List of the mailing status
     */
    public function cmd_mailerengine()
    {
        $invokeMessage = '';

        // enable manual invocation of mailer engine; enabled by default
        $enableTrigger = ! (isset($this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger']) && $this->params['menu.']['dmail_mode.']['mailengine.']['disable_trigger']);
        if ($enableTrigger && GeneralUtility::_GP('invokeMailerEngine')) {
            $this->invokeMEngine();
            /* @var $flashMessage FlashMessage */
            $flashMessage = GeneralUtility::makeInstance(
                'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                '',
                $this->getLanguageService()->getLL('dmail_mailerengine_invoked'),
                FlashMessage::INFO
            );
            $invokeMessage = GeneralUtility::makeInstance(FlashMessageRenderer::class)->render($flashMessage);
        }

        // Invoke engine
        if ($enableTrigger) {
            $invokeMessage .= '<h3>' . $this->getLanguageService()->getLL('dmail_mailerengine_manual_invoke') . '</h3>' .
                '<p>' . $this->getLanguageService()->getLL('dmail_mailerengine_manual_explain') . '<br /><br />' .
                    '<a class="t3-link" href="' . BackendUtility::getModuleUrl('DirectMailNavFrame_MailerEngine') . '&id=' . $this->id . '&invokeMailerEngine=1"><strong>' . $this->getLanguageService()->getLL('dmail_mailerengine_invoke_now') . '</strong></a>'.
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
						<td>' . $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render() . '</td>
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

    /**
     * Create delete link with trash icon
     *
     * @param int $uid Uid of the record
     *
     * @return string Link with the trash icon
     */
    public function deleteLink($uid)
    {
        $icon = $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL);
        $dmail = BackendUtility::getRecord('sys_dmail', $uid);
        if (!empty($dmail['scheduled_begin'])) {
            return '<a href="' . BackendUtility::getModuleUrl('DirectMailNavFrame_MailerEngine') . '&id=' . $this->id . '&cmd=delete&uid=' . $uid . '">' . $icon . '</a>';
        }
        return '';
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
        $htmlmail = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Dmailer');
        $htmlmail->nonCron = 1;
        $htmlmail->start();
        $htmlmail->runcron();
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
