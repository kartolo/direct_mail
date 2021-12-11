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
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $this->view = $this->configureTemplatePaths('Configuration');
        
        $this->init($request);
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $this->moduleTemplate->addJavaScriptCode($this->getJS($this->sys_dmail_uid));

            $module = $this->getModulName();

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $formcontent = $this->moduleContent();
                    $this->view->assignMultiple(
                        [
                            'formcontent' => $formcontent,
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
    
    protected function getJS($sys_dmail_uid) {
        return GeneralUtility::wrapJS('
        <script language="javascript" type="text/javascript">
        script_ended = 0;
        function jumpToUrl(URL)	{
            window.location.href = URL;
        }
        function jumpToUrlD(URL) {
            window.location.href = URL+"&sys_dmail_uid=' . $sys_dmail_uid . '";
        }
        function toggleDisplay(toggleId, e, countBox) {
            if (!e) {
                e = window.event;
            }
            if (!document.getElementById) {
                return false;
            }
            
            prefix = toggleId.split("-");
            for (i=1; i<=countBox; i++){
                newToggleId = prefix[0]+"-"+i;
                body = document.getElementById(newToggleId);
                image = document.getElementById(toggleId + "_toggle");
                if (newToggleId != toggleId){
                    if (body.style.display == "block"){
                        body.style.display = "none";
                        if (image) {
                            image.className = image.className.replace( /expand/ , "collapse");
                        }
                    }
                }
            }
            
            var body = document.getElementById(toggleId);
            if (!body) {
                return false;
            }
            var image = document.getElementById(toggleId + "_toggle");
            if (body.style.display == "none") {
                body.style.display = "block";
                if (image) {
                    image.className = image.className.replace( /collapse/ , "expand");
                }
            } else {
                body.style.display = "none";
                if (image) {
                    image.className = image.className.replace( /expand/ , "collapse");
                }
            }
            if (e) {
                // Stop the event from propagating, which
                // would cause the regular HREF link to
                // be followed, ruining our hard work.
                e.cancelBubble = true;
                if (e.stopPropagation) {
                    e.stopPropagation();
                }
            }
        }
        </script>');
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
        $configArray[2] = [
            'box-2' => $this->getLanguageService()->getLL('configure_default_content'),
            'sendOptions' => [
                'select', DirectMailUtility::fName('sendOptions'), $this->getLanguageService()->getLL('sendOptions.description') . '<br />' . $this->getLanguageService()->getLL('sendOptions.details'), 
                [
                    3 => $this->getLanguageService()->getLL('configure_plain_and_html'),
                    1 => $this->getLanguageService()->getLL('configure_plain_only'),
                    2 => $this->getLanguageService()->getLL('configure_html_only')]],
            'includeMedia' => ['check', DirectMailUtility::fName('includeMedia'), $this->getLanguageService()->getLL('includeMedia.description') . '<br />' . $this->getLanguageService()->getLL('includeMedia.details')],
            'flowedFormat' => ['check', DirectMailUtility::fName('flowedFormat'), $this->getLanguageService()->getLL('flowedFormat.description') . '<br />' . $this->getLanguageService()->getLL('flowedFormat.details')],
        ];
        $configArray[3] = [
            'box-3' => $this->getLanguageService()->getLL('configure_default_fetching'),
            'HTMLParams' => ['short', DirectMailUtility::fName('HTMLParams'), $this->getLanguageService()->getLL('configure_HTMLParams_description') . '<br />' . $this->getLanguageService()->getLL('configure_HTMLParams_details')],
            'plainParams' => ['short', DirectMailUtility::fName('plainParams'), $this->getLanguageService()->getLL('configure_plainParams_description') . '<br />' . $this->getLanguageService()->getLL('configure_plainParams_details')],
        ];
        $configArray[4] = [
            'box-4' => $this->getLanguageService()->getLL('configure_options_encoding'),
            'quick_mail_encoding' => ['select', $this->getLanguageService()->getLL('configure_quickmail_encoding'), $this->getLanguageService()->getLL('configure_quickmail_encoding_description'), ['quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit']],
            'direct_mail_encoding' => ['select', $this->getLanguageService()->getLL('configure_directmail_encoding'), $this->getLanguageService()->getLL('configure_directmail_encoding_description'), ['quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit']],
            'quick_mail_charset' => ['short', $this->getLanguageService()->getLL('configure_quickmail_charset'), $this->getLanguageService()->getLL('configure_quickmail_charset_description')],
            'direct_mail_charset' => ['short', $this->getLanguageService()->getLL('configure_directmail_charset'), $this->getLanguageService()->getLL('configure_directmail_charset_description')],
        ];
        $configArray[5] = [
            'box-5' => $this->getLanguageService()->getLL('configure_options_links'),
            'use_rdct' => ['check', DirectMailUtility::fName('use_rdct'), $this->getLanguageService()->getLL('use_rdct.description') . '<br />' . $this->getLanguageService()->getLL('use_rdct.details') . '<br />' . $this->getLanguageService()->getLL('configure_options_links_rdct')],
            'long_link_mode' => ['check', DirectMailUtility::fName('long_link_mode'), $this->getLanguageService()->getLL('long_link_mode.description')],
            'enable_jump_url' => ['check', $this->getLanguageService()->getLL('configure_options_links_jumpurl'), $this->getLanguageService()->getLL('configure_options_links_jumpurl_description')],
            'jumpurl_tracking_privacy' => ['check', $this->getLanguageService()->getLL('configure_jumpurl_tracking_privacy'), $this->getLanguageService()->getLL('configure_jumpurl_tracking_privacy_description')],
            'enable_mailto_jump_url' => ['check', $this->getLanguageService()->getLL('configure_options_mailto_jumpurl'), $this->getLanguageService()->getLL('configure_options_mailto_jumpurl_description')],
            'authcode_fieldList' => ['short', DirectMailUtility::fName('authcode_fieldList'), $this->getLanguageService()->getLL('authcode_fieldList.description')],
        ];
        $configArray[6] = [
            'box-6' => $this->getLanguageService()->getLL('configure_options_additional'),
            'http_username' => ['short', $this->getLanguageService()->getLL('configure_http_username'), $this->getLanguageService()->getLL('configure_http_username_description') . '<br />' . $this->getLanguageService()->getLL('configure_http_username_details')],
            'http_password' => ['short', $this->getLanguageService()->getLL('configure_http_password'), $this->getLanguageService()->getLL('configure_http_password_description')],
            'simulate_usergroup' => ['short', $this->getLanguageService()->getLL('configure_simulate_usergroup'), $this->getLanguageService()->getLL('configure_simulate_usergroup_description') . '<br />' . $this->getLanguageService()->getLL('configure_simulate_usergroup_details')],
            'userTable' => ['short', $this->getLanguageService()->getLL('configure_user_table'), $this->getLanguageService()->getLL('configure_user_table_description')],
            'test_tt_address_uids' => ['short', $this->getLanguageService()->getLL('configure_test_tt_address_uids'), $this->getLanguageService()->getLL('configure_test_tt_address_uids_description')],
            'test_dmail_group_uids' => ['short', $this->getLanguageService()->getLL('configure_test_dmail_group_uids'), $this->getLanguageService()->getLL('configure_test_dmail_group_uids_description')],
            'testmail' => ['short', $this->getLanguageService()->getLL('configure_testmail'), $this->getLanguageService()->getLL('configure_testmail_description')]
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
        for ($i = 1; $i <= count($configArray); $i++) {
            $form .= $this->makeConfigForm($configArray[$i], $this->implodedParams, 'pageTS');
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
            $this->moduleTemplate->getIconFactory()->getIcon('actions-system-help-open', Icon::SIZE_SMALL) .
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
                                $formEl = '<input type="text" name="' . $dataPrefix . '[' . $fname . ']" value="' . htmlspecialchars($params[$fname] ?? '') . '" style="width: 229.92px;" />';
                                break;
                            case 'check':
                                $formEl = '<input type="hidden" name="' . $dataPrefix . '[' . $fname . ']" value="0" /><input type="checkbox" name="' . $dataPrefix . '[' . $fname . ']" value="1"' . (($params[$fname] ?? '') ? ' checked="checked"' : '') . ' />';
                                break;
                            case 'comment':
                                $formEl = '';
                                break;
                            case 'select':
                                $opt = [];
                                foreach ($config[3] as $k => $v) {
                                    $opt[] = '<option value="' . htmlspecialchars($k) . '"' . (($params[$fname] ?? '') == $k ?' selected="selected"' : '') . '>' . htmlspecialchars($v) . '</option>';
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
									 	' . $this->moduleTemplate->getIconFactory()->getIcon('apps-pagetree-collapse', Icon::SIZE_SMALL) . '
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
}
