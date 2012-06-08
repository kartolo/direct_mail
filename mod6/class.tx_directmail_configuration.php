<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
 *  (c) 2005-2006 Jan-Erik Revsbech <jer@moccompany.com>
 *  (c) 2006 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_tstemplate.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_static.php');

/**
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo <ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 *
 * @version 	$Id: class.tx_directmail_configuration.php 30331 2010-02-22 22:27:07Z ivankartolo $
 */

/**
 * Module Configuration for tx_directmail extension
 *
 */
class tx_directmail_configuration extends t3lib_SCbase {
	var $TSconfPrefix = 'mod.web_modules.dmail.';
	// Internal
	var $params = array();
	var $perms_clause = '';
	var $pageinfo = '';
	var $sys_dmail_uid;
	var $CMD;
	var $pages_uid;
	var $categories;
	var $id;
	var $implodedParams = array();
	var $userTable;		// If set a valid user table is around
	var $sys_language_uid = 0;
	var $allowedTables = array('tt_address','fe_users');
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';

	/**
	 * length of the config array
	 * @var array
	 */
	var $configArray_length;

	/**
	 * @var t3lib_pageSelect
	 */
	var $sys_page;

	/**
	 * standard initialization
	 *
	 * @return	void		No return value. Initializing global variables
	 */
	function init()	{
		$this->MCONF = $GLOBALS['MCONF'];

		parent::init();

		$temp = t3lib_BEfunc::getModTSconfig($this->id,'mod.web_modules.dmail');
		$this->params = $temp['properties'];
		$this->implodedParams = t3lib_BEfunc::implodeTSParams($this->params);
		if ($this->params['userTable'] && is_array($GLOBALS['TCA'][$this->params['userTable']]))	{
			$this->userTable = $this->params['userTable'];
			t3lib_div::loadTCA($this->userTable);
			$this->allowedTables[] = $this->userTable;
		}
		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize the page selector
		$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		$this->sys_page->init(true);

			// initialize backend user language
		if ($GLOBALS['LANG']->lang && t3lib_extMgm::isLoaded('static_info_tables')) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3='.$GLOBALS['TYPO3_DB']->fullQuoteStr($GLOBALS['LANG']->lang,'static_languages').
					t3lib_BEfunc::BEenableFields('sys_language').
					t3lib_BEfunc::deleteClause('sys_language').
					t3lib_BEfunc::deleteClause('static_languages')
				);
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
			}
		}
			// load contextual help
		$this->cshTable = '_MOD_'.$this->MCONF['name'];
		if ($GLOBALS["BE_USER"]->uc['edit_showFieldHelp']){
			$GLOBALS['LANG']->loadSingleTableDescription($this->cshTable);
		}

		t3lib_div::loadTCA('sys_dmail');
		$this->updatePageTS();
	}

	/**
	 * The main function.
	 *
	 * @return	void		No return value. update $this->content
	 */
	function main()	{
		$this->CMD = t3lib_div::_GP('CMD');
		$this->pages_uid = intval(t3lib_div::_GP('pages_uid'));
		$this->sys_dmail_uid = intval(t3lib_div::_GP('sys_dmail_uid'));
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($GLOBALS["BE_USER"]->user['admin'] && !$this->id))	{

			// Draw the header.
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $GLOBALS["BACK_PATH"];
			$this->doc->setModuleTemplate('EXT:direct_mail/mod3/mod_template.html');
			$this->doc->form = '<form action="" method="post" name="'.$this->formname.'" enctype="multipart/form-data">';
			$this->doc->getPageRenderer()->addCssFile('../Resources/Public/StyleSheets/modules.css', 'stylesheet', 'all', '', FALSE, FALSE);

			// Add CSS
			$this->doc->inDocStyles = '
					.toggleTitle { width: 70%; }
					';

			// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid='.$this->sys_dmail_uid.'";
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
										image.src = "'.t3lib_iconWorks::skinImg($GLOBALS["BACK_PATH"], 'gfx/button_right.gif', '', 1).'";
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
								image.src = "'.t3lib_iconWorks::skinImg($GLOBALS["BACK_PATH"], 'gfx/button_down.gif', '', 1).'";
							}
						} else {
							body.style.display = "none";
							if (image) {
								image.src = "'.t3lib_iconWorks::skinImg($GLOBALS["BACK_PATH"], 'gfx/button_right.gif', '', 1).'";
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
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = '.intval($this->id).';
				</script>
			';


			$markers = array(
				'FLASHMESSAGES' => '',
				'CONTENT' => '',
			);

			$docHeaderButtons = array(
				'PAGEPATH' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
				'SHORTCUT' => '',
				'CSH' => t3lib_BEfunc::cshItem($this->cshTable, '', $GLOBALS["BACK_PATH"])
			);
				// shortcut icon
			if ($GLOBALS["BE_USER"]->mayMakeShortcut()) {
				$docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
			}

			$module = $this->pageinfo['module'];
			if (!$module) {
				$pidrec=t3lib_BEfunc::getRecord('pages',intval($this->pageinfo['pid']));
				$module=$pidrec['module'];
			}
			if ($module == 'dmail') {
						// Direct mail module
				if (($this->pageinfo['doktype'] == 254) && ($this->pageinfo['module'] == 'dmail')) {
					$markers['CONTENT'] = '<h2>' . $GLOBALS['LANG']->getLL('header_conf') . '</h2>'
					. $this->moduleContent();
				} elseif ($this->id != 0) {
					/** @var $flashMessage t3lib_FlashMessage */
					$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
						$GLOBALS['LANG']->getLL('dmail_noRegular'),
						$GLOBALS['LANG']->getLL('dmail_newsletters'),
						t3lib_FlashMessage::WARNING
					);
					$markers['FLASHMESSAGES'] = $flashMessage->render();
				}
			} else {
				$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
					$GLOBALS['LANG']->getLL('select_folder'),
					$GLOBALS['LANG']->getLL('header_conf'),
					t3lib_FlashMessage::WARNING
				);
				$markers['FLASHMESSAGES'] = $flashMessage->render();
			}


			$this->content = $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
			$this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());

		} else {
			// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $GLOBALS["BACK_PATH"];

			$this->content .= $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
			$this->content .= $this->doc->header($GLOBALS['LANG']->getLL('title'));
			$this->content .= $this->doc->spacer(5);
			$this->content .= $this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void		no return value
	 */
	function printContent()	{
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Shows the content of configuration module
	 * compiling the configuration form and fill it with default values
	 *
	 * @return	string		The compiled content of the module.
	 */
	protected function moduleContent() {
		$configArray[1] = array(
			'box-1' => $GLOBALS['LANG']->getLL('configure_default_headers'),
			'from_email' => array('string', tx_directmail_static::fName('from_email'), $GLOBALS['LANG']->getLL('from_email.description').'<br />'.$GLOBALS['LANG']->getLL('from_email.details')),
			'from_name' => array('string', tx_directmail_static::fName('from_name'), $GLOBALS['LANG']->getLL('from_name.description').'<br />'.$GLOBALS['LANG']->getLL('from_name.details')),
			'replyto_email' => array('string', tx_directmail_static::fName('replyto_email'), $GLOBALS['LANG']->getLL('replyto_email.description').'<br />'.$GLOBALS['LANG']->getLL('replyto_email.details')),
			'replyto_name' => array('string', tx_directmail_static::fName('replyto_name'), $GLOBALS['LANG']->getLL('replyto_name.description').'<br />'.$GLOBALS['LANG']->getLL('replyto_name.details')),
			'return_path' => array('string', tx_directmail_static::fName('return_path'), $GLOBALS['LANG']->getLL('return_path.description').'<br />'.$GLOBALS['LANG']->getLL('return_path.details')),
			'organisation' => array('string', tx_directmail_static::fName('organisation'), $GLOBALS['LANG']->getLL('organisation.description').'<br />'.$GLOBALS['LANG']->getLL('organisation.details')),
			'priority' => array('select', tx_directmail_static::fName('priority'), $GLOBALS['LANG']->getLL('priority.description').'<br />'.$GLOBALS['LANG']->getLL('priority.details'), array(3 => $GLOBALS['LANG']->getLL('configure_priority_normal'), 1 => $GLOBALS['LANG']->getLL('configure_priority_high'), 5 => $GLOBALS['LANG']->getLL('configure_priority_low'))),
		);
		$configArray[2] = array(
			'box-2' => $GLOBALS['LANG']->getLL('configure_default_content'),
			'sendOptions' => array('select', tx_directmail_static::fName('sendOptions'), $GLOBALS['LANG']->getLL('sendOptions.description').'<br />'.$GLOBALS['LANG']->getLL('sendOptions.details'), array(3 => $GLOBALS['LANG']->getLL('configure_plain_and_html') ,1 => $GLOBALS['LANG']->getLL('configure_plain_only') ,2 => $GLOBALS['LANG']->getLL('configure_html_only'))),
			'includeMedia' => array('check', tx_directmail_static::fName('includeMedia'), $GLOBALS['LANG']->getLL('includeMedia.description').'<br />'.$GLOBALS['LANG']->getLL('includeMedia.details')),
			'flowedFormat' => array('check', tx_directmail_static::fName('flowedFormat'), $GLOBALS['LANG']->getLL('flowedFormat.description').'<br />'.$GLOBALS['LANG']->getLL('flowedFormat.details')),
		);
		$configArray[3] = array(
			'box-3' => $GLOBALS['LANG']->getLL('configure_default_fetching'),
			'HTMLParams' => array('short', tx_directmail_static::fName('HTMLParams'), $GLOBALS['LANG']->getLL('configure_HTMLParams_description').'<br />'.$GLOBALS['LANG']->getLL('configure_HTMLParams_details')),
			'plainParams' => array('short', tx_directmail_static::fName('plainParams'), $GLOBALS['LANG']->getLL('configure_plainParams_description').'<br />'.$GLOBALS['LANG']->getLL('configure_plainParams_details')),
			'use_domain' => array('select', tx_directmail_static::fName('use_domain'), $GLOBALS['LANG']->getLL('use_domain.description').'<br />'.$GLOBALS['LANG']->getLL('use_domain.details'), array(0 => '')),
		);
		$configArray[4] = array(
			'box-4' => $GLOBALS['LANG']->getLL('configure_options_encoding'),
			'quick_mail_encoding' => array('select', $GLOBALS['LANG']->getLL('configure_quickmail_encoding'), $GLOBALS['LANG']->getLL('configure_quickmail_encoding_description'), array('quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit')),
			'direct_mail_encoding' => array('select', $GLOBALS['LANG']->getLL('configure_directmail_encoding'), $GLOBALS['LANG']->getLL('configure_directmail_encoding_description'), array('quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit')),
			'quick_mail_charset' => array('short', $GLOBALS['LANG']->getLL('configure_quickmail_charset'), $GLOBALS['LANG']->getLL('configure_quickmail_charset_description')),
			'direct_mail_charset' => array('short', $GLOBALS['LANG']->getLL('configure_directmail_charset'), $GLOBALS['LANG']->getLL('configure_directmail_charset_description')),
		);
		$configArray[5] = array(
			'box-5' => $GLOBALS['LANG']->getLL('configure_options_links'),
			'use_rdct' => array('check', tx_directmail_static::fName('use_rdct'), $GLOBALS['LANG']->getLL('use_rdct.description').'<br />'.$GLOBALS['LANG']->getLL('use_rdct.details').'<br />'.$GLOBALS['LANG']->getLL('configure_options_links_rdct')),
			'long_link_mode' => array('check', tx_directmail_static::fName('long_link_mode'), $GLOBALS['LANG']->getLL('long_link_mode.description')),
			'enable_jump_url' => array('check', $GLOBALS['LANG']->getLL('configure_options_links_jumpurl'), $GLOBALS['LANG']->getLL('configure_options_links_jumpurl_description')),
			'enable_mailto_jump_url' => array('check', $GLOBALS['LANG']->getLL('configure_options_mailto_jumpurl'), $GLOBALS['LANG']->getLL('configure_options_mailto_jumpurl_description')),
			'authcode_fieldList' => array('short', tx_directmail_static::fName('authcode_fieldList'), $GLOBALS['LANG']->getLL('authcode_fieldList.description')),
		);
		$configArray[6] = array(
			'box-6' => $GLOBALS['LANG']->getLL('configure_options_additional'),
			'http_username' => array('short', $GLOBALS['LANG']->getLL('configure_http_username'), $GLOBALS['LANG']->getLL('configure_http_username_description').'<br />'.$GLOBALS['LANG']->getLL('configure_http_username_details')),
			'http_password' => array('short', $GLOBALS['LANG']->getLL('configure_http_password'), $GLOBALS['LANG']->getLL('configure_http_password_description')),
			'userTable' => array('short', $GLOBALS['LANG']->getLL('configure_user_table'), $GLOBALS['LANG']->getLL('configure_user_table_description')),
			'test_tt_address_uids' => array('short', $GLOBALS['LANG']->getLL('configure_test_tt_address_uids'), $GLOBALS['LANG']->getLL('configure_test_tt_address_uids_description')),
			'test_dmail_group_uids' => array('short', $GLOBALS['LANG']->getLL('configure_test_dmail_group_uids'), $GLOBALS['LANG']->getLL('configure_test_dmail_group_uids_description')),
			'testmail' => array('short', $GLOBALS['LANG']->getLL('configure_testmail'), $GLOBALS['LANG']->getLL('configure_testmail_description'))
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
		$rootlineID = array();
		foreach($rootline as $rArr) {
			$rootlineID[] = $rArr['uid'];
		}

		$res_domain = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid,domainName',
			'sys_domain',
			'sys_domain.pid in ('.implode(',', $rootlineID).')'.
				t3lib_BEfunc::deleteClause('sys_domain')
		);
		while ($row_domain = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res_domain)) {
			$configArray[3]['use_domain']['3'][$row_domain['uid']] = $row_domain['domainName'];
		}
		$GLOBALS["TYPO3_DB"]->sql_free_result($res_domain);

		$this->configArray_length = count($configArray);
		$form ='';
		for ($i=1; $i <= count($configArray); $i++){
			$form .= $this->makeConfigForm($configArray[$i],$this->implodedParams,'pageTS');
		}

		$form .= '<input type="submit" name="submit" value="Update configuration" />';
		return str_replace('Update configuration', $GLOBALS['LANG']->getLL('configure_update_configuration'), $form);
	}

	/**
	 * Compiling the form from an array and put in to boxes
	 *
	 * @param	array		$configArray: the input array parameter
	 * @param	array		$params: default values array
	 * @param	string		$dataPrefix: prefix of the input field's name
	 * @return	string		the compiled input form
	 */
	function makeConfigForm($configArray,$params,$dataPrefix)	{
		$boxFlag = 0;

		$wrapHelp1 = '&nbsp;<a href="#" class="bubble"><img'.
			t3lib_iconWorks::skinImg(
				$GLOBALS["BACK_PATH"],
				'gfx/helpbubble.gif'
			).' alt="" /> <span class="help" id="sender_email_help">';
		$wrapHelp2 = '</span></a>';

		$lines = array();
		if (is_array($configArray))	{
			foreach($configArray as $fname => $config) {
				if (is_array($config))	{
					$lines[$fname] = '<strong>'.htmlspecialchars($config[1]).'</strong>';
					$lines[$fname] .= $wrapHelp1.$config[2].$wrapHelp2.'<br />';
					$formEl = "";
					switch($config[0])	{
						case 'string':
						case 'short':
							$formEl = '<input type="text" name="'.$dataPrefix.'['.$fname.']" value="'.htmlspecialchars($params[$fname]).'"'.$GLOBALS['TBE_TEMPLATE']->formWidth($config[0]=='short'?24:48).' />';
						break;
						case 'check':
							$formEl = '<input type="hidden" name="'.$dataPrefix.'['.$fname.']" value="0" /><input type="checkbox" name="'.$dataPrefix.'['.$fname.']" value="1"'.($params[$fname]?' checked="checked"':'').' />';
						break;
						case 'comment':
							$formEl = '';
						break;
						case 'select':
							$opt = array();
							foreach($config[3] as $k => $v) {
								$opt[]='<option value="'.htmlspecialchars($k).'"'.($params[$fname]==$k?' selected="selected"':'').'>'.htmlspecialchars($v).'</option>';
							}
							$formEl = '<select name="'.$dataPrefix.'['.$fname.']">'.implode('',$opt).'</select>';
						break;
						default:
							debug($config);
						break;
					}
					$lines[$fname] .= $formEl;
					$lines[$fname] .= '<br />';
				} else {
					if (!strpos($fname ,'box')){
						$imgSrc = t3lib_iconWorks::skinImg(
							$GLOBALS["BACK_PATH"],
							'gfx/button_right.gif'
						);
						$lines[$fname] ='<div id="header" class="box">
								<div class="toggleTitle">
									<a href="#" onclick="toggleDisplay(\''.$fname.'\', event, '.$this->configArray_length.')">
										<img id="'.$fname.'_toggle" '.$imgSrc.' alt="" >
										<strong>'.htmlspecialchars($config).'</strong>
									</a>
								</div>
								<div id="'.$fname.'" class="toggleBox" style="display:none">';
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
		$out = implode('',$lines);
		if($boxFlag) {
			$out .= '</div></div>';
		}
		return $out;
	}

	/**
	 * update the pageTS
	 *
	 * @return	void		no return value: sent header to the same page
	 */
	function updatePageTS()	{
		if ($GLOBALS["BE_USER"]->doesUserHaveAccess(t3lib_BEfunc::getRecord( 'pages', $this->id), 2)) {
			$pageTS = t3lib_div::_GP('pageTS');
			if (is_array($pageTS)) {
				t3lib_BEfunc::updatePagesTSconfig($this->id,$pageTS,$this->TSconfPrefix);
				header('Location: '.t3lib_div::locationHeaderUrl(t3lib_div::getIndpEnv('REQUEST_URI')));
			}
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod6/class.tx_directmail_configuration.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod6/class.tx_directmail_configuration.php']);
}

?>
