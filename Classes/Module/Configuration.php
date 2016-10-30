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

use TYPO3\CMS\Backend\Module\BaseScriptClass;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use DirectMailTeam\DirectMail\DirectMailUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Module Configuration for tx_directmail extension.
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo <ivan.kartolo@dkd.de>
 */
class Configuration extends BaseScriptClass
{
    public $TSconfPrefix = 'mod.web_modules.dmail.';
    // Internal
    public $params = array();
    public $perms_clause = '';
    public $pageinfo = '';
    public $sys_dmail_uid;
    public $CMD;
    public $pages_uid;
    public $categories;
    public $id;
    public $implodedParams = array();
    // If set a valid user table is around
    public $userTable;
    public $sys_language_uid = 0;
    public $allowedTables = array('tt_address', 'fe_users');
    public $MCONF;
    public $cshTable;
    public $formname = 'dmailform';

    /**
     * Length of the config array.
     *
     * @var array
     */
    public $configArray_length;

    /**
     * Page Repository.
     *
     * @var \TYPO3\CMS\Frontend\Page\PageRepository
     */
    public $sys_page;

    /**
     * IconFactory for skinning.
     *
     * @var \TYPO3\CMS\Core\Imaging\IconFactory
     */
    protected $iconFactory;

    /**
     * The name of the module.
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame_Configuration';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->MCONF = array(
                'name' => $this->moduleName,
        );
    }

    /**
     * Standard initialization.
     */
    public function init()
    {
        parent::init();

        $temp = BackendUtility::getModTSconfig($this->id, 'mod.web_modules.dmail');
        if (!is_array($temp['properties'])) {
            // init empty array
            $temp['properties'] = array();
        }
        $this->params = $temp['properties'];
        $this->implodedParams = DirectMailUtility::implodeTSParams($this->params);
        if ($this->params['userTable'] && is_array($GLOBALS['TCA'][$this->params['userTable']])) {
            $this->userTable = $this->params['userTable'];
            $this->allowedTables[] = $this->userTable;
        }
        $this->MOD_MENU['dmail_mode'] = BackendUtility::unsetMenuItems($this->params, $this->MOD_MENU['dmail_mode'], 'menu.dmail_mode');

            // initialize the page selector
        $this->sys_page = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
        $this->sys_page->init(true);

        // initialize IconFactory
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);

            // initialize backend user language
        if ($this->getLanguageService()->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                'sys_language.uid',
                'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
                'static_languages.lg_typo3='.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->getLanguageService()->lang, 'static_languages').
                    BackendUtility::BEenableFields('sys_language').
                    BackendUtility::deleteClause('sys_language').
                    BackendUtility::deleteClause('static_languages')
                );
            while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
                $this->sys_language_uid = $row['uid'];
            }
            $GLOBALS['TYPO3_DB']->sql_free_result($res);
        }
            // load contextual help
        $this->cshTable = '_MOD_'.$this->MCONF['name'];
        if ($GLOBALS['BE_USER']->uc['edit_showFieldHelp']) {
            $this->getLanguageService()->loadSingleTableDescription($this->cshTable);
        }

        $this->updatePageTS();
    }

    /**
     * Entrance from the backend module. This replace the _dispatch.
     *
     * @param ServerRequestInterface $request  The request object from the backend
     * @param ResponseInterface      $response The reponse object sent to the backend
     *
     * @return ResponseInterface Return the response object
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');

        $this->init();

        $this->main();
        $this->printContent();

        $response->getBody()->write($this->content);

        return $response;
    }

    /**
     * The main function.
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
            $this->doc->form = '<form action="" method="post" name="'.$this->formname.'" enctype="multipart/form-data">';

            $this->doc->addStyleSheet('direct_mail', ExtensionManagementUtility::extRelPath('direct_mail').'/Resources/Public/StyleSheets/modules.css');

            // Add CSS
            $this->doc->inDocStylesArray['dmail'] = '.toggleTitle { width: 70%; }';

            // JavaScript
            $this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid=' .$this->sys_dmail_uid.'";
					}
					function toggleDisplay(toggleId, e, countBox) { //
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
				</script>
			';

            $this->doc->postCode = '
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = ' .intval($this->id).';
				</script>
			';

            $markers = array(
                'FLASHMESSAGES' => '',
                'CONTENT' => '',
            );

            $docHeaderButtons = array(
                'PAGEPATH' => $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.
                    GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
                'SHORTCUT' => '',
                'CSH' => BackendUtility::cshItem($this->cshTable, '', $GLOBALS['BACK_PATH']),
            );
                // shortcut icon
            if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
                $docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
            }

            $module = $this->pageinfo['module'];
            if (!$module) {
                $pidrec = BackendUtility::getRecord('pages', intval($this->pageinfo['pid']));
                $module = $pidrec['module'];
            }
            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] == 254) && ($this->pageinfo['module'] == 'dmail')) {
                    $markers['CONTENT'] = '<h1>'.$this->getLanguageService()->getLL('header_conf').'</h1>'.
                        $this->moduleContent();
                } elseif ($this->id != 0) {
                    /**
                     * Generate flash message.
                     *
                     * @var \TYPO3\CMS\Core\Messaging\FlashMessage
                     */
                    $flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                        $this->getLanguageService()->getLL('dmail_noRegular'),
                        $this->getLanguageService()->getLL('dmail_newsletters'),
                        FlashMessage::WARNING
                    );
                    $markers['FLASHMESSAGES'] = $flashMessage->render();
                }
            } else {
                $flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $this->getLanguageService()->getLL('select_folder'),
                    $this->getLanguageService()->getLL('header_conf'),
                    FlashMessage::WARNING
                );
                $markers['FLASHMESSAGES'] = $flashMessage->render();
            }

            $this->content = $this->doc->startPage($this->getLanguageService()->getLL('title'));
            $this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());
        } else {
            // If no access or if ID == zero

            $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
            $this->doc->backPath = $GLOBALS['BACK_PATH'];

            $this->content .= $this->doc->startPage($this->getLanguageService()->getLL('title'));
            $this->content .= $this->doc->header($this->getLanguageService()->getLL('title'));
            $this->content .= '<div style="padding-top: 15px;"></div>';
        }
    }

    /**
     * Prints out the module HTML.
     */
    public function printContent()
    {
        $this->content .= $this->doc->endPage();
    }

    /**
     * Shows the content of configuration module
     * compiling the configuration form and fill it with default values.
     *
     * @return string The compiled content of the module
     */
    protected function moduleContent()
    {
        $configArray[1] = array(
            'box-1' => $this->getLanguageService()->getLL('configure_default_headers'),
            'from_email' => array('string', DirectMailUtility::fName('from_email'), $this->getLanguageService()->getLL('from_email.description').'<br />'.$this->getLanguageService()->getLL('from_email.details')),
            'from_name' => array('string', DirectMailUtility::fName('from_name'), $this->getLanguageService()->getLL('from_name.description').'<br />'.$this->getLanguageService()->getLL('from_name.details')),
            'replyto_email' => array('string', DirectMailUtility::fName('replyto_email'), $this->getLanguageService()->getLL('replyto_email.description').'<br />'.$this->getLanguageService()->getLL('replyto_email.details')),
            'replyto_name' => array('string', DirectMailUtility::fName('replyto_name'), $this->getLanguageService()->getLL('replyto_name.description').'<br />'.$this->getLanguageService()->getLL('replyto_name.details')),
            'return_path' => array('string', DirectMailUtility::fName('return_path'), $this->getLanguageService()->getLL('return_path.description').'<br />'.$this->getLanguageService()->getLL('return_path.details')),
            'organisation' => array('string', DirectMailUtility::fName('organisation'), $this->getLanguageService()->getLL('organisation.description').'<br />'.$this->getLanguageService()->getLL('organisation.details')),
            'priority' => array('select', DirectMailUtility::fName('priority'), $this->getLanguageService()->getLL('priority.description').'<br />'.$this->getLanguageService()->getLL('priority.details'), array(3 => $this->getLanguageService()->getLL('configure_priority_normal'), 1 => $this->getLanguageService()->getLL('configure_priority_high'), 5 => $this->getLanguageService()->getLL('configure_priority_low'))),
        );
        $configArray[2] = array(
            'box-2' => $this->getLanguageService()->getLL('configure_default_content'),
            'sendOptions' => array('select', DirectMailUtility::fName('sendOptions'), $this->getLanguageService()->getLL('sendOptions.description').'<br />'.$this->getLanguageService()->getLL('sendOptions.details'), array(3 => $this->getLanguageService()->getLL('configure_plain_and_html'), 1 => $this->getLanguageService()->getLL('configure_plain_only'), 2 => $this->getLanguageService()->getLL('configure_html_only'))),
            'includeMedia' => array('check', DirectMailUtility::fName('includeMedia'), $this->getLanguageService()->getLL('includeMedia.description').'<br />'.$this->getLanguageService()->getLL('includeMedia.details')),
            'flowedFormat' => array('check', DirectMailUtility::fName('flowedFormat'), $this->getLanguageService()->getLL('flowedFormat.description').'<br />'.$this->getLanguageService()->getLL('flowedFormat.details')),
        );
        $configArray[3] = array(
            'box-3' => $this->getLanguageService()->getLL('configure_default_fetching'),
            'HTMLParams' => array('short', DirectMailUtility::fName('HTMLParams'), $this->getLanguageService()->getLL('configure_HTMLParams_description').'<br />'.$this->getLanguageService()->getLL('configure_HTMLParams_details')),
            'plainParams' => array('short', DirectMailUtility::fName('plainParams'), $this->getLanguageService()->getLL('configure_plainParams_description').'<br />'.$this->getLanguageService()->getLL('configure_plainParams_details')),
            'use_domain' => array('select', DirectMailUtility::fName('use_domain'), $this->getLanguageService()->getLL('use_domain.description').'<br />'.$this->getLanguageService()->getLL('use_domain.details'), array(0 => '')),
        );
        $configArray[4] = array(
            'box-4' => $this->getLanguageService()->getLL('configure_options_encoding'),
            'quick_mail_encoding' => array('select', $this->getLanguageService()->getLL('configure_quickmail_encoding'), $this->getLanguageService()->getLL('configure_quickmail_encoding_description'), array('quoted-printable' => 'quoted-printable', 'base64' => 'base64', '8bit' => '8bit')),
            'direct_mail_encoding' => array('select', $this->getLanguageService()->getLL('configure_directmail_encoding'), $this->getLanguageService()->getLL('configure_directmail_encoding_description'), array('quoted-printable' => 'quoted-printable', 'base64' => 'base64', '8bit' => '8bit')),
            'quick_mail_charset' => array('short', $this->getLanguageService()->getLL('configure_quickmail_charset'), $this->getLanguageService()->getLL('configure_quickmail_charset_description')),
            'direct_mail_charset' => array('short', $this->getLanguageService()->getLL('configure_directmail_charset'), $this->getLanguageService()->getLL('configure_directmail_charset_description')),
        );
        $configArray[5] = array(
            'box-5' => $this->getLanguageService()->getLL('configure_options_links'),
            'use_rdct' => array('check', DirectMailUtility::fName('use_rdct'), $this->getLanguageService()->getLL('use_rdct.description').'<br />'.$this->getLanguageService()->getLL('use_rdct.details').'<br />'.$this->getLanguageService()->getLL('configure_options_links_rdct')),
            'long_link_mode' => array('check', DirectMailUtility::fName('long_link_mode'), $this->getLanguageService()->getLL('long_link_mode.description')),
            'enable_jump_url' => array('check', $this->getLanguageService()->getLL('configure_options_links_jumpurl'), $this->getLanguageService()->getLL('configure_options_links_jumpurl_description')),
            'jumpurl_tracking_privacy' => array('check', $this->getLanguageService()->getLL('configure_jumpurl_tracking_privacy'), $this->getLanguageService()->getLL('configure_jumpurl_tracking_privacy_description')),
            'enable_mailto_jump_url' => array('check', $this->getLanguageService()->getLL('configure_options_mailto_jumpurl'), $this->getLanguageService()->getLL('configure_options_mailto_jumpurl_description')),
            'authcode_fieldList' => array('short', DirectMailUtility::fName('authcode_fieldList'), $this->getLanguageService()->getLL('authcode_fieldList.description')),
        );
        $configArray[6] = array(
            'box-6' => $this->getLanguageService()->getLL('configure_options_additional'),
            'http_username' => array('short', $this->getLanguageService()->getLL('configure_http_username'), $this->getLanguageService()->getLL('configure_http_username_description').'<br />'.$this->getLanguageService()->getLL('configure_http_username_details')),
            'http_password' => array('short', $this->getLanguageService()->getLL('configure_http_password'), $this->getLanguageService()->getLL('configure_http_password_description')),
            'simulate_usergroup' => array('short', $this->getLanguageService()->getLL('configure_simulate_usergroup'), $this->getLanguageService()->getLL('configure_simulate_usergroup_description').'<br />'.$this->getLanguageService()->getLL('configure_simulate_usergroup_details')),
            'userTable' => array('short', $this->getLanguageService()->getLL('configure_user_table'), $this->getLanguageService()->getLL('configure_user_table_description')),
            'test_tt_address_uids' => array('short', $this->getLanguageService()->getLL('configure_test_tt_address_uids'), $this->getLanguageService()->getLL('configure_test_tt_address_uids_description')),
            'test_dmail_group_uids' => array('short', $this->getLanguageService()->getLL('configure_test_dmail_group_uids'), $this->getLanguageService()->getLL('configure_test_dmail_group_uids_description')),
            'testmail' => array('short', $this->getLanguageService()->getLL('configure_testmail'), $this->getLanguageService()->getLL('configure_testmail_description')),
        );

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

            // Set domain selection list
        $rootline = $this->sys_page->getRootLine($this->id);
        $rootlineId = array();
        foreach ($rootline as $rArr) {
            $rootlineId[] = $rArr['uid'];
        }

        $res_domain = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
            'uid,domainName',
            'sys_domain',
            'sys_domain.pid in ('.implode(',', $rootlineId).')'.
                BackendUtility::deleteClause('sys_domain')
        );
        while (($row_domain = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_domain))) {
            $configArray[3]['use_domain']['3'][$row_domain['uid']] = $row_domain['domainName'];
        }
        $GLOBALS['TYPO3_DB']->sql_free_result($res_domain);

        $this->configArray_length = count($configArray);
        $form = '';
        for ($i = 1; $i <= count($configArray); ++$i) {
            $form .= $this->makeConfigForm($configArray[$i], $this->implodedParams, 'pageTS');
        }

        $form .= '<input type="submit" name="submit" value="Update configuration" />';

        return str_replace('Update configuration', $this->getLanguageService()->getLL('configure_update_configuration'), $form);
    }

    /**
     * Compiling the form from an array and put in to boxes.
     *
     * @param array  $configArray The input array parameter
     * @param array  $params      Default values array
     * @param string $dataPrefix  Prefix of the input field's name
     *
     * @return string The compiled input form
     */
    public function makeConfigForm(array $configArray, array $params, $dataPrefix)
    {
        $boxFlag = 0;

        $wrapHelp1 = '&nbsp;<a href="#" class="bubble">'.
            $this->iconFactory->getIcon('actions-system-help-open', Icon::SIZE_SMALL).
            ' <span class="help" id="sender_email_help">';
        $wrapHelp2 = '</span></a>';

        $lines = array();
        if (is_array($configArray)) {
            foreach ($configArray as $fname => $config) {
                if (is_array($config)) {
                    $lines[$fname] = '<strong>'.htmlspecialchars($config[1]).'</strong>';
                    $lines[$fname] .= $wrapHelp1.$config[2].$wrapHelp2.'<br />';
                    $formEl = '';
                    switch ($config[0]) {
                        case 'string':
                            // do as short
                        case 'short':
                            $formEl = '<input type="text" name="'.$dataPrefix.'['.$fname.']" value="'.htmlspecialchars($params[$fname]).'"'.$GLOBALS['TBE_TEMPLATE']->formWidth($config[0] == 'short' ? 24 : 48).' />';
                            break;
                        case 'check':
                            $formEl = '<input type="hidden" name="'.$dataPrefix.'['.$fname.']" value="0" /><input type="checkbox" name="'.$dataPrefix.'['.$fname.']" value="1"'.($params[$fname] ? ' checked="checked"' : '').' />';
                            break;
                        case 'comment':
                            $formEl = '';
                            break;
                        case 'select':
                            $opt = array();
                            foreach ($config[3] as $k => $v) {
                                $opt[] = '<option value="'.htmlspecialchars($k).'"'.($params[$fname] == $k ? ' selected="selected"' : '').'>'.htmlspecialchars($v).'</option>';
                            }
                            $formEl = '<select name="'.$dataPrefix.'['.$fname.']">'.implode('', $opt).'</select>';
                            break;
                        default:
                    }
                    $lines[$fname] .= $formEl;
                    $lines[$fname] .= '<br />';
                } else {
                    if (!strpos($fname, 'box')) {
                        $lines[$fname] = '<div id="header" class="box">
								<div class="toggleTitle">
									<a href="#" onclick="toggleDisplay(\'' .$fname.'\', event, '.$this->configArray_length.')">
									 	' .$this->iconFactory->getIcon('apps-pagetree-collapse', Icon::SIZE_SMALL).'
										<strong>' .htmlspecialchars($config).'</strong>
									</a>
								</div>
								<div id="' .$fname.'" class="toggleBox" style="display:none">';
                        $boxFlag = 1;
                    } else {
                        $lines[$fname] = '<hr />';
                        if ($config) {
                            $lines[$fname] .= '<strong>'.htmlspecialchars($config).'</strong><br />';
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
     * No return value: sent header to the same page.
     */
    public function updatePageTS()
    {
        if ($GLOBALS['BE_USER']->doesUserHaveAccess(BackendUtility::getRecord('pages', $this->id), 2)) {
            $pageTypoScript = GeneralUtility::_GP('pageTS');
            if (is_array($pageTypoScript)) {
                DirectMailUtility::updatePagesTSconfig($this->id, $pageTypoScript, $this->TSconfPrefix);
                header('Location: '.GeneralUtility::locationHeaderUrl(GeneralUtility::getIndpEnv('REQUEST_URI')));
            }
        }
    }

    /**
     * Returns LanguageService.
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
