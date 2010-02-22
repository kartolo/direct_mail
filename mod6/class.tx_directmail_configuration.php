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
 * @version 	$Id$
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   77: class tx_directmail_configuration extends t3lib_SCbase
 *  103:     function init()
 *  154:     function main()
 *  320:     function printContent()
 *  330:     function moduleContent()
 *  368:     function mailModule_main()
 *  384:     function menuConfig()
 *  420:     function cmd_conf()
 *  504:     function makeConfigForm($configArray,$defaults,$dataPrefix)
 *  582:     function cmd_convertCategories()
 *  622:     function convertCategoriesInRecords($table,$convert_confirm=0)
 *  740:     function makeCategories($table,$row)
 *  781:     function getRecordOverlay($table,$row,$sys_language_content,$OLmode='')
 *  841:     function updatePageTS()
 *  859:     function cmd_default($mode)
 *  888:     function fName($name)
 *  898:     function createNewCategories()
 *
 * TOTAL FUNCTIONS: 16
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * Module Configuration for tx_directmail extension
 *
 */
class tx_directmail_configuration extends t3lib_SCbase {
	var $extKey = 'direct_mail';
	var $TSconfPrefix = 'mod.web_modules.dmail.';
	var $fieldList='uid,name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';
	// Internal
	var $params=array();
	var $perms_clause='';
	var $pageinfo='';
	var $sys_dmail_uid;
	var $CMD;
	var $pages_uid;
	var $categories;
	var $id;
	var $implodedParams=array();
	var $userTable;		// If set a valid user table is around
	var $sys_language_uid = 0;
	var $allowedTables = array('tt_address','fe_users');
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';

	/**
	 * standard initialization
	 *
	 * @return	void		No return value. Initializing global variables
	 */
	function init()	{
		global $LANG,$BACK_PATH,$TCA,$TYPO3_CONF_VARS,$TYPO3_DB;

		$this->MCONF = $GLOBALS['MCONF'];

		parent::init();

		$temp = t3lib_BEfunc::getModTSconfig($this->id,'mod.web_modules.dmail');
		$this->params = $temp['properties'];
		$this->implodedParams = t3lib_BEfunc::implodeTSParams($this->params);
		if ($this->params['userTable'] && is_array($TCA[$this->params['userTable']]))	{
			$this->userTable = $this->params['userTable'];
			t3lib_div::loadTCA($this->userTable);
			$this->allowedTables[] = $this->userTable;
		}
		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize the page selector
		$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		$this->sys_page->init(true);

			// initialize backend user language
		if ($LANG->lang && t3lib_extMgm::isLoaded('static_info_tables')) {
			$res = $TYPO3_DB->exec_SELECTquery(
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3='.$TYPO3_DB->fullQuoteStr($LANG->lang,'static_languages').
					t3lib_BEfunc::BEenableFields('sys_language').
					t3lib_BEfunc::deleteClause('sys_language').
					t3lib_BEfunc::deleteClause('static_languages')
				);
			while($row = $TYPO3_DB->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
			}
		}
			// load contextual help
		$this->cshTable = '_MOD_'.$this->MCONF['name'];
		if ($BE_USER->uc['edit_showFieldHelp']){
			$LANG->loadSingleTableDescription($this->cshTable);
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
		global $BE_USER,$LANG,$BACK_PATH,$TCA,$TYPO3_CONF_VARS;

		$this->CMD = t3lib_div::_GP('CMD');
		$this->pages_uid=t3lib_div::_GP('pages_uid');
		$this->sys_dmail_uid=t3lib_div::_GP('sys_dmail_uid');
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id))	{

			// Draw the header.
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $BACK_PATH;
			$this->doc->form='<form action="" method="post" name="'.$this->name.'" enctype="multipart/form-data">';

			// Add CSS
			$this->doc->inDocStyles = '
					a.bubble {position:relative; z-index:24; color:#000; text-decoration:none}
					a.bubble:hover {z-index:25; background-color: #e6e8ea;}
					a.bubble span.help {display: none;}
					a.bubble:hover span.help {display:block; position:absolute; top:2em; left:2em; width:25em; border:1px solid #0cf; background-color:#cff; padding: 2px;}
					a {text-decoration: none;}
					#page-header {font-weight: bold;}
					.box {margin: 0 0 1em 1em;}
					div.toggleTitle:hover {background-color: #cfcbc7 !important;}
					div.toggleTitle a {width: 100%; position: relative; top: -3px;}
					div.toggleTitle a img {position: relative; top:4px;}
					.toggleTitle {font-weight:bold; border: 1px solid #898989; width: 70%; background-color: #e3dfdb;}
					.toggleBox {border: 1px solid #aaaaaa; background-color: '.$this->doc->bgColor4.'; padding: 1em;}
					.toggleBox h3 {background-color: '.$this->doc->bgColor4.';}
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
										image.src = "'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/button_right.gif', '', 1).'";
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
								image.src = "'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/button_down.gif', '', 1).'";
							}
						} else {
							body.style.display = "none";
							if (image) {
								image.src = "'.t3lib_iconWorks::skinImg($BACK_PATH, 'gfx/button_right.gif', '', 1).'";
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

			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = '.intval($this->id).';
				</script>
			';

			$headerSection = $this->doc->getHeader('pages',$this->pageinfo,$this->pageinfo['_thePath'],0).'<br />'.$LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],50);

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);

			$module = $this->pageinfo['module'];
			if (!$module)	{
				$pidrec=t3lib_BEfunc::getRecord('pages',intval($this->pageinfo['pid']));
				$module=$pidrec['module'];
			}
			if ($module == 'dmail') {
					// Render content:
				$this->moduleContent();
			} else {
				$this->content.=$this->doc->section($LANG->getLL('header_conf'), $LANG->getLL('select_folder'), 1, 1, 0 , TRUE);
			}

			// ShortCut
			if ($BE_USER->mayMakeShortcut())	{
				$this->content.=$this->doc->spacer(20).$this->doc->section('',$this->doc->makeShortcutIcon('id',implode(',',array_keys($this->MOD_MENU)),$this->MCONF['name']));
			}
			$this->content.=$this->doc->spacer(10);

		} else {
			// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('mediumDoc');
			$this->doc->backPath = $BACK_PATH;

			$this->content.=$this->doc->startPage($LANG->getLL('title'));
			$this->content.=$this->doc->header($LANG->getLL('title'));
			$this->content.=$this->doc->spacer(5);
			$this->content.=$this->doc->spacer(10);
		}
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void		no return value
	 */
	function printContent()	{
		$this->content.=$this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Shows the content of configuration module
	 *
	 * @return	string		The compiled content of the module.
	 */
	function moduleContent() {
		global $TYPO3_CONF_VARS, $LANG;

		if ($this->pageinfo['doktype']==254 && $this->pageinfo['module']=='dmail')	{	// Direct mail module
			$theOutput.= $this->mailModule_main();
		} elseif ($this->id!=0) {
			$theOutput.= $this->doc->section($LANG->getLL('dmail_newsletters'),'<span class="typo3-red">'.$GLOBALS['LANG']->getLL('dmail_noRegular').'</span>',0,1);
		}

		if ($this->id!=0) {
			$theOutput.=$this->doc->spacer(10);
		}
		$this->content .= $theOutput;
	}

	/**
	 * shows the form
	 *
	 * @return	string		the compiled content
	 */
	function mailModule_main()	{
		global $LANG, $TYPO3_DB, $TYPO3_CONF_VARS;

		//$theOutput.=$this->doc->divider(5);
		$mode = $this->MOD_SETTINGS['dmail_mode'];

		$theOutput.= $this->cmd_default($mode);
		return $theOutput;
	}

	/**
	 * compiling the configuration form and fill it with default values
	 *
	 * @return	string		the compiled configuration form
	 */
	function cmd_conf() {
		global $TYPO3_DB, $LANG, $BACK_PATH;

		$configArray[1] = array(
			'box-1' => $LANG->getLL('configure_default_headers'),
			'from_email' => array('string', tx_directmail_static::fName('from_email'), $LANG->getLL('from_email.description').'<br />'.$LANG->getLL('from_email.details')),
			'from_name' => array('string', tx_directmail_static::fName('from_name'), $LANG->getLL('from_name.description').'<br />'.$LANG->getLL('from_name.details')),
			'replyto_email' => array('string', tx_directmail_static::fName('replyto_email'), $LANG->getLL('replyto_email.description').'<br />'.$LANG->getLL('replyto_email.details')),
			'replyto_name' => array('string', tx_directmail_static::fName('replyto_name'), $LANG->getLL('replyto_name.description').'<br />'.$LANG->getLL('replyto_name.details')),
			'return_path' => array('string', tx_directmail_static::fName('return_path'), $LANG->getLL('return_path.description').'<br />'.$LANG->getLL('return_path.details')),
			'organisation' => array('string', tx_directmail_static::fName('organisation'), $LANG->getLL('organisation.description').'<br />'.$LANG->getLL('organisation.details')),
			'priority' => array('select', tx_directmail_static::fName('priority'), $LANG->getLL('priority.description').'<br />'.$LANG->getLL('priority.details'), array(3 => $LANG->getLL('configure_priority_normal'), 1 => $LANG->getLL('configure_priority_high'), 5 => $LANG->getLL('configure_priority_low'))),
		);
		$configArray[2] = array(
			'box-2' => $LANG->getLL('configure_default_content'),
			'sendOptions' => array('select', tx_directmail_static::fName('sendOptions'), $LANG->getLL('sendOptions.description').'<br />'.$LANG->getLL('sendOptions.details'), array(3 => $LANG->getLL('configure_plain_and_html') ,1 => $LANG->getLL('configure_plain_only') ,2 => $LANG->getLL('configure_html_only'))),
			'includeMedia' => array('check', tx_directmail_static::fName('includeMedia'), $LANG->getLL('includeMedia.description').'<br />'.$LANG->getLL('includeMedia.details')),
			'flowedFormat' => array('check', tx_directmail_static::fName('flowedFormat'), $LANG->getLL('flowedFormat.description').'<br />'.$LANG->getLL('flowedFormat.details')),
		);
		$configArray[3] = array(
			'box-3' => $LANG->getLL('configure_default_fetching'),
			'HTMLParams' => array('short', tx_directmail_static::fName('HTMLParams'), $LANG->getLL('configure_HTMLParams_description').'<br />'.$LANG->getLL('configure_HTMLParams_details')),
			'plainParams' => array('short', tx_directmail_static::fName('plainParams'), $LANG->getLL('configure_plainParams_description').'<br />'.$LANG->getLL('configure_plainParams_details')),
			'use_domain' => array('select', tx_directmail_static::fName('use_domain'), $LANG->getLL('use_domain.description').'<br />'.$LANG->getLL('use_domain.details'), array(0 => '')),
		);
		$configArray[4] = array(
			'box-4' => $LANG->getLL('configure_options_encoding'),
			'quick_mail_encoding' => array('select', $LANG->getLL('configure_quickmail_encoding'), $LANG->getLL('configure_quickmail_encoding_description'), array('quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit')),
			'direct_mail_encoding' => array('select', $LANG->getLL('configure_directmail_encoding'), $LANG->getLL('configure_directmail_encoding_description'), array('quoted-printable'=>'quoted-printable','base64'=>'base64','8bit'=>'8bit')),
			'quick_mail_charset' => array('short', $LANG->getLL('configure_quickmail_charset'), $LANG->getLL('configure_quickmail_charset_description')),
			'direct_mail_charset' => array('short', $LANG->getLL('configure_directmail_charset'), $LANG->getLL('configure_directmail_charset_description')),
		);
		$configArray[5] = array(
			'box-5' => $LANG->getLL('configure_options_links'),
			'use_rdct' => array('check', tx_directmail_static::fName('use_rdct'), $LANG->getLL('use_rdct.description').'<br />'.$LANG->getLL('use_rdct.details').'<br />'.$LANG->getLL('configure_options_links_rdct')),
			'long_link_mode' => array('check', tx_directmail_static::fName('long_link_mode'), $LANG->getLL('long_link_mode.description')),
			'enable_jump_url' => array('check', $LANG->getLL('configure_options_links_jumpurl'), $LANG->getLL('configure_options_links_jumpurl_description')),
			'enable_mailto_jump_url' => array('check', $LANG->getLL('configure_options_mailto_jumpurl'), $LANG->getLL('configure_options_mailto_jumpurl_description')),
			'authcode_fieldList' => array('short', tx_directmail_static::fName('authcode_fieldList'), $LANG->getLL('authcode_fieldList.description')),
		);
		$configArray[6] = array(
			'box-6' => $LANG->getLL('configure_options_additional'),
			'http_username' => array('short', $LANG->getLL('configure_http_username'), $LANG->getLL('configure_http_username_description').'<br />'.$LANG->getLL('configure_http_username_details')),
			'http_password' => array('short', $LANG->getLL('configure_http_password'), $LANG->getLL('configure_http_password_description')),
			'userTable' => array('short', $LANG->getLL('configure_user_table'), $LANG->getLL('configure_user_table_description')),
			'test_tt_address_uids' => array('short', $LANG->getLL('configure_test_tt_address_uids'), $LANG->getLL('configure_test_tt_address_uids_description')),
			'test_dmail_group_uids' => array('short', $LANG->getLL('configure_test_dmail_group_uids'), $LANG->getLL('configure_test_dmail_group_uids_description')),
			'testmail' => array('short', $LANG->getLL('configure_testmail'), $LANG->getLL('configure_testmail_description'))
		);

			// Set default values
		if (!isset($this->implodedParams['plainParams'])) $this->implodedParams['plainParams'] = '&type=99';
		if (!isset($this->implodedParams['quick_mail_charset'])) $this->implodedParams['quick_mail_charset'] = 'iso-8859-1';
		if (!isset($this->implodedParams['direct_mail_charset'])) $this->implodedParams['direct_mail_charset'] = 'iso-8859-1';

			// Set domain selection list
		$rootline = $this->sys_page->getRootLine($this->id);
		$res_domain = $TYPO3_DB->exec_SELECTquery(
			'uid,domainName',
			'sys_domain',
			'sys_domain.pid='.intval($rootline[0]['uid']).
				t3lib_BEfunc::deleteClause('sys_domain')
			);
		while ($row_domain = $TYPO3_DB->sql_fetch_assoc($res_domain)) {
			$configArray[3]['use_domain']['3'][$row_domain['uid']] = $row_domain['domainName'];
		}

		$this->configArray_length = count($configArray);
		$form ='';
		for ($i=1; $i <= count($configArray); $i++){
			$form .= $this->makeConfigForm($configArray[$i],$this->implodedParams,'pageTS');
		}

		$form .= '<input type="submit" name="submit" value="Update configuration" />';
		$theOutput.= $this->doc->section($LANG->getLL('configure_direct_mail_module'),str_replace('Update configuration', $LANG->getLL('configure_update_configuration'), $form),1,1,0, TRUE);
		return $theOutput;
	}

	/**
	 * Compiling the form from an array and put in to boxes
	 *
	 * @param	array		$configArray: the input array parameter
	 * @param	array		$defaults: default values array
	 * @param	string		$dataPrefix: prefix of the input field's name
	 * @return	string		the compiled input form
	 */
	function makeConfigForm($configArray,$defaults,$dataPrefix)	{
		global $BACK_PATH;

		$params = $defaults;
		$wrapHelp1 = '&nbsp;<a href="#" class="bubble"><img'.
				t3lib_iconWorks::skinImg(
					$BACK_PATH,
					'gfx/helpbubble.gif'
				).' alt="" /> <span class="help" id="sender_email_help">';
		$wrapHelp2 = '</span></a>';

		if (is_array($configArray))	{
			reset($configArray);
			$lines=array();
			while(list($fname,$config)=each($configArray))	{
				if (is_array($config))	{
					$lines[$fname]='<strong>'.htmlspecialchars($config[1]).'</strong>';
					$lines[$fname].=$wrapHelp1.$config[2].$wrapHelp2.'<br />';
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
							reset($config[3]);
							$opt=array();
							while(list($k,$v)=each($config[3]))	{
								$opt[]='<option value="'.htmlspecialchars($k).'"'.($params[$fname]==$k?' selected="selected"':'').'>'.htmlspecialchars($v).'</option>';
							}
							$formEl = '<select name="'.$dataPrefix.'['.$fname.']">'.implode('',$opt).'</select>';
						break;
						default:
							debug($config);
						break;
					}
					$lines[$fname].=$formEl;
					$lines[$fname].='<br />';
				} else {
					if (!strpos($fname ,'box')){
						$imgSrc = t3lib_iconWorks::skinImg(
							$BACK_PATH,
							'gfx/button_right.gif'

						);
						$lines[$fname] ='<div id="header" class="box">
								<div class="toggleTitle">
									<a href="#" onclick="toggleDisplay(\''.$fname.'\', event, '.$this->configArray_length.')">
										<img id="'.$fname.'_toggle" '.$imgSrc.' alt="" >
										<strong>'.strtoupper(htmlspecialchars($config)).'</strong>
									</a>
								</div>
								<div id="'.$fname.'" class="toggleBox" style="display:none">';
						$boxFlag = 1;
					} else {
						$lines[$fname]='<hr />';
						if ($config)	$lines[$fname].='<strong>'.strtoupper(htmlspecialchars($config)).'</strong><br />';
						if ($config)	$lines[$fname].='<br />';
					}
				}
			}
		}
		$out = implode('',$lines);
		if($boxFlag)
			$out .='</div></div>';
		return $out;
	}

	/**
	 * update the pageTS
	 *
	 * @return	void		no return value: sent header to the same page
	 */
	function updatePageTS()	{
		global $BE_USER;

		if ($BE_USER->doesUserHaveAccess(t3lib_BEfunc::getRecord( 'pages', $this->id), 2)) {
			$pageTS = t3lib_div::_GP('pageTS');
			if (is_array($pageTS))	{
				t3lib_BEfunc::updatePagesTSconfig($this->id,$pageTS,$this->TSconfPrefix);
				header('Location: '.t3lib_div::locationHeaderUrl(t3lib_div::getIndpEnv('REQUEST_URI')));
			}
		}
	}

	/**
	 * choose the function, which is chosen in dropdown menu
	 *
	 * @param	string		$mode: function
	 * @return	string		HTML of the content
	 */
	function cmd_default($mode)	{
		global $TCA,$LANG,$TYPO3_CONF_VARS;
		switch($mode)	{
			case 'conf':
				$theOutput.= $this->cmd_conf();
				break;
			case 'convert':
				$theOutput .= $this->cmd_convertCategories();
				break;
			default:
					// Hook for handling of custom modes:
				if (is_array($TYPO3_CONF_VARS['EXT']['directmail']['handlemode-'.$mode])) {
					foreach($TYPO3_CONF_VARS['EXT']['directmail']['handlemode-'.$mode] as $_funcRef) {
						$_params = array();
						$theOutput .= t3lib_div::callUserFunction($_funcRef,$_params,$this);
					}
				}
				break;
		}

		return $theOutput;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod6/class.tx_directmail_configuration.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod6/class.tx_directmail_configuration.php']);
}

?>
