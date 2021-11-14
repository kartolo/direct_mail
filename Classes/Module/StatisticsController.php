<?php
namespace DirectMailTeam\DirectMail\Module;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

class StatisticsController extends MainController
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
    
    protected $id = 0;
    protected $sys_dmail_uid = 0;
    
    /**
     * Constructor Method
     *
     * @var ModuleTemplate $moduleTemplate
     */
    public function __construct(ModuleTemplate $moduleTemplate = null)
    {
        $this->moduleTemplate = $moduleTemplate ?? GeneralUtility::makeInstance(ModuleTemplate::class);
    }
    
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
/**
        $this->CMD = GeneralUtility::_GP('CMD');
        $this->pages_uid = intval(GeneralUtility::_GP('pages_uid'));
        $this->sys_dmail_uid = intval(GeneralUtility::_GP('sys_dmail_uid'));
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $access = is_array($this->pageinfo) ? 1 : 0;
        
        if (($this->id && $access) || ($GLOBALS['BE_USER']->user['admin'] && !$this->id)) {
*/            

        /**
         * Configure template paths for your backend module
         */
        $this->view = GeneralUtility::makeInstance(StandaloneView::class);
        $this->view->setTemplateRootPaths(['EXT:direct_mail/Resources/Private/Templates/']);
        $this->view->setPartialRootPaths(['EXT:direct_mail/Resources/Private/Partials/']);
        $this->view->setLayoutRootPaths(['EXT:direct_mail/Resources/Private/Layouts/']);
        $this->view->setTemplate('Statistics');
        
        $formcontent = $this->moduleContent();
        $this->view->assignMultiple(
            [
                'formcontent' => $formcontent
            ]
        );
        
        /**
         * Render template and return html content
         */
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }
    
    protected function moduleContent()
    {
        $theOutput = '';
        
        if (!$this->sys_dmail_uid) {
            $theOutput = $this->displayPageInfo();
        } else {
            $table = 'sys_dmail';
            $queryBuilder = $this->getQueryBuilder($table);
            $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $res = $queryBuilder->select('*')
            ->from('sys_dmail')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->id, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($this->sys_dmail_uid, \PDO::PARAM_INT))
            )
    //      debug($statement->getSQL());
    //      debug($statement->getParameters());
            ->execute();
            
//          $this->noView = 0;
            if (($row = $res->fetch())) {
                // Set URL data for commands
                $this->setURLs($row);
                
                // COMMAND:
                switch ($this->CMD) {
                    case 'displayUserInfo':
                        $theOutput = $this->displayUserInfo();
                        break;
                    case 'stats':
                        $theOutput = $this->stats($row);
                        break;
                    default:
                        // Hook for handling of custom direct mail commands:
                        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['handledirectmailcmd-' . $this->CMD])) {
                            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['handledirectmailcmd-' . $this->CMD] as $funcRef) {
                                $params = ['pObj' => &$this];
                                $theOutput = GeneralUtility::callUserFunction($funcRef, $params, $this);
                            }
                        }
                }
            }
        }
        return $theOutput;
    }
    
    /**
     * Shows the info of a page
     *
     * @return string The infopage of the sent newsletters
     */
    protected function displayPageInfo()
    {
        // Here the dmail list is rendered:
        $table = 'sys_dmail';
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        
        $res = $queryBuilder
        ->selectLiteral('sys_dmail.uid', 'sys_dmail.subject', 'sys_dmail.scheduled', 'sys_dmail.scheduled_begin', 'sys_dmail.scheduled_end', 'COUNT(sys_dmail_maillog.mid) AS count')
        ->from('sys_dmail','sys_dmail')
        ->leftJoin(
            'sys_dmail',
            'sys_dmail_maillog',
            'sys_dmail_maillog',
            $queryBuilder->expr()->eq('sys_dmail.uid', $queryBuilder->quoteIdentifier('sys_dmail_maillog.mid'))
        )
        ->add('where','sys_dmail.pid=' . intval($this->id) .
            ' AND sys_dmail.type IN (0,1)' .
            ' AND sys_dmail.issent = 1'.
            ' AND sys_dmail_maillog.response_type=0'.
            ' AND sys_dmail_maillog.html_sent>0')
            ->groupBy('sys_dmail_maillog.mid')
            ->orderBy('sys_dmail.scheduled','DESC')
            ->addOrderBy('sys_dmail.scheduled_begin','DESC')
            ->execute()
            ->fetchAll();
        
        $onClick = '';
        if ($res) {
            $onClick = ' onClick="return confirm(' . GeneralUtility::quoteJSvalue(sprintf($this->getLanguageService()->getLL('nl_l_warning'), count($res))) . ');"';
        }
        $out = '';
        
        if ($res) {
            $out .='<table border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover">';
            $out .='<thead>
					<th>&nbsp;</th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_subject') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_scheduled') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_delivery_begun') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_delivery_ended') . '</b></th>
					<th nowrap="nowrap"><b>' . $this->getLanguageService()->getLL('stats_overview_total_sent') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_status') . '</b></th>
				</thead>';
            
            foreach ($res as $row)  {
                if (!empty($row['scheduled_begin'])) {
                    if (!empty($row['scheduled_end'])) {
                        $sent = $this->getLanguageService()->getLL('stats_overview_sent');
                    } else {
                        $sent = $this->getLanguageService()->getLL('stats_overview_sending');
                    }
                } else {
                    $sent = $this->getLanguageService()->getLL('stats_overview_queuing');
                }
                
                $out.='<tr class="db_list_normal">
					<td>' . $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render() . '</td>
					<td>' . $this->linkDMail_record(GeneralUtility::fixed_lgd_cs($row['subject'], 30) . '  ', $row['uid'], $row['subject']) . '&nbsp;&nbsp;</td>
					<td>' . BackendUtility::datetime($row['scheduled']) . '</td>
					<td>' . ($row['scheduled_begin']?BackendUtility::datetime($row['scheduled_begin']):'&nbsp;') . '</td>
					<td>' . ($row['scheduled_end']?BackendUtility::datetime($row['scheduled_end']):'&nbsp;') . '</td>
					<td>' . ($row['count']?$row['count']:'&nbsp;') . '</td>
					<td>' . $sent . '</td>
				</tr>';
            }
            $out.='</table>';
        }

        $theOutput = '<h3>' . $this->getLanguageService()->getLL('stats_overview_choose') . '</h3>' .
            $out;
            $theOutput .= '<div style="padding-top: 20px;"></div>';

        return $theOutput;
    }
    
    /**
     * Shows user's info and categories
     *
     * @return string HTML showing user's info and the categories
     */
    protected function displayUserInfo()
    {
        return 'TEST 2';
    }
    
    /**
     * Get statistics from DB and compile them.
     *
     * @param array $row DB record
     *
     * @return string Statistics of a mail
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function stats($row)
    {
        return 'TEST 3';
    }
}