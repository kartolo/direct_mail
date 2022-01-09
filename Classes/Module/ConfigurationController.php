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
                    $formcontent = $this->moduleContent();
                    $this->view->assignMultiple(
                        [
                            'formcontent' => $formcontent,
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
    
    /**
     * Shows the content of configuration module
     * compiling the configuration form and fill it with default values
     *
     * @return	string		The compiled content of the module.
     */
    protected function moduleContent()
    {
        $configArray[1] = [
            'box-1' => $this->getLanguageService()->getLL('configure_default_headers'),
            'from_email' => ['string', DirectMailUtility::fName('from_email'), $this->getLanguageService()->getLL('from_email.description') . '<br />' . $this->getLanguageService()->getLL('from_email.details')],
            'from_name' => ['string', DirectMailUtility::fName('from_name'), $this->getLanguageService()->getLL('from_name.description') . '<br />' . $this->getLanguageService()->getLL('from_name.details')],
            'replyto_email' => ['string', DirectMailUtility::fName('replyto_email'), $this->getLanguageService()->getLL('replyto_email.description') . '<br />' . $this->getLanguageService()->getLL('replyto_email.details')],
            'replyto_name' => ['string', DirectMailUtility::fName('replyto_name'), $this->getLanguageService()->getLL('replyto_name.description') . '<br />' . $this->getLanguageService()->getLL('replyto_name.details')],
            'return_path' => ['string', DirectMailUtility::fName('return_path'), $this->getLanguageService()->getLL('return_path.description') . '<br />' . $this->getLanguageService()->getLL('return_path.details')],
            'organisation' => ['string', DirectMailUtility::fName('organisation'), $this->getLanguageService()->getLL('organisation.description') . '<br />' . $this->getLanguageService()->getLL('organisation.details')],
            'priority' => [
                'select', DirectMailUtility::fName('priority'), 
                $this->getLanguageService()->getLL('priority.description') . '<br />' . $this->getLanguageService()->getLL('priority.details'), 
                [
                    3 => $this->getLanguageService()->getLL('configure_priority_normal'), 
                    1 => $this->getLanguageService()->getLL('configure_priority_high'), 
                    5 => $this->getLanguageService()->getLL('configure_priority_low')]],
        ];

        // Set default values
        if (!isset($this->implodedParams['plainParams'])) {
            $this->implodedParams['plainParams'] = '&type=99';
        }
        if (!isset($this->implodedParams['quick_mail_charset'])) {
            $this->implodedParams['quick_mail_charset'] = 'utf-8';
        }
        if (!isset($this->implodedParams['direct_mail_charset'])) {
            $this->implodedParams['direct_mail_charset'] = 'iso-8859-1';
        }
        
        $this->configArray_length = count($configArray);
        $form = '';
        foreach($configArray as $cArray) {
            $form .= $this->makeConfigForm($cArray, $this->implodedParams, 'pageTS');
        }
        
        return $form;
    }
    
    /**
     * Compiling the form from an array and put in to boxes
     *
     * @param array $configArray The input array parameter
     * @param array $params Default values array
     * @param string $dataPrefix Prefix of the input field's name
     *
     * @return string The compiled input form
     */
    public function makeConfigForm(array $configArray, array $params, $dataPrefix)
    {
        $boxFlag = 0;
        
        $wrapHelp1 = '&nbsp;<a href="#" class="bubble">' .
            $this->iconFactory->getIcon('actions-system-help-open', Icon::SIZE_SMALL) .
            ' <span class="help" id="sender_email_help">';
        $wrapHelp2 = '</span></a>';
            
        $lines = [];
        if (is_array($configArray)) {
            foreach ($configArray as $fname => $config) {
                if (is_array($config)) {
                    $lines[$fname] = '<strong>' . htmlspecialchars($config[1]) . '</strong>';
                    $lines[$fname] .= $wrapHelp1 . $config[2] . $wrapHelp2 . '<br />';
                    $formEl = '';
                    switch ($config[0]) {
                        case 'string':
                        case 'short':
                            $formEl = '<input type="text" name="' . $dataPrefix . '[' . $fname . ']" value="' . htmlspecialchars($params[$fname] ?? '') . '" />';
                            break;
                        case 'check':
                            $formEl = '<input type="hidden" name="' . $dataPrefix . '[' . $fname . ']" value="0" /><input type="checkbox" name="' . $dataPrefix . '[' . $fname . ']" value="1"' . (($params[$fname] ?? '') ? ' checked="checked"' : '') . ' />';
                            break;
                        case 'select':
                            $opt = [];
                            foreach ($config[3] as $k => $v) {
                                $opt[] = '<option value="' . htmlspecialchars((string)$k) . '"' . (($params[$fname] ?? '') == $k ?' selected="selected"' : '') . '>' . htmlspecialchars($v) . '</option>';
                            }
                            $formEl = '<select name="' . $dataPrefix . '[' . $fname . ']">' . implode('', $opt) . '</select>';
                            break;
                        default:
                    }
                    $lines[$fname] .= $formEl;
                    $lines[$fname] .= '<br />';
                } else {
                    if (!strpos($fname, 'box')) {
                        $lines[$fname] ='<div id="header" class="box">
							<div class="toggleTitle">
								<a href="#" onclick="toggleDisplay(\'' . $fname . '\', event, ' . $this->configArray_length . ')">
								 	' . $this->iconFactory->getIcon('apps-pagetree-collapse', Icon::SIZE_SMALL) . '
									<strong>' . htmlspecialchars($config) . '</strong>
								</a>
							</div>
							<div id="' . $fname . '" class="toggleBox" style="display:none">';
                        $boxFlag = 1;
                    } else {
                        $lines[$fname] = '<hr />';
                        if ($config) {
                            $lines[$fname] .= '<strong>' . htmlspecialchars($config) . '</strong><br />';
                        }
                        if ($config) {
                            $lines[$fname] .= '<br />';
                        }
                    }
                }
            }
        }
        $out = implode('', $lines);
        if ($boxFlag) {
            $out .= '</div></div>';
        }
        return $out;
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
