<?php
namespace DirectMailTeam\DirectMail\Module;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use DirectMailTeam\DirectMail\DirectMailUtility;

class RecipientListController extends MainController
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
    
    protected $CMD = '';
    protected $id;
    protected $sys_dmail_uid = 0;
   
    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = '';
    
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
        $this->moduleName = (string)($request->getQueryParams()['currentModule'] ?? $request->getParsedBody()['currentModule'] ?? 'DirectMailNavFrame_RecipientList');
        /*
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
        $this->view->setTemplate('RecipientList');
        
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
    
    /**
     * Show the module content
     *
     * @return string The compiled content of the module.
     */
    protected function moduleContent()
    {
        // COMMAND:
        switch ($this->CMD) {
            case 'displayUserInfo':
                $theOutput = $this->cmd_displayUserInfo();
                break;
            case 'displayMailGroup':
                $result = $this->cmd_compileMailGroup(intval(GeneralUtility::_GP('group_uid')));
                $theOutput = $this->cmd_displayMailGroup($result);
                break;
            case 'displayImport':
                /* @var $importer \DirectMailTeam\DirectMail\Importer */
                $importer = GeneralUtility::makeInstance(Importer::class);
                $importer->init($this);
                $theOutput = $importer->cmd_displayImport();
                break;
            default:
                $theOutput = $this->showExistingRecipientLists();
        }
        
        return $theOutput;
    }
    
    /**
     * Shows the existing recipient lists and shows link to create a new one or import a list
     *
     * @return string List of existing recipient list, link to create a new list and link to import
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function showExistingRecipientLists()
    {
        
        $out = '<thead>
					<th colspan="2">&nbsp;</th>
					<th>' . $this->getLanguageService()->getLL('title') . '</th>
					<th>' . $this->getLanguageService()->getLL('type') . '</th>
					<th>' . $this->getLanguageService()->getLL('description') . '</th>
					<th>' . $this->getLanguageService()->getLL('recip_group_amount') . '</th>
				</thead>';
        
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_group');
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $res = $queryBuilder->select('uid','pid','title','description','type')
        ->from('sys_dmail_group')
        ->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->id,\PDO::PARAM_INT))
            )
            ->orderBy(
                preg_replace(
                    '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                    trim($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
                    )
                )
                ->execute()
                ->fetchAll();
                
                foreach($res as $row) {
                    $result = $this->cmd_compileMailGroup(intval($row['uid']));
                    $count = 0;
                    $idLists = $result['queryInfo']['id_lists'];
                    if (is_array($idLists['tt_address'])) {
                        $count+=count($idLists['tt_address']);
                    }
                    if (is_array($idLists['fe_users'])) {
                        $count+=count($idLists['fe_users']);
                    }
                    if (is_array($idLists['PLAINLIST'])) {
                        $count+=count($idLists['PLAINLIST']);
                    }
                    if (is_array($idLists[$this->userTable])) {
                        $count+=count($idLists[$this->userTable]);
                    }
                    
                    $out .= '<tr class="db_list_normal">
					<td nowrap="nowrap">' .  $this->moduleTemplate->getIconFactory()->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL)->render() . '</td>
					<td>' . $this->editLink('sys_dmail_group', $row['uid']) . '</td>
					<td nowrap="nowrap">' . $this->linkRecip_record('<strong>' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['title'], 30)) . '</strong>&nbsp;&nbsp;', $row['uid']) . '</td>
					<td nowrap="nowrap">' . htmlspecialchars(BackendUtility::getProcessedValue('sys_dmail_group', 'type', $row['type'])) . '&nbsp;&nbsp;</td>
					<td>' . BackendUtility::getProcessedValue('sys_dmail_group', 'description', htmlspecialchars($row['description'])) . '&nbsp;&nbsp;</td>
					<td>' . $count . '</td>
				</tr>';
                }
                
                $out =' <table class="table table-striped table-hover">' . $out . '</table>';
                $theOutput = '<h3>' . $this->getLanguageService()->getLL('recip_select_mailgroup') . '</h3>' .
                    $out;
                    
                    $editOnClickLink = DirectMailUtility::getEditOnClickLink([
                        'edit' => [
                            'sys_dmail_group' => [
                                $this->id => 'new'
                            ]
                        ],
                        'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
                    ]);
                    
                    // New:
                    $out = '<a href="#" class="t3-link" onClick="' . $editOnClickLink . '">' .
                        $this->moduleTemplate->getIconFactory()->getIconForRecord('sys_dmail_group', [], Icon::SIZE_SMALL) .
                        $this->getLanguageService()->getLL('recip_create_mailgroup_msg') . '</a>';
                        $theOutput .= '<div style="padding-top: 20px;"></div>';
                        $theOutput .= '<h3>' . $this->getLanguageService()->getLL('recip_select_mailgroup') . '</h3>' .
                            $out;
                            
                            // Import
                            /** @var UriBuilder $uriBuilder */
                            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                            $moduleUrl = $uriBuilder->buildUriFromRoute(
                                $this->moduleName,
                                [
                                    'id' => $this->id,
                                    'CMD' => 'displayImport'
                                ]
                                );
                            $out = '<a class="t3-link" href="' . $moduleUrl . '">' . $this->getLanguageService()->getLL('recip_import_mailgroup_msg') . '</a>';
                            $theOutput.= '<div style="padding-top: 20px;"></div>';
                            $theOutput.= '<h3>' . $this->getLanguageService()->getLL('mailgroup_import') . '</h3>' . $out;
                            return $theOutput;
    }
    
}