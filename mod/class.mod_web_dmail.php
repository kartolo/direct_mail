<?php
/***************************************************************
*  Copyright notice
*  
*  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
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
/** 
 * @author	Kasper Skårhøj <kasper@typo3.com>
 */

/**
TS config:


mod.web_modules.dmail {
  from_email
  from_name
  replyto_email
  replyto_name
  organisation
  sendOptions (isset)
  HTMLParams (isset) If 
  plainParams (isset)
  userTable		(name of user defined table for mailing. Fields used from this table includes $this->fieldList)

  enablePlain
  enableHTML
  http_username
  http_password

  test_tt_address_uids
  categories.[integer=bit, 0-30] = label
}

 
 
The be_users own TSconfig for the module will override by being merged onto this array.
 
 
 */


/**
EXAMPLE of csv field specification:

;user_date;name;email;zip;phone;module_sys_dmail_category[+1];module_sys_dmail_category[+2];module_sys_dmail_category[+4];module_sys_dmail_category[+8];module_sys_dmail_category[+16];user_age[=20];user_age[=26];user_age[=31];user_age[=36];user_age[=41];user_pregnant[=1];user_kids;user_kidsage[+1];user_kidsage[+2];user_kidsage[+4];user_kidsage[+8]

184;12-02-01;Pia;kimjokumsen@mail.dk;;Nielsen;x;x;x;;;;;x;;;;1;x;;;
185;12-02-01;Connie Greffel;c.greffel@get2net.dk;;39905067;x;x;x;;;;x;;;;;1;x;;;
186;12-02-01;Stine Holm;ravnsbjergholm@hotmail.com;;32 96 70 75;;x;x;;;;x;;;;x;;;;;
187;12-02-01;Anette Bentholm;madsenbentholm@mail.net4you.dk;;98373677;;x;x;;;;;x;;;;2;x;;;


Import of 3541 records raw on PIII/500Mzh took 80 approx seconds 

*/
 
class mod_web_dmail	{
	var $TSconfPrefix = "mod.web_modules.dmail.";
	var $fieldList="uid,name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html,description";	// Taken from class.t3lib_dmailer.php (,firstname is automatically set), added description 050301
		// Internal
	var $modList="";
	var $params=array();
	var $perms_clause="";
	var $pageinfo="";
	var $sys_dmail_uid;
	var $CMD;
	var $pages_uid;
	var $categories;
	var $id;
	var $urlbase;
	var $back;
	var $noView;
	var $url_plain;
	var $url_html;
	var $mode;
	var $implodedParams=array();
	var $userTable;		// If set a valid user table is around
	
	function mod_web_dmail ($id,$pageinfo,$perms_clause,$CMD,$sys_dmail_uid,$pages_uid,$modTSconfig)	{
		$this->id = $id;
		$this->pageinfo = $pageinfo;
		$this->perms_clause = $perms_clause;
		$this->CMD=$CMD;
		$this->sys_dmail_uid=$sys_dmail_uid;
		$this->pages_uid=$pages_uid;
		$this->modList = t3lib_BEfunc::getListOfBackendModules(array("dmail"),$this->perms_clause,$GLOBALS["BACK_PATH"]);
		$this->params=$modTSconfig["properties"];
		$this->implodedParams = t3lib_BEfunc::implodeTSParams($this->params);
		if ($this->params["userTable"] && is_array($GLOBALS["TCA"][$this->params["userTable"]]))	{
			$this->userTable = $this->params["userTable"];
			t3lib_div::loadTCA($this->userTable);
		}

		t3lib_div::loadTCA("sys_dmail");


			// Local lang for dmail module:
		include (t3lib_extMgm::extPath("direct_mail")."mod/locallang.php");
		$GLOBALS["LOCAL_LANG"] = t3lib_div::array_merge_recursive_overrule($GLOBALS["LOCAL_LANG"],$LOCAL_LANG_DMAIL);
		

		
//		debug($this->implodedParams);
//		$this->params=t3lib_BEfunc::processParams($this->pageinfo["abstract"]);
//		debug($this->params);
	}
	function createDMail()	{
		global $TCA;
		if ($createMailFrom = t3lib_div::_GP("createMailFrom"))	{
				// Set default values:
			$dmail = array();
			$dmail["sys_dmail"]["NEW"] = array (
				"from_email" => $this->params["from_email"],
				"from_name" => $this->params["from_name"],
				"replyto_email" => $this->params["replyto_email"],
				"replyto_name" => $this->params["replyto_name"],
				"return_path" => $this->params["return_path"],
				"long_link_rdct_url" => $this->params["long_link_rdct_url"],
				"long_link_mode" => $this->params["long_link_mode"],
				"organisation" => $this->params["organisation"]
			);
			$dmail["sys_dmail"]["NEW"]["sendOptions"] = $TCA["sys_dmail"]["columns"]["sendOptions"]["config"]["default"];
				// If params set, set default values:
			if (isset($this->params["sendOptions"]))	$dmail["sys_dmail"]["NEW"]["sendOptions"] = $this->params["sendOptions"];
			if (isset($this->params["HTMLParams"]))		$dmail["sys_dmail"]["NEW"]["HTMLParams"] = $this->params["HTMLParams"];
			if (isset($this->params["plainParams"]))	$dmail["sys_dmail"]["NEW"]["plainParams"] = $this->params["plainParams"];
	
				// If createMailFrom is an integer, it's an internal page. If not, it's an external url 
			if (t3lib_div::testInt($createMailFrom))	{
				$createFromMailRec = t3lib_BEfunc::getRecord ("pages",$createMailFrom);
				if (t3lib_div::inList($GLOBALS["TYPO3_CONF_VARS"]["FE"]["content_doktypes"],$createFromMailRec["doktype"]))	{
					$dmail["sys_dmail"]["NEW"]["subject"] = $createFromMailRec["title"];
					$dmail["sys_dmail"]["NEW"]["type"] = 0;
					$dmail["sys_dmail"]["NEW"]["page"] = $createFromMailRec["uid"];
					$dmail["sys_dmail"]["NEW"]["pid"]=$this->pageinfo["uid"];
				}
			} else {
				$dmail["sys_dmail"]["NEW"]["subject"] = $createMailFrom;
				$dmail["sys_dmail"]["NEW"]["type"] = 1;
				$dmail["sys_dmail"]["NEW"]["sendOptions"] = 0;
	
				$dmail["sys_dmail"]["NEW"]["plainParams"] = t3lib_div::_GP("createMailFrom_plainUrl");
				$this->params["enablePlain"] = $dmail["sys_dmail"]["NEW"]["plainParams"] ? 1 : 0;
	
				$dmail["sys_dmail"]["NEW"]["HTMLParams"] = t3lib_div::_GP("createMailFrom_HTMLUrl");
				$this->params["enableHTML"] = $dmail["sys_dmail"]["NEW"]["HTMLParams"] ? 1 : 0;
	
				$dmail["sys_dmail"]["NEW"]["pid"]=$this->pageinfo["uid"];
			}
	
				// Finally the enablePlain and enableHTML flags ultimately determines the sendOptions, IF they are set in the pageTSConfig
			if (isset($this->params["enablePlain"]))	{if ($this->params["enablePlain"]) {$dmail["sys_dmail"]["NEW"]["sendOptions"]|=1;} else {$dmail["sys_dmail"]["NEW"]["sendOptions"]&=254;}}
			if (isset($this->params["enableHTML"]))	{if ($this->params["enableHTML"]) {$dmail["sys_dmail"]["NEW"]["sendOptions"]|=2;} else {$dmail["sys_dmail"]["NEW"]["sendOptions"]&=253;}}

			if ($dmail["sys_dmail"]["NEW"]["pid"])	{
				$tce = t3lib_div::makeInstance("t3lib_TCEmain");
				$tce->stripslashes_values=0;
				$tce->start($dmail,Array());
				$tce->process_datamap();
	//			debug($tce->substNEWwithIDs["NEW"]);
				$this->sys_dmail_uid = $tce->substNEWwithIDs["NEW"];
			} else {
				// wrong page, could not...
			}
		}
	}

	// ********************
	// MAIN function
	// ********************
	function main()	{
//		debug($this->modList);
		if (t3lib_div::inList($GLOBALS["TYPO3_CONF_VARS"]["FE"]["content_doktypes"],$this->pageinfo["doktype"]))	{		// Regular page, show menu to create a direct mail from this page.
			if ($this->pageinfo["group_id"]>0 || $this->pageinfo["hidden"])	{
				$theOutput.= $GLOBALS["SOBE"]->doc->section($GLOBALS["LANG"]->getLL("dmail_newsletters"),'<span class="typo3-red">'.$GLOBALS["LANG"]->getLL("dmail_noCreateAccess").'</span>',0,1);
			} else {
				$isNewsletterPage=0;
				if (is_array($this->modList["rows"]))	{
					reset($this->modList["rows"]);
					while(list(,$rData)=each($this->modList["rows"]))	{
						if ($rData["uid"]==$this->pageinfo["pid"])	{
							$isNewsletterPage=1;
						}
					}
				}
				if ($isNewsletterPage)	{
					header('Location: index.php?id='.$this->pageinfo["pid"].'&CMD=displayPageInfo&pages_uid='.$this->pageinfo["uid"].'&SET[dmail_mode]=news');
					exit;
				}
			}
		} elseif ($this->pageinfo["doktype"]==254 && $this->pageinfo["module"]=="dmail")	{	// Direct mail module
			$theOutput.= $this->mailModule_main();
		} elseif ($this->id!=0) {
			$theOutput.= $GLOBALS["SOBE"]->doc->section($GLOBALS["LANG"]->getLL("dmail_newsletters"),'<span class="typo3-red">'.$GLOBALS["LANG"]->getLL("dmail_noRegular").'</span>',0,1);
		}

		if ($this->id!=0) {
			$theOutput.=$GLOBALS["SOBE"]->doc->spacer(10);
		}
		return $theOutput;
	}
	function mailModule_main()	{
			// Top menu
		$menuHTML = t3lib_BEfunc::getFuncMenu($GLOBALS["SOBE"]->id,"SET[dmail_mode]",$GLOBALS["SOBE"]->MOD_SETTINGS["dmail_mode"],$GLOBALS["SOBE"]->MOD_MENU["dmail_mode"]);

		$theOutput.=$GLOBALS["SOBE"]->doc->divider(5);
		$theOutput.=$GLOBALS["SOBE"]->doc->section("",$menuHTML);
//		$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
		$mode = $GLOBALS["SOBE"]->MOD_SETTINGS["dmail_mode"];

		if (!$this->sys_dmail_uid || $mode!="direct")	{
			$this->makeCategories();

				// COMMAND:
			switch($this->CMD) {
				case "displayPageInfo":
					$theOutput.= $this->cmd_displayPageInfo();
				break;
				case "displayUserInfo":
					$theOutput.= $this->cmd_displayUserInfo();
				break;				
				case "displayMailGroup":
					$result = $this->cmd_compileMailGroup(intval(t3lib_div::_GP("group_uid")));
					$theOutput.= $this->cmd_displayMailGroup($result);
				break;
				case "displayImport":
					$theOutput.= $this->cmd_displayImport();
				break;
				default:
					$theOutput.= $this->cmd_default($mode);
				break;
			}
		} else {
				// Here the single dmail record is shown.
			$this->urlbase = substr(t3lib_div::getIndpEnv("TYPO3_REQUEST_DIR"),0,-(strlen(TYPO3_MOD_PATH)+strlen(TYPO3_mainDir)));
			$this->sys_dmail_uid = intval($this->sys_dmail_uid);
			
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_dmail', 'pid='.intval($this->id).' AND uid='.intval($this->sys_dmail_uid).t3lib_BEfunc::deleteClause('sys_dmail'));

			$this->noView = 0;
			$this->back = '<input type="Submit" value="< BACK" onClick="jumpToUrlD(\'index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'\'); return false;">';
			if($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					// Finding the url to fetch content from
				switch((string)$row["type"])	{
					case 1:
						$this->url_html = $row["HTMLParams"];
						$this->url_plain = $row["plainParams"];
					break;
					default:
						$this->url_html = $this->urlbase."?id=".$row["page"].$row["HTMLParams"];
						$this->url_plain = $this->urlbase."?id=".$row["page"].$row["plainParams"];
					break;
				}
				if (!($row["sendOptions"]&1) || !$this->url_plain)	{	// plain
					$this->url_plain="";
				} else {
					$urlParts = parse_url($this->url_plain);
					if (!$urlParts["scheme"])	{
						$this->url_plain="http://".$this->url_plain;
					}
				}
				if (!($row["sendOptions"]&2) || !$this->url_html)	{	// html
					$this->url_html="";
				} else {
					$urlParts = parse_url($this->url_html);
					if (!$urlParts["scheme"])	{
						$this->url_html="http://".$this->url_html;
					}
				}				
				
					// COMMAND:
				switch($this->CMD) {
					case "fetch":
						$theOutput.=$this->cmd_fetch($row);
						$row = t3lib_befunc::getRecord("sys_dmail",$row["uid"]);
					break;
					case "prefetch":
						$theOutput.=$this->cmd_prefetch($row);
					break;
					case "testmail":
						$theOutput.=$this->cmd_testmail($row);
					break;
					case "finalmail":
						$theOutput.=$this->cmd_finalmail($row);
					break;
					case "send_mail_test":
					case "send_mail_final":
						$theOutput.=$this->cmd_send_mail($row);
					break;
					case "stats":
						$theOutput.=$this->cmd_stats($row);
					break;
				}
				if (!$this->noView)	{
					$theOutput.=$this->directMail_defaultView($row);
				}
			}
		}
		return $theOutput;
	}
	function makeCategories()	{
		$this->categories = array();
		if (is_array($this->params["categories."]))	{
			reset($this->params["categories."]);
			while(list($pKey,$pVal)=each($this->params["categories."]))	{
				if (trim($pVal))	{
					$catNum = intval($pKey);
					if ($catNum>=0 && $catNum<=30)	{
						$this->categories[$catNum] = $pVal;
					}
				}
			}
		}
	}

	// ********************
	// CMD functions
	// ********************
	function cmd_displayPageInfo()	{
		global $TCA, $HTTP_POST_VARS, $LANG;		

			// Here the dmail list is rendered:
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'uid,pid,subject,tstamp,issent,renderedsize,attachment,type', 
						'sys_dmail', 
						'pid='.intval($this->id).' AND type=0 AND page='.intval($this->pages_uid).t3lib_BEfunc::deleteClause('sys_dmail'),
						'',
						$GLOBALS['TYPO3_DB']->stripOrderBy($TCA['sys_dmail']['ctrl']['default_sortby'])
					);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res))	{
			$onClick = ' onClick="return confirm('.$GLOBALS['LANG']->JScharCode(sprintf($LANG->getLL("nl_l_warning"),$GLOBALS['TYPO3_DB']->sql_num_rows($res))).');"';
		} else {
			$onClick = "";
		}

		
		
		$out="";
		$out.='<a href="#" onClick="'.t3lib_BEfunc::viewOnClick($this->pages_uid,$GLOBALS["BACK_PATH"]).'"><img src="'.$GLOBALS["BACK_PATH"].'gfx/zoom.gif" width="12" height="12" hspace=3 vspace=2 border="0" align=top>'.$LANG->getLL("nl_viewPage").'</a><BR>';
		$out.='<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[pages]['.$this->pages_uid.']=edit&edit_content=1',$GLOBALS["BACK_PATH"],"").'"><img src="'.$GLOBALS["BACK_PATH"].'gfx/edit2.gif" width="11" height="12" hspace=3 vspace=2 border="0" align=top>'.$LANG->getLL("nl_editPage").'</a><BR>';
		$out.='<a href="index.php?id='.$this->id.'&createMailFrom='.$this->pages_uid.'&SET[dmail_mode]=direct"'.$onClick.'><img src="'.$GLOBALS["BACK_PATH"].'/gfx/newmail.gif" width="18" height="16" border="0" align=top>'.$LANG->getLL("nl_createDmailFromPage").'</a><BR>';				
	
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res))	{
			$out.="<BR><b>".$LANG->getLL("nl_alreadyBasedOn").":</b><BR><BR>";
			$out.="<table border=0 cellpadding=0 cellspacing=0>";
				$out.='<tr>
					<td class="bgColor5">'.fw("&nbsp;").'</td>
					<td class="bgColor5"><b>'.fw($LANG->getLL("nl_l_subject")."&nbsp;&nbsp;").'</b></td>
					<td class="bgColor5"><b>'.fw($LANG->getLL("nl_l_lastM")."&nbsp;&nbsp;").'</b></td>
					<td class="bgColor5"><b>'.fw($LANG->getLL("nl_l_sent")."&nbsp;&nbsp;").'</b></td>
					<td class="bgColor5"><b>'.fw($LANG->getLL("nl_l_size")."&nbsp;&nbsp;").'</b></td>
					<td class="bgColor5"><b>'.fw($LANG->getLL("nl_l_attach")."&nbsp;&nbsp;").'</b></td>
					<td class="bgColor5"><b>'.fw($LANG->getLL("nl_l_type")."&nbsp;&nbsp;").'</b></td>
				</tr>';
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$out.='<tr>
					<td><img src="'.$GLOBALS["BACK_PATH"].'gfx/i/mail.gif" width=18 height=16 border=0 align="top"></td>
					<td>'.$this->linkDMail_record(fw(t3lib_div::fixed_lgd($row["subject"],30)."&nbsp;&nbsp;"),$row["uid"]).'</td>
					<td>'.fw(t3lib_BEfunc::date($row["tstamp"])."&nbsp;&nbsp;").'</td>
					<td>'.($row["issent"] ? fw("YES") : "").'</td>
					<td>'.($row["renderedsize"] ? fw(t3lib_div::formatSize($row["renderedsize"])."&nbsp;&nbsp;") : "").'</td>
					<td>'.($row["attachment"] ? '<img src="attach.gif" width=9 height=13>' : "").'</td>
					<td>'.fw($row["type"] ? $LANG->getLL("nl_l_tUrl") : $LANG->getLL("nl_l_tPage")).'</td>
				</tr>';
				
			}
			$out.='</table>';
		}
	
		$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("nl_info"),$out,0,1);
		$theOutput.= $GLOBALS["SOBE"]->doc->divider(20);




		if (is_array($HTTP_POST_VARS["indata"]["categories"]))	{
			$data=array();
			reset($HTTP_POST_VARS["indata"]["categories"]);
			while(list($recUid,$recValues)=each($HTTP_POST_VARS["indata"]["categories"]))	{
//						debug($recValues);
				reset($recValues);
				$data["tt_content"][$recUid]["module_sys_dmail_category"]=0;
				while(list($k,$b)=each($recValues))	{
					if ($b)	{$data["tt_content"][$recUid]["module_sys_dmail_category"]|= pow (2,$k);}
				}
			}
			$tce = t3lib_div::makeInstance("t3lib_TCEmain");
			$tce->stripslashes_values=0;
			$tce->start($data,Array());
			$tce->process_datamap();
//						debug($data);
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'colPos, CType, uid, header, bodytext, module_sys_dmail_category', 
					'tt_content', 
					'pid='.intval($this->pages_uid).t3lib_BEfunc::deleteClause('tt_content').' AND NOT hidden',
					'',
					'colPos,sorting'
				);
		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res))	{
			$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("nl_cat"),$LANG->getLL("nl_cat_msg1"));
		} else {
			$out="";
			$colPosVal=99;
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$out.='<tr><td colspan=3><img src="clear.gif" width=1 height=15></td></tr>';
				if ($colPosVal!=$row["colPos"])	{
					$out.='<tr><td colspan=3 class="bgColor5">'.fw($LANG->getLL("nl_l_column").": <strong>".htmlspecialchars(t3lib_BEfunc::getProcessedValue("tt_content","colPos",$row["colPos"]))."</strong>").'</td></tr>';
					$colPosVal=$row["colPos"];
				}
				$out.='<tr>';
				$out.='<td valign=top width=75%>'.fw('<img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("tt_content",$row).'" width=18 height=16 border=0 title="'.htmlspecialchars(t3lib_BEfunc::getProcessedValue("tt_content","CType",$row["CType"])).'" align=top> '.
					$row["header"].'<BR>'.t3lib_div::fixed_lgd(strip_tags($row["bodytext"]),200).'<BR>').'</td>';

				$out.='<td>&nbsp;&nbsp;</td><td nowrap valign=top>';
				$out_check="";
				if ($row["module_sys_dmail_category"]) {
					$out_check.='<font color=red><strong>'.$LANG->getLL("nl_l_ONLY").'</strong></font>';
				} else {
					$out_check.='<font color=green><strong>'.$LANG->getLL("nl_l_ALL").'</strong></font>';
				}
				$out_check.="<BR>";
				reset($this->categories);
				while(list($pKey,$pVal)=each($this->categories))	{
					$out_check.='<input type="hidden" name="indata[categories]['.$row["uid"].']['.$pKey.']" value="0"><input type="checkbox" name="indata[categories]['.$row["uid"].']['.$pKey.']" value="1"'.(($row["module_sys_dmail_category"]&pow (2,$pKey)) ?" checked":"").'> '.$pVal.'<BR>';
				}
				$out.=fw($out_check).'</td></tr>';
			}
			$out='<table border=0 cellpadding=0 cellspacing=0>'.$out.'</table>';
			$out.='<input type="hidden" name="pages_uid" value="'.$this->pages_uid.'"><input type="hidden" name="CMD" value="'.$this->CMD.'"><BR><input type="submit" value="'.$LANG->getLL("nl_l_update").'">';
			$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("nl_cat"),$LANG->getLL("nl_cat_msg2")."<BR><BR>".$out);
		}
		return $theOutput;
	}
	function getCsvValues($str,$sep=",")	{
		$fh=tmpfile();
		fwrite ($fh, trim($str));
		fseek ($fh,0);
		$lines=array();
		while ($data = fgetcsv ($fh, 1000, $sep)) {
			$lines[]=$data;
		}
		return $lines;
	}
	function rearrangeCsvValues($lines)	{
		$out=array();
		if (is_array($lines) && count($lines)>0)	{
				// Analyse if first line is fieldnames. 
				// Required is it that every value is either 1) found in the list, fieldsList in this class (see top) 2) the value is empty (value omitted then) or 3) the field starts with "user_".
				// In addition fields may be prepended with "[code]". This is used if the incoming value is true in which case '+[value]' adds that number to the field value (accummulation) and '=[value]' overrides any existing value in the field
			$first = $lines[0];
			$fieldListArr = explode(",",$this->fieldList);
//			debug($fieldListArr);
			reset($first);
			$fieldName=1;
			$fieldOrder=array();
			while(list(,$v)=each($first))	{
				list($fName,$fConf) = split("\[|\]",$v);
				$fName =trim($fName);
				$fConf =trim($fConf);
				
				$fieldOrder[]=array($fName,$fConf);
				if ($fName && substr($fName,0,5)!="user_" && !in_array($fName,$fieldListArr))	{$fieldName=0; break;}
			}
				// If not field list, then:
			if (!$fieldName)	{
				$fieldOrder = array(array("name"),array("email"));
			}
//			debug($fieldOrder);
//			debug($fieldName);
//debug($lines);
				// Re map values
			reset($lines);
			if ($fieldName)	{
				next($lines);	// Advance pointer if the first line was field names
			}
			$c=0;
			while(list(,$data)=each($lines))	{
				if (count($data)>1 || $data[0])	{	// Must be a line with content. This sorts out entries with one key which is empty. Those are empty lines.
//					debug($data);
						// Traverse fieldOrder and map values over
					reset($fieldOrder);
					while(list($kk,$fN)=each($fieldOrder))	{
						if ($fN[0])	{
							if ($fN[1])	{
								if (trim($data[$kk]))	{	// If is true
									if (substr($fN[1],0,1)=="=")	{
										$out[$c][$fN[0]]=trim(substr($fN[1],1));
									} elseif (substr($fN[1],0,1)=="+")	{
										$out[$c][$fN[0]]+=substr($fN[1],1);
									}
								}
							} else {
								$out[$c][$fN[0]]=$data[$kk];
							}
						}
					}
					$c++;
				}
			}
		}
		return $out;
	}
	function rearrangePlainMails($plainMails)	{
		$out=array();
		if (is_array($plainMails))	{
			reset($plainMails);
			$c=0;
			while(list(,$v)=each($plainMails))	{
				$out[$c]["email"]=$v;
				$out[$c]["name"]="";
				$c++;
			}
		}
		return $out;
	}
	function makePidListQuery($table,$pidList,$fields,$cat)	{
		$cat = intval($cat);
		if ($cat>0)	{
			$addQ = ' AND module_sys_dmail_category&'.$cat.' > 0';
		} else {
			$addQ = '';
		}

		$query = $GLOBALS['TYPO3_DB']->SELECTquery(
						$fields,
						$table, 
						'pid IN ('.$pidList.')'.
							$addQ.
							t3lib_BEfunc::deleteClause($table).
							t3lib_pageSelect::enableFields($table)
					);

		return $query;
	}
	function getIdList($table,$pidList,$cat)	{
		$query = $this->makePidListQuery($table,$pidList,"uid",$cat);
		$res = $GLOBALS['TYPO3_DB']->sql(TYPO3_db,$query);
		$outArr = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$outArr[] = $row["uid"];
		}
		return $outArr;
	}
	function makeStaticListQuery($table,$uid,$fields)	{
		$query = $GLOBALS['TYPO3_DB']->SELECTquery(
						$fields,
						$table.',sys_dmail_group,sys_dmail_group_mm', 
						'sys_dmail_group_mm.uid_local=sys_dmail_group.uid AND
						sys_dmail_group.uid = '.$uid.' AND
								sys_dmail_group_mm.uid_foreign='.$table.'.uid AND sys_dmail_group_mm.tablenames="'.$table.'"'.
								t3lib_pageSelect::enableFields($table).	// Enable fields includes 'deleted'
								t3lib_pageSelect::enableFields("sys_dmail_group")
					);
		return $query;
	}
	function getStaticIdList($table,$uid)	{
		$query = $this->makeStaticListQuery($table,$uid,$table.".uid");
		$res = $GLOBALS['TYPO3_DB']->sql(TYPO3_db,$query);
		$outArr=array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$outArr[]=$row["uid"];
		}
		return $outArr;
	}
	function getMailGroups($list,$parsedGroups)	{
		$groupIdList = t3lib_div::intExplode(",",$list);
		$groups = array();

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('sys_dmail_group.*', 'sys_dmail_group,pages', '
					sys_dmail_group.uid IN ('.implode(',',$groupIdList).') 
					AND pages.uid=sys_dmail_group.pid 
					AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
		//			t3lib_BEfunc::deleteClause('sys_dmail_group').	// Enable fields includes 'deleted'
		//			t3lib_pageSelect::enableFields('pages').		// Records should be selected from hidden pages...
					t3lib_pageSelect::enableFields('sys_dmail_group'));
			
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			if ($row["type"]==4)	{	// Other mail group...
				if (!in_array($row["uid"],$parsedGroups))	{
					$parsedGroups[]=$row["uid"];
					$groups=array_merge($groups,$this->getMailGroups($row["mail_groups"],$parsedGroups));
				}
			} else {
				$groups[]=$row["uid"];	// Normal mail group, just add to list
			}
		}
		return $groups;
	}
	function cmd_displayMailGroup($result)	{
		$count=0;
		$idLists = $result["queryInfo"]["id_lists"];
		if (is_array($idLists["tt_address"]))	$count+=count($idLists["tt_address"]);
		if (is_array($idLists["fe_users"]))	$count+=count($idLists["fe_users"]);
		if (is_array($idLists["PLAINLIST"]))	$count+=count($idLists["PLAINLIST"]);
		if (is_array($idLists[$this->userTable]))	$count+=count($idLists[$this->userTable]);
		
		$group = t3lib_befunc::getRecord("sys_dmail_group",t3lib_div::_GP("group_uid"));
		$out=t3lib_iconWorks::getIconImage("sys_dmail_group",$group,$GLOBALS["BACK_PATH"],'align="top"').$group["title"]."<BR>";
		
		$lCmd=t3lib_div::_GP("lCmd");

		$mainC = $out."Total number of recipients: <strong>".$count."</strong>";
		if (!$lCmd)	{
			$mainC.= '<BR>';
			$mainC.= '<BR>';
			$mainC.= '<a href="'.t3lib_div::linkThisScript(array("lCmd"=>"listall")).'">List all recipients</a>';
		}
		
		$theOutput.= $GLOBALS["SOBE"]->doc->section("Recipients from group:",$mainC);
		$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);

		switch($lCmd)	{
			case "listall":
				$theOutput.= $GLOBALS["SOBE"]->doc->section("ADDRESS TABLE",$this->getRecordList($this->fetchRecordsListValues($idLists["tt_address"],"tt_address"),"tt_address"));
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section("WEBSITE USERS TABLE",$this->getRecordList($this->fetchRecordsListValues($idLists["fe_users"],"fe_users"),"fe_users"));
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section("PLAIN LIST",$this->getRecordList($idLists["PLAINLIST"],"default",1));
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section($this->userTable." TABLE",$this->getRecordList($this->fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable));
			break;
			default:
				if (t3lib_div::_GP("csv"))	{
					$csvValue=t3lib_div::_GP("csv");
					if ($csvValue=="PLAINLIST")	{
						$this->downloadCSV($idLists["PLAINLIST"]);
					} elseif (t3lib_div::inList("tt_address,fe_users,".$this->userTable, $csvValue)) {
						$this->downloadCSV($this->fetchRecordsListValues($idLists[$csvValue],$csvValue,$this->fieldList.",tstamp"));
					}
				} else {
					$theOutput.= $GLOBALS["SOBE"]->doc->section("ADDRESS TABLE","Recipients: ".(is_array($idLists["tt_address"])?count($idLists["tt_address"]):0).'<BR><a href="'.t3lib_div::linkThisScript(array("csv"=>"tt_address")).'">Download CSV file</a>');
					$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
					$theOutput.= $GLOBALS["SOBE"]->doc->section("WEBSITE USERS TABLE","Recipients: ".(is_array($idLists["fe_users"])?count($idLists["fe_users"]):0).'<BR><a href="'.t3lib_div::linkThisScript(array("csv"=>"fe_users")).'">Download CSV file</a>');
					$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
					$theOutput.= $GLOBALS["SOBE"]->doc->section("PLAIN LIST","Recipients: ".(is_array($idLists["PLAINLIST"])?count($idLists["PLAINLIST"]):0).'<BR><a href="'.t3lib_div::linkThisScript(array("csv"=>"PLAINLIST")).'">Download CSV file</a>');
					$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
					$theOutput.= $GLOBALS["SOBE"]->doc->section($this->userTable." TABLE","Recipients: ".(is_array($idLists[$this->userTable])?count($idLists[$this->userTable]):0).'<BR><a href="'.t3lib_div::linkThisScript(array("csv"=>$this->userTable)).'">Download CSV file</a>');
				}
			break;
		}		
		return $theOutput;
	}
	function downloadCSV($idArr)	{
		$lines=array();
#debug($idArr);
		if (is_array($idArr) && count($idArr))	{
			reset($idArr);
			$lines[]=t3lib_div::csvValues(array_keys(current($idArr)),",","");
			
			reset($idArr);
			while(list($i,$rec)=each($idArr))	{
	//			debug(t3lib_div::csvValues($rec),1);
				$lines[]=t3lib_div::csvValues($rec);
			}
		}

			$filename="DirectMail_export_".date("dmy-Hi").".csv";
			$mimeType = "application/octet-stream";
			Header("Content-Type: ".$mimeType);
			Header("Content-Disposition: attachment; filename=".$filename);
			echo implode(chr(13).chr(10),$lines);
			exit;
	}
	function cmd_displayMailGroup_test($result)	{
		$count=0;
		$idLists = $result["queryInfo"]["id_lists"];
		$out="";
		if (is_array($idLists["tt_address"]))	{$out.=$this->getRecordList($this->fetchRecordsListValues($idLists["tt_address"],"tt_address"),"tt_address");}
		if (is_array($idLists["fe_users"]))	{$out.=$this->getRecordList($this->fetchRecordsListValues($idLists["fe_users"],"fe_users"),"fe_users");}
		if (is_array($idLists["PLAINLIST"]))	{$out.=$this->getRecordList($idLists["PLAINLIST"],"default",1);}
		if (is_array($idLists[$this->userTable]))	{$out.=$this->getRecordList($this->fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable);}

		return $out;
	}
	function cmd_compileMailGroup($group_uid,$makeIdLists=1)	{
		// $makeIdLists: Set to 0 if you don't want the list of table ids to be collected but only the queries to be stored.
		$queries=array();
		$id_lists=array();
		if ($group_uid)	{
			$mailGroup=t3lib_BEfunc::getRecord("sys_dmail_group",$group_uid);
			if (is_array($mailGroup) && $mailGroup["pid"]==$this->id)	{
				$head = '<img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("sys_dmail_group").'" width=18 height=16 border=0 align="top">'.t3lib_div::fixed_lgd($mailGroup["title"],30)."<BR>";
				$theOutput.=$head;
				switch($mailGroup["type"])	{
					case 0:	// From pages
						$thePages = $mailGroup["pages"] ? $mailGroup["pages"] : $this->id;		// use current page if no else
						$pages = t3lib_div::intExplode(",",$thePages);	// Explode the pages
						reset($pages);
						$pageIdArray=array();
						while(list(,$pageUid)=each($pages))	{
							if ($pageUid>0)	{
								$pageinfo = t3lib_BEfunc::readPageAccess($pageUid,$this->perms_clause);
								if (is_array($pageinfo))	{
									$info["fromPages"][]=$pageinfo;
									$pageIdArray[]=$pageUid;
									if ($mailGroup["recursive"])	{
										$pageIdArray=array_merge($pageIdArray,$GLOBALS["SOBE"]->getRecursiveSelect($pageUid,$this->perms_clause));
									}
								}
							}
								
						}
							// Remove any duplicates
						$pageIdArray=array_unique($pageIdArray);
						$pidList = implode(",",$pageIdArray);
						$info["recursive"]=$mailGroup["recursive"];
//						debug($pageIdArray);
//						debug($info);
							// Make queries
						if ($pidList)	{
							$whichTables = intval($mailGroup["whichtables"]);
							if ($whichTables&1)	{	// tt_address
								$queries["tt_address"]=$this->makePidListQuery("tt_address",$pidList,"*",$mailGroup["select_categories"]);
								if ($makeIdLists)	$id_lists["tt_address"]=$this->getIdList("tt_address",$pidList,$mailGroup["select_categories"]);
							}
							if ($whichTables&2)	{	// tt_address
								$queries["fe_users"]=$this->makePidListQuery("fe_users",$pidList,"*",$mailGroup["select_categories"]);
								if ($makeIdLists)	$id_lists["fe_users"]=$this->getIdList("fe_users",$pidList,$mailGroup["select_categories"]);
							}
							if ($this->userTable && ($whichTables&4))	{	// tt_address
								$queries[$this->userTable]=$this->makePidListQuery($this->userTable,$pidList,"*",$mailGroup["select_categories"]);
								if ($makeIdLists)	$id_lists[$this->userTable]=$this->getIdList($this->userTable,$pidList,$mailGroup["select_categories"]);
							}
						}
		//				debug($queries);
			//			debug($id_lists);
					break;
					case 1: // List of mails
						if ($mailGroup["csv"]==1)	{
							$recipients = $this->rearrangeCsvValues($this->getCsvValues($mailGroup["list"]));
//							debug($recipients);
						} else {
							$recipients = $this->rearrangePlainMails(array_unique(split("[[:space:],;]+",$mailGroup["list"])));
//							debug($recipients);
						}
						$id_lists["PLAINLIST"] = $this->cleanPlainList($recipients);
//						debug($id_lists);
					break;
					case 2:	// Static MM list
						$queries["tt_address"] = $this->makeStaticListQuery("tt_address",$group_uid,"tt_address.*");
						if ($makeIdLists)	$id_lists["tt_address"] = $this->getStaticIdList("tt_address",$group_uid);
						$queries["fe_users"] = $this->makeStaticListQuery("fe_users",$group_uid,"fe_users.*");
						if ($makeIdLists)	$id_lists["fe_users"] = $this->getStaticIdList("fe_users",$group_uid);
						if ($this->userTable)	{
							$queries[$this->userTable] = $this->makeStaticListQuery($this->userTable,$group_uid,$this->userTable."*");
							if ($makeIdLists)	$id_lists[$this->userTable] = $this->getStaticIdList($this->userTable,$group_uid);
						}
//						debug($queries);
//						debug($id_lists);
					break;
					case 3:	// QUERY
						//$theOutput.=$this->cmd_query($group_uid);
						$theOutput.=$GLOBALS["SOBE"]->doc->section("Special Query","UNDER CONSTRUCTION...");
					break;
					case 4:	// 
						$groups = array_unique($this->getMailGroups($mailGroup["mail_groups"],array($mailGroup["uid"])));
						reset($groups);
						$queries=array();
						$id_lists=array();
						while(list(,$v)=each($groups))	{
							$collect=$this->cmd_compileMailGroup($v);
							if (is_array($collect["queryInfo"]["queries"]))	{
								$queries=t3lib_div::array_merge_recursive_overrule($queries,$collect["queryInfo"]["queries"]);
							}
							if (is_array($collect["queryInfo"]["id_lists"]))	{
								$id_lists=t3lib_div::array_merge_recursive_overrule($id_lists,$collect["queryInfo"]["id_lists"]);
							}
						}
							// Make unique entries
						if (is_array($id_lists["tt_address"]))	$id_lists["tt_address"] = array_unique($id_lists["tt_address"]);
						if (is_array($id_lists["fe_users"]))	$id_lists["fe_users"] = array_unique($id_lists["fe_users"]);
						if (is_array($id_lists[$this->userTable]) && $this->userTable)	$id_lists[$this->userTable] = array_unique($id_lists[$this->userTable]);
						if (is_array($id_lists["PLAINLIST"]))	{$id_lists["PLAINLIST"] = $this->cleanPlainList($id_lists["PLAINLIST"]);}

//						debug($id_lists);
	//					debug($queries);
						
//						debug($groups);
					break;
				}
//				debug($mailGroup);
			}
		}
		$outputArray=array(
			"code"=>$theOutput,
			"queryInfo" => array("id_lists"=>$id_lists, "queries"=>$queries)
		);
		return $outputArray;
	}
	function cleanPlainList($plainlist)	{
		reset($plainlist);
		$emails=array();
		while(list($k,$v)=each($plainlist))	{
			if (in_array($v["email"],$emails))	{	unset($plainlist[$k]);	}
			$emails[]=$v["email"];
		}
		return $plainlist;
	}
	function cmd_query($dgUid)	{
		global $HTTP_POST_VARS;
		$GLOBALS["SOBE"]->MOD_SETTINGS=array();
		$GLOBALS["SOBE"]->MOD_SETTINGS["dmail_queryConfig"]=serialize($HTTP_POST_VARS["dmail_queryConfig"]);
		$GLOBALS["SOBE"]->MOD_SETTINGS["dmail_queryTable"]=$HTTP_POST_VARS["SET"]["dmail_queryTable"];
		$GLOBALS["SOBE"]->MOD_SETTINGS["dmail_search_query_smallparts"]=1;

		$qGen = t3lib_div::makeInstance("mailSelect");
		$qGen->init("queryConfig",$GLOBALS["SOBE"]->MOD_SETTINGS["queryTable"]);
		$qGen->noWrap="";
		if ($this->userTable)	$qGen->allowedTables[]=$this->userTable;
		$tmpCode=$qGen->makeSelectorTable($GLOBALS["SOBE"]->MOD_SETTINGS,"table,query");
		$tmpCode.='<input type="hidden" name="CMD" value="displayMailGroup"><input type="hidden" name="group_uid" value="'.$dgUid.'">';
		$theOutput.=$GLOBALS["SOBE"]->doc->section("Make Query",$tmpCode);

//		$theOutput=$qGen->getFormElements();
//		$theOutput.="<HR>";


		$out = $qGen->getQuery($qGen->queryConfig);
		$theOutput.=$GLOBALS["SOBE"]->doc->section("QUERY",$out);
		
		return $theOutput;
	}
	function importRecords_sort($records,$syncSelect,$tstampFlag)	{
		reset($records);
		$kinds=array();
		while(list(,$recdata)=each($records))	{
			if ($syncSelect && !t3lib_div::testInt($syncSelect))	{
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,tstamp', 'tt_address', 'pid='.intval($this->id).' AND '.$syncSelect.'="'.$GLOBALS['TYPO3_DB']->quoteStr($recdata[$syncSelect], 'tt_address').'"'.t3lib_befunc::deleteClause('tt_address'), '', '', '1');
				if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					if ($tstampFlag)	{
						if ($row["tstamp"]>intval($recdata["tstamp"]))	{
							$kinds["newer_version_detected"][]=$recdata;
						} else {$kinds["update"][$row["uid"]]=$recdata;}
					} else {$kinds["update"][$row["uid"]]=$recdata;}
				} else {$kinds["insert"][]=$recdata;}	// Import if no row found
			} else {$kinds["insert"][]=$recdata;}
		}
		return $kinds;
	}
	function importRecords($categorizedRecords,$removeExisting)	{
		$cmd = array();
		$data = array();
		if ($removeExisting)	{		// Deleting:
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tt_address', 'pid='.intval($this->id).t3lib_BEfunc::deleteClause('tt_address'));
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$cmd["tt_address"][$row["uid"]]["delete"] = 1;
			}
		}
		if (is_array($categorizedRecords["insert"]))	{
			reset($categorizedRecords["insert"]);
			$c=0;
			while(list(,$rec)=each($categorizedRecords["insert"]))	{
				$c++;
				$data["tt_address"]["NEW".$c] = $rec;
				$data["tt_address"]["NEW".$c]["pid"] = $this->id;
			}
		}
		if (is_array($categorizedRecords["update"]))	{
			reset($categorizedRecords["update"]);
			$c=0;
			while(list($rUid,$rec)=each($categorizedRecords["update"]))	{
				$c++;
				$data["tt_address"][$rUid]=$rec;
			}
		}
		
		$tce = t3lib_div::makeInstance("t3lib_TCEmain");
		$tce->stripslashes_values=0;
		$tce->enableLogging=0;
		$tce->start($data,$cmd);
		$tce->process_datamap();
		$tce->process_cmdmap();
/*		debug($data);
		debug($cmd);
		*/
	}
	function fetchRecordsListValues($listArr,$table,$fields="uid,name,email")	{
		$count = 0;
		$outListArr = array();
		if (is_array($listArr) && count($listArr))	{
			$idlist = implode(",",$listArr);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, 'uid IN ('.$idlist.')'.t3lib_befunc::deleteClause($table));
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$outListArr[$row["uid"]] = $row;
			}
		}
		return $outListArr;
	}
	function getRecordList($listArr,$table,$dim=0,$editLinkFlag=1)	{
		$count=0;
		$lines=array();
		$out="";
		if (is_array($listArr))	{
			$count=count($listArr);
			reset($listArr);
			while(list(,$row)=each($listArr))	{
				if ($editLinkFlag)	{
					$editLink = '<td><a href="index.php?id='.$this->id.'&CMD=displayUserInfo&table='.$table.'&uid='.$row["uid"].'"><img src="'.$GLOBALS["BACK_PATH"].'gfx/zoom2.gif" width=12 height=12 hspace=5 border=0 title="Edit" align="top"></a></td>';
		//			debug($editLink,1);
//					break;
//					debug($editLink,1);
//					$editLink.= '<td>'.($row["module_sys_dmail_html"]?"YES":"").'</td>';
				}
				$lines[]='<tr  class="bgColor4">
				<td>'.t3lib_iconWorks::getIconImage($table,array(),$GLOBALS["BACK_PATH"],'title="'.($row["uid"]?"uid: ".$row["uid"]:"").'"',$dim).'</td>
				'.$editLink.'
				<td nowrap>&nbsp;'.$row["email"].'&nbsp;</td>
				<td nowrap>&nbsp;'.$row["name"].'&nbsp;</td>
				</tr>';
			}
		}
		if (count($lines))	{
			$out="Number of records: <strong>".$count."</strong><BR>";
			$out.='<table border=0 cellspacing=1 cellpadding=0>'.implode(chr(10),$lines).'</table>';
		}
		return $out;
	}
	function cmd_displayImport()	{
		$indata = t3lib_div::_GP("CSV_IMPORT");
		if (is_array($indata))	{
			$records = $this->rearrangeCsvValues($this->getCsvValues($indata["csv"],$indata["sep"]));
			$categorizedRecords = $this->importRecords_sort($records,$indata["syncSelect"],$indata["tstamp"]);
			
			$theOutput.= $GLOBALS["SOBE"]->doc->section("INSERT RECORDS",$this->getRecordList($categorizedRecords["insert"],"tt_address",1));
			$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
			$theOutput.= $GLOBALS["SOBE"]->doc->section("UPDATE RECORDS",$this->getRecordList($categorizedRecords["update"],"tt_address",1));
			$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
			$theOutput.= $GLOBALS["SOBE"]->doc->section("NOT UPDATED - NEWER VERSION IN DATABASE",$this->getRecordList($categorizedRecords["newer_version_detected"],"tt_address",1));
			$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);

			if ($indata["doImport"])	{
				$this->importRecords($categorizedRecords,$indata["syncSelect"]==-1?1:0);
			}
		}
		if (!is_array($indata) || $indata["test_only"])	{
			$importButton=is_array($indata) ? '<input type="submit" name="CSV_IMPORT[doImport]" value="Import(!)">' : '';
				// Selector, mode
			if (!isset($indata["syncSelect"]))	$indata["syncSelect"]="email";
			$opt=array();
			$opt[]='<option value="email"'.($indata["syncSelect"]=="email"?" selected":"").'>Email</option>';
			$opt[]='<option value="name"'.($indata["syncSelect"]=="name"?" selected":"").'>Name</option>';
			$opt[]='<option value="uid"'.($indata["syncSelect"]=="uid"?" selected":"").'>uid</option>';
			$opt[]='<option value="phone"'.($indata["syncSelect"]=="phone"?" selected":"").'>phone</option>';
			$opt[]='<option value="0"'.($indata["syncSelect"]=="0"?" selected":"").'>[Import ALL]</option>';
			$opt[]='<option value="-1"'.($indata["syncSelect"]=="-1"?" selected":"").'>[Import ALL and Remove Existing]</option>';
			$selectSync='<select name="CSV_IMPORT[syncSelect]">'.implode("",$opt).'</select>';
				// Selector, sep
			if (!isset($indata["sep"]))	$indata["sep"]=",";
			$opt=array();
			$opt[]='<option value=","'.($indata["sep"]==","?" selected":"").'>, (comma)</option>';
			$opt[]='<option value=";"'.($indata["sep"]==";"?" selected":"").'>; (semicolon)</option>';
			$opt[]='<option value=":"'.($indata["sep"]==":"?" selected":"").'>: (colon)</option>';
			$sepSync='<select name="CSV_IMPORT[sep]">'.implode("",$opt).'</select>';

			$out='<textarea name="CSV_IMPORT[csv]" rows="25" wrap="off"'.$GLOBALS["SOBE"]->doc->formWidthText(48,"","off").'>'.t3lib_div::formatForTextarea($indata["csv"]).'</textarea><BR>
			<br>
						<strong>Rules:</strong><hr>
			Update existing records based on the '.$selectSync.'-field being unique. Import the rest.
			<hr>
			<input type="checkbox" name="CSV_IMPORT[tstamp]" value="1"'.(($importButton && !$indata["tstamp"])?"":" checked").'>Update only records where the time stamp (tstamp) is NOT newer than the imported.
			<hr>
			'.$sepSync.' Separator character.
			<hr>
			<input type="submit" name="CSV_IMPORT[test_only]" value="Test import (no data written)"> &nbsp; &nbsp; '.$importButton.'
			<input type="hidden" name="CMD" value="displayImport">
			';
		}
		$theOutput.= $GLOBALS["SOBE"]->doc->section("IMPORT CSV into 'ADDRESS' table",$out);
		return $theOutput;
	}
	function cmd_displayUserInfo()	{
		global $HTTP_POST_VARS;
		$uid = intval(t3lib_div::_GP("uid"));
		
		unset($row);
		$table=t3lib_div::_GP("table");

		switch($table)	{
			case "tt_address":
			case "fe_users":
				if (is_array($HTTP_POST_VARS["indata"]))	{
					$data=array();
					if (is_array($HTTP_POST_VARS["indata"]["categories"]))	{
						reset($HTTP_POST_VARS["indata"]["categories"]);
						while(list($recUid,$recValues)=each($HTTP_POST_VARS["indata"]["categories"]))	{
							reset($recValues);
							$data[$table][$uid]["module_sys_dmail_category"]=0;
							while(list($k,$b)=each($recValues))	{
								if ($b)	{$data[$table][$uid]["module_sys_dmail_category"]|= pow (2,$k);}
							}
						}
					}
					//debug($data[$table][$uid]["module_sys_dmail_category"]);
//					debug($HTTP_POST_VARS["indata"]["categories"]);
					
					$data[$table][$uid]["module_sys_dmail_html"] = $HTTP_POST_VARS["indata"]["html"] ? 1 : 0;
					$tce = t3lib_div::makeInstance("t3lib_TCEmain");
					$tce->stripslashes_values=0;
					$tce->start($data,Array());
					$tce->process_datamap();
//								debug($data);
				}
			break;
		}

		switch($table)	{
			case "tt_address":
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tt_address.*', 'tt_address,pages', 'pages.uid=tt_address.pid AND tt_address.uid='.intval($uid).' AND '.$this->perms_clause.t3lib_BEfunc::deleteClause('tt_address').t3lib_BEfunc::deleteClause('pages'));
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			break;
			case "fe_users":
			
			break;
		}
		if (is_array($row))	{
			$Eparams="&edit[".$table."][".$row["uid"]."]=edit";
			$out="";
			$out.='<img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon($table,$row).'" width=18 height=16 border=0 title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row["pid"],$this->perms_clause,40)).'" align=top>'.$row["name"].htmlspecialchars(" <".$row["email"].">");
			$out.='&nbsp;&nbsp;<A HREF="#" onClick="'.t3lib_BEfunc::editOnClick($Eparams,$GLOBALS["BACK_PATH"],"").'"><img src="'.$GLOBALS["BACK_PATH"].'gfx/edit2.gif" width=11 height=12 hspace=2 border=0 title="Edit" align="top">'.fw("<B>EDIT</B>").'</a>';
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Subscriber info",$out);



			$out="";
			$out_check="";
			reset($this->categories);
			while(list($pKey,$pVal)=each($this->categories))	{
				$out_check.='<input type="hidden" name="indata[categories]['.$row["uid"].']['.$pKey.']" value="0"><input type="checkbox" name="indata[categories]['.$row["uid"].']['.$pKey.']" value="1"'.(($row["module_sys_dmail_category"]&pow(2,$pKey)) ?" checked":"").'> '.$pVal.'<BR>';
			}
			$out_check.='<BR><BR><input type="hidden" name="indata[html]" value="0"><input type="checkbox" name="indata[html]" value="1"'.($row["module_sys_dmail_html"]?" checked":"").'> ';
			$out_check.='Receive HTML based mails<BR>';
			$out.=fw($out_check);
			
			$out.='<input type="hidden" name="table" value="'.$table.'"><input type="hidden" name="uid" value="'.$uid.'"><input type="hidden" name="CMD" value="'.$this->CMD.'"><BR><input type="submit" value="Update profile settings">';
			$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Subscriber profile","Set categories of interest for the subscriber.<BR>".$out);
		}
		return $theOutput;
	}	
	
	/**
	 * 
	 */
	function cmd_default($mode)	{
		global $TCA,$LANG;
		
		switch($mode)	{
			case "direct":
					// Here the dmail list is rendered:
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
								'uid,pid,subject,tstamp,issent,renderedsize,attachment,type', 
								'sys_dmail', 
								'pid='.intval($this->id).' AND scheduled=0'.t3lib_BEfunc::deleteClause('sys_dmail'),
								'',
								$GLOBALS['TYPO3_DB']->stripOrderBy($TCA['sys_dmail']['ctrl']['default_sortby'])
							);
				$out="";
					$out.='<tr>
						<td class="bgColor5">'.fw("&nbsp;").'</td>
						<td class="bgColor5"><b>'.fw("Subject"."&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Last mod."."&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Sent?"."&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Size"."&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Attach."."&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Type"."&nbsp;&nbsp;").'</b></td>
					</tr>';
				while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					$out.='<tr>
						<td><img src="'.$GLOBALS["BACK_PATH"].'gfx/i/mail.gif" width=18 height=16 border=0 align="top"></td>
						<td>'.$this->linkDMail_record(fw(t3lib_div::fixed_lgd($row["subject"],30)."&nbsp;&nbsp;"),$row["uid"]).'</td>
						<td>'.fw(t3lib_BEfunc::date($row["tstamp"])."&nbsp;&nbsp;").'</td>
						<td>'.($row["issent"] ? fw("YES") : "").'</td>
						<td>'.($row["renderedsize"] ? fw(t3lib_div::formatSize($row["renderedsize"])."&nbsp;&nbsp;") : "").'</td>
						<td>'.($row["attachment"] ? '<img src="attach.gif" width=9 height=13>' : "").'</td>
						<td>'.fw($row["type"] ? 'EXT URL' : 'PAGE').'</td>
					</tr>';
					
				}
				$out='<table border=0 cellpadding=0 cellspacing=0>'.$out.'</table>';
				$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("dmail_dovsk_selectDmail"),$out,1,1);
				

					// Find all newsletters NOT created as DMAILS: // NOTICE: Hardcoded PID - hardly what we want!
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pages.uid,pages.title', 'pages LEFT JOIN sys_dmail ON pages.uid=sys_dmail.page', 'sys_dmail.page is NULL AND pages.pid=47'.t3lib_BEfunc::deleteClause('sys_dmail').t3lib_BEfunc::deleteClause('pages'));
				if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res))	{
					$out = $LANG->getLL("dmail_msg1_crFromNL");
				} else {
					$out = "";
					while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
						$out.= '<nobr><a href="index.php?id='.$this->id.'&createMailFrom='.$row["uid"].'&SET[dmail_mode]=direct"><img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("pages",$row).'" width=18 height=16 border=0 title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row["uid"],$this->perms_clause,20)).'" align=top>'.
							$row["title"]."</a></nobr><BR>";
					}
				}
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("dmail_dovsk_crFromNL"),$out,1,1);
				
				
					// Create
				$out='
				HTML URL:<br>
				<input type="text" value="http://" name="createMailFrom_HTMLUrl"'.$GLOBALS["TBE_TEMPLATE"]->formWidth(40).'><br>
				Plain Text URL:<br>
				<input type="text" value="http://" name="createMailFrom_plainUrl"'.$GLOBALS["TBE_TEMPLATE"]->formWidth(40).'><br>
				Subject:<br>
				<input type="text" value="[write subject]" name="createMailFrom" onfocus="this.value=\'\';"'.$GLOBALS["TBE_TEMPLATE"]->formWidth(40).'><br>
				<input type="submit" value="'.$LANG->getLL("dmail_createMail").'">
				';
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("dmail_dovsk_crFromUrl"),$out,1,1);


			break;
			case "news":
					// Here the list of subpages, news, is rendered:
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,doktype,title,abstract', 'pages', 'pid='.intval($this->id).' AND doktype IN ('.$GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'].') AND '.$this->perms_clause.t3lib_BEfunc::deleteClause('pages').t3lib_pageSelect::enableFields('pages'), '', 'sorting');
				if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res))	{
					$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("nl_select"),$LANG->getLL("nl_select_msg1"),0,1);
				} else {
					$out="";
					while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
						$out.='<nobr><a href="index.php?id='.$this->id.'&CMD=displayPageInfo&pages_uid='.$row["uid"].'&SET[dmail_mode]=news"><img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("pages",$row).'" width=18 height=16 border=0 title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row["uid"],$this->perms_clause,20)).'" align=top>'.
							$row["title"]."</a></nobr><BR>";
					}
					$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("nl_select"),$out,0,1);
				}
								
					// Create a new page
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("nl_create"),'<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[pages]['.$this->id.']=new&edit[tt_content][prev]=new',$GLOBALS["BACK_PATH"],"").'"><b>'.$LANG->getLL("nl_create_msg1").'</b></a>',0,1);
			break;
			case "recip":
					// Create a query...
//				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(10);

//				$theOutput.= $GLOBALS["SOBE"]->doc->section("qUERY...",'<nobr><a href="index.php?id='.$this->id.'&CMD=displayQuery">QUERY</a></nobr><BR>');
					// Display mailer engine status
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,title,description,type', 'sys_dmail_group', 'pid='.intval($this->id).t3lib_BEfunc::deleteClause('sys_dmail_group'), '', $GLOBALS['TYPO3_DB']->stripOrderBy($TCA['sys_dmail_group']['ctrl']['default_sortby']));
				$out = "";
					$out.='<tr>
						<td class="bgColor5" colspan=2>'.fw("&nbsp;").'</td>
						<td class="bgColor5"><b>'.fw($LANG->sL(t3lib_BEfunc::getItemLabel("sys_dmail_group","title"))).'</b></td>
						<td class="bgColor5"><b>'.fw($LANG->sL(t3lib_BEfunc::getItemLabel("sys_dmail_group","type"))).'</b></td>
						<td class="bgColor5"><b>'.fw($LANG->sL(t3lib_BEfunc::getItemLabel("sys_dmail_group","description"))).'</b></td>
					</tr>';
				$TDparams=' valign=top';
				while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					$out.='<tr>
						<td'.$TDparams.' nowrap><img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("sys_dmail_group").'" width=18 height=16 border=0 align="top"></td>
						<td'.$TDparams.'>'.$this->editLink("sys_dmail_group",$row["uid"]).'</td>
						<td'.$TDparams.' nowrap>'.$this->linkRecip_record(fw("<strong>".t3lib_div::fixed_lgd($row["title"],30)."</strong>&nbsp;&nbsp;"),$row["uid"]).'</td>
						<td'.$TDparams.' nowrap>'.fw(htmlspecialchars(t3lib_BEfunc::getProcessedValue("sys_dmail_group","type",$row["type"]))."&nbsp;&nbsp;").'</td>
						<td'.$TDparams.'>'.fw(htmlspecialchars(t3lib_BEfunc::getProcessedValue("sys_dmail_group","description",$row["description"]))."&nbsp;&nbsp;").'</td>
					</tr>';
					
				}
				$out='<table border=0 cellpadding=0 cellspacing=0>'.$out.'</table>';
				
				$theOutput.= $GLOBALS["SOBE"]->doc->section("Select a Mail Group",$out,0,1);

				// New:
				$out='<a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[sys_dmail_group]['.$this->id.']=new',$GLOBALS["BACK_PATH"],"").'">'.t3lib_iconWorks::getIconImage("sys_dmail_group",array(),$GLOBALS["BACK_PATH"],'align=top').'Create new?</a>';
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section("New mail group?",$out);

				// Import
				$out='<a href="index.php?id='.$this->id.'&CMD=displayImport">Click here to import CSV</a>';
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section("Import",$out);
			break;
			case "mailerengine":
				if (t3lib_div::_GP("invokeMailerEngine"))	{
					$out="<strong>Log:</strong><BR><BR><font color=#666666>".nl2br($this->invokeMEngine())."</font>";
					$theOutput.= $GLOBALS["SOBE"]->doc->section("Mailer Engine Invoked!",$out);
					$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				}

					// Display mailer engine status
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
								'uid,pid,subject,scheduled,scheduled_begin,scheduled_end', 
								'sys_dmail', 
								'pid='.intval($this->id).' AND scheduled>0'.t3lib_BEfunc::deleteClause('sys_dmail'), 
								'', 
								$GLOBALS['TYPO3_DB']->stripOrderBy($TCA['sys_dmail']['ctrl']['default_sortby'])
							);
				$out="";
					$out.='<tr>
						<td class="bgColor5">'.fw("&nbsp;").'</td>
						<td class="bgColor5"><b>'.fw("Subject&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Scheduled&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Delivery begun&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("Delivery ended&nbsp;&nbsp;").'</b></td>
						<td class="bgColor5"><b>'.fw("&nbsp;# sent&nbsp;").'</b></td>
					</tr>';
				while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					$countres = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*)', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=0');
					list($count) = $GLOBALS['TYPO3_DB']->sql_fetch_row($countres);
					
					$out.='<tr>
						<td><img src="'.$GLOBALS["BACK_PATH"].'gfx/i/mail.gif" width=18 height=16 border=0 align="top"></td>
						<td>'.$this->linkDMail_record(fw(t3lib_div::fixed_lgd($row["subject"],30)."&nbsp;&nbsp;"),$row["uid"]).'</td>
						<td>'.fw(t3lib_BEfunc::datetime($row["scheduled"])."&nbsp;&nbsp;").'</td>
						<td>'.fw(($row["scheduled_begin"]?t3lib_BEfunc::datetime($row["scheduled_begin"]):"")."&nbsp;&nbsp;").'</td>
						<td>'.fw(($row["scheduled_end"]?t3lib_BEfunc::datetime($row["scheduled_end"]):"")."&nbsp;&nbsp;").'</td>
						<td align=right>'.fw($count?$count:"&nbsp;").'</td>
					</tr>';
					
				}
				$out='<table border=0 cellpadding=0 cellspacing=0>'.$out.'</table>';
				$out.='<BR>Current time: '.t3lib_BEfunc::datetime(time())."<BR>";
				
				$theOutput.= $GLOBALS["SOBE"]->doc->section("Mail Engine Status",$out,0,1);
					// Invoke engine
					
				$out='If TYPO3 is not configured to automatically invoke the Mailer Engine, you can invoke it by clicking here:<BR><BR>&nbsp; &nbsp; &nbsp; &nbsp;<a href="index.php?id='.$this->id.'&invokeMailerEngine=1"><strong>Invoke Mailer Engine</strong></a>';
				$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
				$theOutput.= $GLOBALS["SOBE"]->doc->section("Manually Invoke Engine",$out);
			break;
			case "quick":
				$theOutput.= $this->cmd_quickmail();
			break;
			case "conf":
				$configArray = array(
					"spacer0" => "Set default values for Direct Mails:",
					"from_email" => array("string", "'From' email", "Enter the sender email address. (Required)"),
					"from_name" => array("string", "'From' name", "Enter the sender name. (Required)"),
					"replyto_email" => array("string", "'Reply To' email", "Enter the email address to which replys are sent. If none, the 'From' email is used. (Optional)"),
					"replyto_name" => array("string", "'Reply To' name", "Enter the name of the 'Reply To' email address. If none, the 'From' name is used. (Optional)"),
					"return_path" => array("string", "'Return Path'", "Enter the return path email address here. This is the address to which non-deliverable mails will be returned to. If you put in the marker ###XID### it'll be substituted with the unique id of the mail/recipient."),
					"organisation" => array("string", "Organisation name", "(Optional)"),
					"spacer1" => "",
					"sendOptions" => array("select", "Default Format options", "Select the format of the mail content. If in doubt, set it to 'Plain and HTML'. The recipients are normally able to select their preferences anyway.", array(0=>"",1=>"Plain text only",2=>"HTML only",3=>"Plain and HTML")),
					"HTMLParams" => array("short", "HTML parameters", "Enter the additional URL parameters used to fetch the HTML content. If in doubt, leave it blank."),
					"plainParams" => array("short", "Plain Text parameters", "Enter the additional URL parameters used to fetch the plain text content. If in doubt, set it to '&type=99' which is standard."),

					"long_link_rdct_url" => array("string", "Long link RDCT url", "If you enter a http://../ url here it should point to the index.php script of typo3 without any query-string. Then the parameter ?RDCT=[md5hash] will be appended and the whole url used as substitute for long urls in plain text mails. This configuration determines how QuickMails are handled and further sets the default setting for DirectMails."),
					"long_link_mode" => array("check", "Not only links longer than 76 chars but ALL links", "Option for the RDCT-url feature above."),
					"quick_mail_encoding" => array("select", "Encoding for quick mails", "Select the encoding you want to sending of quick-mails.", array(0=>"","base64"=>"base64","quoted-printable"=>"quoted-printable","8bit"=>"8bit")),
					"spacer2" => "Configure technical options",
					"enablePlain" => array("check", "Allow Plain Text emails", "Set this if you want to allow plain text emails to be fetched. If in doubt, check this option."),
					"enableHTML" => array("check", "Allow HTML emails", "Set this if you want to allow HTML emails to be fetched. If in doubt, check this option."),
					"http_username" => array("short", "HTTP username", "If the mail content is protected by a HTTP authentication, enter the username here. The username and password is used to fetch the mail content. They are NOT sent in the mail!<BR>If you don't enter a username and password and the newsletter pages happens to be protected, an error will occur and no mail content is fetched."),
					"http_password" => array("short", "HTTP password", "... and enter the password here."),
					"test_tt_address_uids" => array("short", "List of UID numbers of test-recipients", "Before sending mails you should test the mail content by sending testmails to one or more test recipients. The available recipients for testing are determined by the list of UID numbers, you enter here. So first, find out the UID-numbers of the recipients you wish to use for testing, then enter them here in a comma-separated list."),
					"test_dmail_group_uids" => array("short", "List of UID numbers of test dmail_groups", "Alternatively to sending test-mails to individuals, you can choose to send to a whole group. List the group ids available for this action here:"),
					"spacer3" => "Available categories"
				);
				for ($a=0;$a<9;$a++)	{
					$configArray["categories.".$a] = array("short", "Category ".$a, "");
				}
				$configArray["spacer4"] = array("comment","","(You can use categories from 0-30 inclusive. However this interface shows only 10 categories for your convenience.)");

				$theOutput.= $GLOBALS["SOBE"]->doc->section("Configure direct mail module",t3lib_BEfunc::makeConfigForm($configArray,$this->implodedParams,"pageTS"),0,1);
			break;
			case "help":
//				if ($GLOBALS["BE_USER"]->uc["helpText"])	{
					// Make this a link to the online documentation instead!!!
					// How this works.
				$theOutput.= $GLOBALS["SOBE"]->doc->section("How this works...",nl2br(trim("
	In this module you can create newsletters (pages), which can be emailed as 'direct mails' to people on a subscription list.
	
	To create a direct mail, you must follow these steps:
	<B>1)</B> Select 'Newsletter' in the menu above.
	<B>2)</B> Create a new 'newsletter'. Put content in that newsletter, save it, preview it - exactly as you're used to with regular pages in TYPO3. Actually a 'newsletter' in this context is simply a TYPO3 page destined for emailing!
	<B>3)</B> Click your new newsletter in the list. Now you can see information about that page, categorize the content elements.
	<B>4)</B> When your newsletter is ready to be distributed, click the link 'Create new direct mail based on this page' and a new direct mail based on your newsletter is created.
	<B>5)</B> The first thing to do with your new 'Direct Mail' is to fetch the mail content. This process reads the content from the page and compiles a mail out of it.
	<B>6)</B> Send a test. You should definitely send a testmail to yourself before mailing to your subscribers. Doing so, you can make sure that the mail and all links in it are correctly set up. Be aware if there are links to local network URLs. Those will not be accessible to the people receiving your newsletter!
	<B>7)</B> Initialize distribution if everything is OK.
	
	<B>The difference of a newsletter and a direct mail</B>
	A 'newsletter' is basically a regular TYPO3 page which resides here in the direct mail module. You can view the page in a browser and the point is that this page is finally send as a direct mail.
	A 'direct mail' is a record that contains a compiled version of either a newsletter page or alternatively the content of an external url. In addition the direct mail contains information like the mail subject, any attachments, priority settings, reply addresses and all that. For each direct mail a log is kept of who has received the direct mail and if they responded to it.
	
				
	<B>Data fields in direct mails:</B>
	You can insert personalized data in the mails by inserting these markers:
	###USER_uid### (the unique id of the recipient)
	###USER_name### (full name)
	###USER_firstname### (first name calculated)
	###USER_title###
	###USER_email###
	###USER_phone###
	###USER_www###
	###USER_address###
	###USER_company###
	###USER_city###
	###USER_zip###
	###USER_country###
	###USER_fax###
	
	###SYS_TABLE_NAME###
	###SYS_MAIL_ID###
	###SYS_AUTHCODE###
	
	(In addition ###USER_NAME### and ###USER_FIRSTNAME### will insert uppercase versions of the equalents)
				")),0,1);
	//			}
			break;
		}
		return $theOutput;
	}
	function editLink($table,$uid)	{
		$params = '&edit['.$table.']['.$uid.']=edit';
		$str = '<a href="#" onClick="'.t3lib_BEfunc::editOnClick($params,$GLOBALS["BACK_PATH"],"").'"><img src="'.$GLOBALS["BACK_PATH"].'gfx/edit2.gif" width="11" height="12" hspace=3 vspace=2 border="0" align=top></a>';
		return $str;
	}
	function invokeMEngine()	{
		$htmlmail = t3lib_div::makeInstance("t3lib_dmailer");
		$htmlmail->start();
		$htmlmail->runcron();
		return implode(chr(10),$htmlmail->logArray);
	}
	function updatePageTS()	{
		$pageTS = t3lib_div::_GP("pageTS");
		if (is_array($pageTS))	{
			t3lib_BEfunc::updatePagesTSconfig($this->id,$pageTS,$this->TSconfPrefix);
//			debug(t3lib_div::getIndpEnv("REQUEST_URI"));
			header("Location: ".t3lib_div::locationHeaderUrl(t3lib_div::getIndpEnv("REQUEST_URI")));
		}
	}
	function addUserPass($url)	{
		$user = $this->params["http_username"];
		$pass = $this->params["http_password"];
		
		if ($user && $pass && substr($url,0,7)=="http://")	{
			$url = "http://".$user.":".$pass."@".substr($url,7);
		}
		return $url;
	}
	function cmd_fetch($row)	{
		// Compile the mail
		$htmlmail = t3lib_div::makeInstance("t3lib_dmailer");
		$htmlmail->jumperURL_prefix = $this->urlbase."?id=".$row["page"]."&rid=###SYS_TABLE_NAME###_###USER_uid###&mid=###SYS_MAIL_ID###&jumpurl=";
		$htmlmail->jumperURL_useId=1;
		$htmlmail->start();
		$htmlmail->useBase64();
		$htmlmail->http_username = $this->params["http_username"];
		$htmlmail->http_password = $this->params["http_password"];
		
		if ($this->url_plain)	{
			$htmlmail->addPlain(t3lib_div::getURL($this->addUserPass($this->url_plain)));
		}
		if ($this->url_html)	{
			$htmlmail->addHTML($this->url_html);	// Username and password is added in htmlmail object
		}
//		debug($htmlmail->theParts);
//		debug(base64_decode($htmlmail->theParts["plain"]["content"]));

		$attachmentArr = t3lib_div::trimExplode(",", $row["attachment"],1);
		if (count($attachmentArr))	{
			t3lib_div::loadTCA("sys_dmail");
			$upath = $GLOBALS["TCA"]["sys_dmail"]["columns"]["attachment"]["config"]["uploadfolder"];
			while(list(,$theName)=each($attachmentArr))	{
				$theFile = PATH_site.$upath."/".$theName;
				if (@is_file($theFile))	{
					$htmlmail->addAttachment($theFile, $theName);
				}
			}
		}
		
			// Update the record:
		$htmlmail->theParts["messageid"] = $htmlmail->messageid;
		$mailContent = serialize($htmlmail->theParts);

		$updateFields = array(
			'issent' => 0,
			'mailContent' => $mailContent,
			'renderedSize' => strlen($mailContent)
		);

		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_dmail', 'uid='.intval($this->sys_dmail_uid), $updateFields);

			// Read again:
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_dmail', 'pid='.intval($this->id).' AND uid='.intval($this->sys_dmail_uid).t3lib_BEfunc::deleteClause('sys_dmail'));
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		return $theOutput;
	}
	function cmd_prefetch($row)	{
		if ($row["sendOptions"])	{
			$msg = "You're about to read the url with the mail-content from these URL's:<BR><BR>";

			if ($this->url_plain)	{
				$msg.= '<B>PLAIN:</b> <a href="'.$this->url_plain.'" target="testing_window">'.htmlspecialchars($this->url_plain).'</a><BR>';
			}
			if ($this->url_html)	{
				$msg.= '<B>HTML:</b> <a href="'.$this->url_html.'" target="testing_window">'.htmlspecialchars($this->url_html).'</a><BR><BR>';
			}

			$msg.= 'This operation may take a while. Therefore be patient.<BR><BR>
			Be aware if the URL is password protected, you must set up the http username and password in the "Configuration" section. Else you will get an error.<BR><BR>';
			$msg.= '<input type="Submit" value="Read URL" onClick="jumpToUrlD(\'index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=fetch\'); return false;"> &nbsp;';
			$msg.= $this->back;
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Fetching the mail content",fw($msg));
		} else {
			$theOutput.= $GLOBALS["SOBE"]->doc->section("ERROR",fw("You must choose to send either HTML, plaintext or both. Nothing is selected at this time!<br><br>".$this->back));
		}
		$this->noView=1;
		return $theOutput;
	}
	function cmd_testmail($row)	{
		if ($this->params["test_tt_address_uids"])	{
			$intList = implode(t3lib_div::intExplode(",",$this->params["test_tt_address_uids"]),",");
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tt_address.*', 'tt_address,pages', 'pages.uid=tt_address.pid AND tt_address.uid IN ('.$intList.') AND '.$this->perms_clause.t3lib_BEfunc::deleteClause('tt_address').t3lib_BEfunc::deleteClause('pages'));
			$msg = "Select a recipient of the testmail. The mail will be generated based on the profile of the recipient, you select.<BR><BR>";
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$msg.='<a href="index.php?id='.$this->id.'&CMD=displayUserInfo&table=tt_address&uid='.$row["uid"].'"><img src="'.$GLOBALS["BACK_PATH"].'gfx/zoom2.gif" width=12 height=12 hspace=5 border=0 title="Edit" align="top"></a><a href="index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=send_mail_test&tt_address_uid='.$row["uid"].'"><img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("tt_address", $row).'" width=18 height=16 align=top border=0>'.htmlspecialchars($row["name"]." <".$row["email"].">".($row["module_sys_dmail_html"]?" html":""))."</a><BR>";
			}
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Testmail - Individuals",fw($msg));
			$theOutput.= $GLOBALS["SOBE"]->doc->divider(20);
		}


		if ($this->params["test_dmail_group_uids"])	{
			$intList = implode(t3lib_div::intExplode(",",$this->params["test_dmail_group_uids"]),",");
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('sys_dmail_group.*', 'sys_dmail_group,pages', 'pages.uid=sys_dmail_group.pid AND sys_dmail_group.uid IN ('.$intList.') AND '.$this->perms_clause.t3lib_BEfunc::deleteClause('sys_dmail_group').t3lib_BEfunc::deleteClause('pages'));
			$msg = "Select a recipient mail group for the testmail. The mails will be generated based on the profiles of the recipients in that group.<BR><BR>";
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$msg.='<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=send_mail_test&sys_dmail_group_uid='.$row["uid"].'"><img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("sys_dmail_group", $row).'" width=18 height=16 align=top border=0>'.htmlspecialchars($row["title"])."</a><BR>";
					// Members:
				$result = $this->cmd_compileMailGroup(intval($row["uid"]));
				$msg.='<table border=0>
				<tr>
					<td><img src=clear.gif width=50 height=1></td>
					<td>'.$this->cmd_displayMailGroup_test($result).'</td>
				</tr>
				</table>';
			}
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Testmail - Direct Mail Group",fw($msg));
			$theOutput.= $GLOBALS["SOBE"]->doc->divider(20);
		}

			
		$msg="";
		$msg.= 'A simple testmail includes all mail elements regardless of category. But any USER_fields are not substituted with data.<BR>Enter an email-address for the testmail:<BR><BR>';
		$msg.= '<input'.$GLOBALS["TBE_TEMPLATE"]->formWidth().' type="Text" name="SET[dmail_test_email]" value="'.$GLOBALS["SOBE"]->MOD_SETTINGS["dmail_test_email"].'"><BR><BR>';
		$msg.= '<input type="hidden" name="id" value="'.$this->id.'">';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_test">';
		$msg.= '<input type="Submit" name="mailingMode_simple" value="Send">';

		$theOutput.= $GLOBALS["SOBE"]->doc->section("Testmail - simple",fw($msg));

		$theOutput.= $GLOBALS["SOBE"]->doc->spacer(20);
		$theOutput.= $GLOBALS["SOBE"]->doc->section("",$this->back);		

		$this->noView=1;
		return $theOutput;
	}
	
	function cmd_finalmail($row)	{
		global $TCA;
			// Mail groups
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,title', 'sys_dmail_group', 'pid='.intval($this->id).t3lib_BEfunc::deleteClause('sys_dmail_group'), '', $GLOBALS['TYPO3_DB']->stripOrderBy($TCA['sys_dmail_group']['ctrl']['default_sortby']));
		$opt = array();
		$opt[] = '<option></option>';
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$opt[] = '<option value="'.$row["uid"].'">'.htmlspecialchars($row["title"]).'</option>';
		}
		$select = '<select name="mailgroup_uid">'.implode(chr(10),$opt).'</select>';

			// Set up form:		
		$msg="";
		$msg.= '<input type="hidden" name="id" value="'.$this->id.'">';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_final">';
		$msg.= $select."<BR>";
		$msg.='Distribution time (hh:mm dd-mm-yy):<BR><input type="text" name="send_mail_datetime_hr'.'" onChange="typo3FormFieldGet(\'send_mail_datetime\', \'datetime\', \'\', 0,0);"'.$GLOBALS["TBE_TEMPLATE"]->formWidth(20).'><input type="hidden" value="'.time().'" name="send_mail_datetime"><BR>';
		$this->extJSCODE.='typo3FormFieldSet("send_mail_datetime", "datetime", "", 0,0);';
		$msg.= '<input type="Submit" name="mailingMode_mailGroup" value="Send to all subscribers in mail group">';


		
		$theOutput.= $GLOBALS["SOBE"]->doc->section("Select a Mail Group",fw($msg));
		$theOutput.= $GLOBALS["SOBE"]->doc->divider(20);


		$msg="";
		$msg.= 'Enter the email-addresses for the recipients. Separate the addresses with linebreak or comma ",":<BR><BR>';
		$msg.= '<textarea'.$GLOBALS["TBE_TEMPLATE"]->formWidthText().' rows=30 name="email"></textarea><BR><BR>';
		$msg.= '<input type="hidden" name="id" value="'.$this->id.'">';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_final">';
		$msg.= '<input type="Submit" name="mailingMode_simple" value="Send">';
		
		$msg.=$this->JSbottom();

		$theOutput.= $GLOBALS["SOBE"]->doc->section("Send mail - list of emails",fw($msg));
		$this->noView=1;
		return $theOutput;
	}
	function getUniqueEmailsFromGroup($mailgroup_uid)	{
		$result = $this->cmd_compileMailGroup(intval($mailgroup_uid));
		$idLists = $result["queryInfo"]["id_lists"];
		$emailArr = array();
		$emailArr = $this->addMailAddresses($idLists,"tt_address",$emailArr);
		$emailArr = $this->addMailAddresses($idLists,"fe_users",$emailArr);
		$emailArr = $this->addMailAddresses($idLists,"PLAINLIST",$emailArr);
		$emailArr = $this->addMailAddresses($idLists,$this->userTable,$emailArr);
		$emailArr = array_unique($emailArr);
		return $emailArr;
	}
	function cmd_quickmail()	{
		global $TCA,$BE_USER;
		$theOutput="";
		$whichMode="";
		
			// Check if send mail:
		if (t3lib_div::_GP("quick_mail_send"))	{
			$mailgroup_uid = t3lib_div::_GP("mailgroup_uid");
			$senderName = t3lib_div::_GP("senderName");
			$senderEmail = t3lib_div::_GP("senderEmail");
			$subject = t3lib_div::_GP("subject");
			$message = t3lib_div::_GP("message");
			$sendMode = t3lib_div::_GP("sendMode");
			$breakLines = t3lib_div::_GP("breakLines");
			if ($mailgroup_uid && $senderName && $senderEmail && $subject && $message)	{

				$emailArr = $this->getUniqueEmailsFromGroup($mailgroup_uid);

				if (count($emailArr))	{
					if (trim($this->params["long_link_rdct_url"]))	{
#debug(array($this->params["long_link_rdct_url"],$this->params["long_link_mode"]?"all":"76"));
						$message = t3lib_div::substUrlsInPlainText($message,$this->params["long_link_mode"]?"all":"76",trim($this->params["long_link_rdct_url"]));
					}
					if ($breakLines)	{
						$message = t3lib_div::breakTextForEmail($message);
					}
					
					$headers=array();
					$headers[]="From: ".$senderName." <".$senderEmail.">";
					$headers[]="Return-path: ".$senderName." <".$senderEmail.">";

					if ($sendMode=="CC")	{
						$headers[]="Cc: ".implode(",",$emailArr);
						t3lib_div::plainMailEncoded($senderEmail,$subject,$message,implode(chr(13).chr(10),$headers),$this->params["quick_mail_encoding"]);
						$whichMode="One mail to all recipients in Carbon Copy (Cc)";
					}
					if ($sendMode=="BCC")	{
						$headers[]="Bcc: ".implode(",",$emailArr);
						t3lib_div::plainMailEncoded($senderEmail,$subject,$message,implode(chr(13).chr(10),$headers),$this->params["quick_mail_encoding"]);
						$whichMode="One mail to all recipients in Blind Carbon Copy (Bcc)";
					}
					if ($sendMode=="1-1")	{
						reset($emailArr);
						while(list(,$email)=each($emailArr))	{
							t3lib_div::plainMailEncoded($email,$subject,$message,implode(chr(13).chr(10),$headers),$this->params["quick_mail_encoding"]);
						}
						$whichMode="Many mails, one for each recipient";
					}
					$msg="<strong>Sent to:</strong>";
					$msg.='<table border=0>
					<tr>
						<td><img src=clear.gif width=50 height=1></td>
						<td><em>'.implode(", ",$emailArr).'</em></td>
					</tr>
					</table>
					<BR>
					<strong>Copy to:</strong> '.$senderEmail.'<BR>
					<strong>Mode:</strong> '.$whichMode;
				} else {
					$msg="No recipients";
				}
				$theOutput.= $GLOBALS["SOBE"]->doc->section("Mails are sent",$msg,0,1);
			} else {
				$theOutput.= $GLOBALS["SOBE"]->doc->section("Error","You didn't fill in all the fields correctly!",0,1);
			}
		}

		if (!$whichMode)	{
				// Mail groups
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,pid,title', 'sys_dmail_group', 'pid='.intval($this->id).t3lib_BEfunc::deleteClause('sys_dmail_group'), '', $GLOBALS['TYPO3_DB']->stripOrderBy($TCA['sys_dmail_group']['ctrl']['default_sortby']));
			$opt = array();
			$opt[] = '<option></option>';
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$opt[] = '<option value="'.$row["uid"].'"'.($mailgroup_uid==$row["uid"]?" selected":"").'>'.htmlspecialchars($row["title"]).'</option>';
			}
			$select = '<select name="mailgroup_uid">'.implode(chr(10),$opt).'</select>';
	
			$selectMode = '<select name="sendMode">
			<option value="BCC"'.($sendMode=="BCC"?" selected":"").'>One mail to all recipients in Blind Carbon Copy (Bcc)</option>
			<option value="CC"'.($sendMode=="CC"?" selected":"").'>One mail to all recipients in Carbon Copy (Cc)</option>
			<option value="1-1"'.($sendMode=="1-1"?" selected":"").'>Many mails, one for each recipient</option>
			</select>';
				// Set up form:		
			$msg="";
			$msg.= '<input type="hidden" name="id" value="'.$this->id.'">';
			$msg.= $select.$selectMode."<BR>";
			if ($mailgroup_uid)	{
				$msg.='Recipients:<BR><em>'.implode(", ",$this->getUniqueEmailsFromGroup($mailgroup_uid)).'</em><BR><BR>';
			}
			$msg.= 'Sender Name:<BR><input type="text" name="senderName" value="'.($senderName?$senderName:$BE_USER->user["realName"]).'"'.$GLOBALS["SOBE"]->doc->formWidth().'><BR>';
			$msg.= 'Sender Email:<BR><input type="text" name="senderEmail" value="'.($senderEmail?$senderEmail:$BE_USER->user["email"]).'"'.$GLOBALS["SOBE"]->doc->formWidth().'><BR>';
			$msg.= 'Subject:<BR><input type="text" name="subject" value="'.$subject.'"'.$GLOBALS["SOBE"]->doc->formWidth().'><BR>';
			$msg.= 'Message:<BR><textarea rows="20" name="message"'.$GLOBALS["SOBE"]->doc->formWidthText().'>'.t3lib_div::formatForTextarea($message).'</textarea><BR>';
			$msg.= 'Break lines to 76 char: <input type="checkbox" name="breakLines" value="1"'.($breakLines?" checked":"").'><BR><BR>';
	
			$msg.= '<input type="Submit" name="quick_mail_send" value="Send message to all members of group immediately">';
			$msg.= '<BR><input type="Submit" name="cancel" value="Cancel">';
	
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Quick-Mail",$msg,0,1);
		}
		return $theOutput;
	}
	function sendTestMailToTable($idLists,$table,$htmlmail)	{
		$sentFlag=0;
		if (is_array($idLists[$table]))	{
			if ($table!="PLAINLIST")	{
				$recs=$this->fetchRecordsListValues($idLists[$table],$table,"*");
			} else {
				$recs = $idLists["PLAINLIST"];
			}
			reset($recs);
			while(list($k,$rec)=each($recs))	{
				$recipRow = t3lib_dmailer::convertFields($rec);
//				debug($recipRow);
				$kc = substr($table,0,1);
				$htmlmail->dmailer_sendAdvanced($recipRow,$kc=="p"?"P":$kc);
				$sentFlag++;
			}
		}
		return $sentFlag;
	}
	function addMailAddresses($idLists,$table,$arr)	{
		if (is_array($idLists[$table]))	{
			if ($table!="PLAINLIST")	{
				$recs=$this->fetchRecordsListValues($idLists[$table],$table,"*");
			} else {
				$recs = $idLists["PLAINLIST"];
			}
			reset($recs);
			while(list($k,$rec)=each($recs))	{
				$arr[]=$rec["email"];
			}
		}
		return $arr;
	}
	function cmd_send_mail($row)	{
		// Preparing mailer
		$htmlmail = t3lib_div::makeInstance("t3lib_dmailer");
		$htmlmail->start();
		$htmlmail->dmailer_prepare($row);	// ,$this->params
		
		$sentFlag=false;
		if (t3lib_div::_GP("mailingMode_simple"))	{
				// Fixing addresses:
		  $addressList = t3lib_div::_GP('email') ? t3lib_div::_GP('email') : $GLOBALS["SOBE"]->MOD_SETTINGS["dmail_test_email"]; 
			$addresses = split(chr(10)."|,|;",$addressList);
			while(list($key,$val)=each($addresses))	{
				$addresses[$key]=trim($val);
				if (!strstr($addresses[$key],"@"))	{
					unset($addresses[$key]);
				}
			}
			$hash = array_flip($addresses); 
			$addresses = array_keys($hash);
			$addressList = implode($addresses,",");
			
			if ($addressList)	{
					// Sending the same mail to lots of recipients
				$htmlmail->dmailer_sendSimple($addressList);
				$sentFlag=true;
				$theOutput.= $GLOBALS["SOBE"]->doc->section("Sending mail",fw("The mail was send.<BR><BR>Recipients:<BR>".$addressList."<BR><BR>".$this->back));
				$this->noView=1;
			}
		} else {	// extended, personalized emails.
			if ($this->CMD=="send_mail_test")	{
				if (t3lib_div::_GP("tt_address_uid"))	{
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tt_address.*', 'tt_address,pages', 'pages.uid=tt_address.pid AND tt_address.uid='.intval(t3lib_div::_GP('tt_address_uid')).' AND '.$this->perms_clause.t3lib_BEfunc::deleteClause('tt_address').t3lib_BEfunc::deleteClause('pages'));
					if ($recipRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
						$recipRow = t3lib_dmailer::convertFields($recipRow);
						$htmlmail->dmailer_sendAdvanced($recipRow,"t");
						$sentFlag=true;
						$theOutput.= $GLOBALS["SOBE"]->doc->section("Sending mail",fw(sprintf("The mail was send to <strong>%s</strong>.<BR><BR>".$this->back, $recipRow["name"].htmlspecialchars(" <".$recipRow["email"].">"))));
						$this->noView=1;
					}
				} elseif (t3lib_div::_GP("sys_dmail_group_uid"))	{
					$result = $this->cmd_compileMailGroup(t3lib_div::_GP("sys_dmail_group_uid"));
					
					$idLists = $result["queryInfo"]["id_lists"];
					$sendFlag=0;
					$sendFlag+=$this->sendTestMailToTable($idLists,"tt_address",$htmlmail);
					$sendFlag+=$this->sendTestMailToTable($idLists,"fe_users",$htmlmail);
					$sendFlag+=$this->sendTestMailToTable($idLists,"PLAINLIST",$htmlmail);
					$sendFlag+=$this->sendTestMailToTable($idLists,$this->userTable,$htmlmail);
//					debug($sendFlag);

					if ($sendFlag)	{
						$theOutput.= $GLOBALS["SOBE"]->doc->section("Sending mail",fw(sprintf("The mail was send to <strong>%s</strong> recipients.<BR><BR>".$this->back, $sendFlag)));
						$this->noView=1;
					}
				}
			} else {


				$mailgroup_uid = t3lib_div::_GP("mailgroup_uid");
				if (t3lib_div::_GP("mailingMode_mailGroup") && $this->sys_dmail_uid && intval($mailgroup_uid))	{
						// Update the record:
					$result = $this->cmd_compileMailGroup(intval($mailgroup_uid));
					$query_info=$result["queryInfo"];
//					debug($query_info);
					
/*					$query_info = array();
					$query_info["tt_address"] = "tt_address.pid=".$this->id;
					$query_info["fe_users"] = "fe_users.pid=".$this->id;
					*/
					$distributionTime = intval(t3lib_div::_GP("send_mail_datetime"));
					$distributionTime = $distributionTime<time() ? time() : $distributionTime;
					
					$updateFields = array(
						'scheduled' => $distributionTime,
						'query_info' => serialize($query_info)
					);
					
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_dmail', 'uid='.intval($this->sys_dmail_uid), $updateFields);

					$sentFlag=true;
					$theOutput.= $GLOBALS["SOBE"]->doc->section("Mail scheduled for distribution",fw('The mail was scheduled for distribution at '.t3lib_BEfunc::datetime($distributionTime).'.<BR><BR>'.$this->back));
					$this->noView=1;
				}
				// Here the correct address-records are selected and ...
			}
		}

			// Setting flags:
		if ($sentFlag && $this->CMD=="send_mail_final")	{
				// Update the record:
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('sys_dmail', 'uid='.intval($this->sys_dmail_uid), array('issent' => 1));

				// Read again:
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_dmail', 'pid='.intval($this->id).' AND uid='.intval($this->sys_dmail_uid).t3lib_BEfunc::deleteClause('sys_dmail'));
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}
		return $theOutput;
	}
	function showWithPercent($pieces,$total)	{
		$total = intval($total);
		$str = $pieces;
		if ($total)	{
			$str.= " / ".number_format(($pieces/$total*100),2)."%";
		}
		return $str;
	}
	function formatTable($tableLines,$cellParams,$header,$cellcmd=array(),$tableParams='border=0 cellpadding=2 cellspacing=1')	{
		reset($tableLines);
		$cols = count(current($tableLines));
		
		reset($tableLines);
		$lines=array();
		$first=$header?1:0;
		while(list(,$r)=each($tableLines))	{
			$rowA=array();
			for($k=0;$k<$cols;$k++)	{
				$v=$r[$k];
				$v = $v ? ($cellcmd[$k]?$v:htmlspecialchars($v)) : "&nbsp;";
				if ($first) $v='<B>'.$v.'</B>';
				$rowA[]='<td'.($cellParams[$k]?" ".$cellParams[$k]:"").'>'.$v.'</td>';
			}
			$lines[]='<tr class="'.($first?'bgColor5':'bgColor4').'">'.implode("",$rowA).'</tr>';
			$first=0;
		}
		$table = '<table '.$tableParams.'>'.implode("",$lines).'</table>';
		return $table;
	}
	function cmd_stats($row)	{
		if (t3lib_div::_GP("recalcCache"))	{
			$this->makeStatTempTableContent($row);
		}
		$thisurl = "index.php?id=".$this->id."&sys_dmail_uid=".$row["uid"]."&CMD=".$this->CMD."&recalcCache=1";


		$output.=t3lib_iconWorks::getIconImage("sys_dmail",$row,$GLOBALS["BACK_PATH"],'align="top"').$row["subject"]."<BR>";
		
			// *****************************
			// Mail responses, general:
			// *****************************			
		$queryArray = array('response_type,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']), 'response_type');
		$table = $this->getQueryRows($queryArray, 'response_type');
			
			// Plaintext/HTML
		$queryArray = array('html_sent,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=0', 'html_sent');
		$table2 = $this->getQueryRows($queryArray, 'html_sent');

			// Unique responses, html
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=1', 'rid,rtbl', 'counter');
		$unique_html_responses = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			
			// Unique responses, Plain
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=2', 'rid,rtbl', 'counter');
		$unique_plain_responses = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			
			// Unique responses, pings
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=-1', 'rid,rtbl', 'counter');
		$unique_ping_responses = $GLOBALS['TYPO3_DB']->sql_num_rows($res);


		$tblLines=array();
		$tblLines[]=array("","Total:","HTML:","Plaintext:");

		$sent_total = intval($table["0"]["counter"]);
		$sent_html = intval($table2["3"]["counter"]+$table2["1"]["counter"]);
		$sent_plain = intval($table2["2"]["counter"]);
		$tblLines[]=array("Mails sent:",$sent_total,$sent_html,$sent_plain);
		$tblLines[]=array("Mails returned:",$this->showWithPercent($table["-127"]["counter"],$sent_total));
		$tblLines[]=array("HTML mails viewed:","",$this->showWithPercent($unique_ping_responses,$sent_html));
		$tblLines[]=array("Unique responses (link click):",$this->showWithPercent($unique_html_responses+$unique_plain_responses,$sent_total),$this->showWithPercent($unique_html_responses,$sent_html),$this->showWithPercent($unique_plain_responses,$sent_plain));

		$output.='<BR><strong>General information:</strong>';
		$output.=$this->formatTable($tblLines,array("nowrap","nowrap align=right","nowrap align=right","nowrap align=right"),1);


			// ******************
			// Links:
			// ******************

			// Most popular links, html:
		$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=1', 'url_id', 'counter');
		$htmlUrlsTable=$this->getQueryRows($queryArray,"url_id");

			// Most popular links, plain:
		$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=2', 'url_id', 'counter');
		$plainUrlsTable=$this->getQueryRows($queryArray,"url_id");
		
		// Find urls:
		$temp_unpackedMail = unserialize($row["mailContent"]);
		$urlArr=array();
		$urlMd5Map=array();
		if (is_array($temp_unpackedMail["html"]["hrefs"]))	{
			reset($temp_unpackedMail["html"]["hrefs"]);
			while(list($k,$v)=each($temp_unpackedMail["html"]["hrefs"]))	{
				$urlArr[$k]=$v["absRef"];
				$urlMd5Map[md5($v["absRef"])]=$k;
			}
		}
		if (is_array($temp_unpackedMail["plain"]["link_ids"]))	{
			reset($temp_unpackedMail["plain"]["link_ids"]);
			while(list($k,$v)=each($temp_unpackedMail["plain"]["link_ids"]))	{
				$urlArr[intval(-$k)]=$v;
			}
		}
		// Traverse plain urls:
		reset($plainUrlsTable);
		$plainUrlsTable_mapped=array();
		while(list($id,$c)=each($plainUrlsTable))	{
			$url = $urlArr[intval($id)];
			if (isset($urlMd5Map[md5($url)]))	{
				$plainUrlsTable_mapped[$urlMd5Map[md5($url)]]=$c;
			} else {
				$plainUrlsTable_mapped[$id]=$c;
			}
		}

		// Traverse html urls:
		$urlCounter["html"]=array();
		reset($htmlUrlsTable);
		while(list($id,$c)=each($htmlUrlsTable))	{	
			$urlCounter["html"][$id]=$c["counter"];
		}
		$urlCounter["total"]=$urlCounter["html"];

		// Traverse plain urls:
		$urlCounter["plain"]=array();
		reset($plainUrlsTable_mapped);
		while(list($id,$c)=each($plainUrlsTable_mapped))	{
			$urlCounter["plain"][$id]=$c["counter"];
			$urlCounter["total"][$id]+=$c["counter"];
		}

		$tblLines=array();
		$tblLines[]=array("","Total:","HTML:","Plaintext:","");
		$tblLines[]=array("Total responses (link click):",$this->showWithPercent($table["1"]["counter"]+$table["2"]["counter"],$sent_total),$this->showWithPercent($table["1"]["counter"],$sent_html),$this->showWithPercent($table["2"]["counter"],$sent_plain));
		$tblLines[]=array("Unique responses (link click):",$this->showWithPercent($unique_html_responses+$unique_plain_responses,$sent_total),$this->showWithPercent($unique_html_responses,$sent_html),$this->showWithPercent($unique_plain_responses,$sent_plain));
		$tblLines[]=array("Links clicked per respondent:",
			($unique_html_responses+$unique_plain_responses ? number_format(($table["1"]["counter"]+$table["2"]["counter"])/($unique_html_responses+$unique_plain_responses),2):""),
			($unique_html_responses ? number_format(($table["1"]["counter"])/($unique_html_responses),2):""),
			($unique_plain_responses ? number_format(($table["2"]["counter"])/($unique_plain_responses),2):"")
		);
		arsort($urlCounter["total"]);
		arsort($urlCounter["html"]);
		arsort($urlCounter["plain"]);
/*		$tblLines[]=array("Most popular link:","#".key($urlCounter["total"]),"#".key($urlCounter["html"]),"#".key($urlCounter["plain"]));
		end($urlCounter["total"]);
		end($urlCounter["html"]);
		end($urlCounter["plain"]);
		$tblLines[]=array("Least popular link:","#".key($urlCounter["total"]),"#".key($urlCounter["html"]),"#".key($urlCounter["plain"]));
*/
		reset($urlCounter["total"]);
		while(list($id,$c)=each($urlCounter["total"]))	{
			$uParts = parse_url($urlArr[intval($id)]);
			$urlstr = $uParts["path"].($uParts["query"]?"?".$uParts["query"]:"");
			if (strlen($urlstr)<10)	{
				$urlstr=$uParts["host"].$urlstr;
			}
			$urlstr=substr($urlstr,0,40);
			$img='<img src="'.$GLOBALS["BACK_PATH"].'gfx/zoom2.gif" width="12" height="12" border="0" title="'.htmlspecialchars($urlArr[$id]).'">';
			$tblLines[]=array("Link #".$id." (".$urlstr.")",$c,$urlCounter["html"][$id],$urlCounter["plain"][$id],$img);
		}
		$output.='<BR><strong>Response:</strong>';
		$output.=$this->formatTable($tblLines,array("nowrap","nowrap align=right","nowrap align=right","nowrap align=right"),1,array(0,0,0,0,1));

		

			// ******************
			// Returned mails:
			// ******************
		$queryArray = array('count(*) as counter,return_code', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=-127', 'return_code');
		$table_ret = $this->getQueryRows($queryArray,"return_code");
		$tblLines=array();
		$tblLines[]=array("","Count:");
		$tblLines[]=array("Total Mails returned:",$table["-127"]["counter"]);
		$tblLines[]=array("Recipient unknown (550):",$this->showWithPercent($table_ret["550"]["counter"]+$table_ret["553"]["counter"],$table["-127"]["counter"]));
		$tblLines[]=array("Mailbox full:",$this->showWithPercent($table_ret["551"]["counter"],$table["-127"]["counter"]));
		$tblLines[]=array("Bad host:",$this->showWithPercent($table_ret["2"]["counter"],$table["-127"]["counter"]));
		$tblLines[]=array("Error in Header:",$this->showWithPercent($table_ret["554"]["counter"],$table["-127"]["counter"]));
		$tblLines[]=array("Unknown:",$this->showWithPercent($table_ret["-1"]["counter"],$table["-127"]["counter"]));

		$output.='<BR><strong>Returned mails:</strong>';
		$output.=$this->formatTable($tblLines,array("nowrap","nowrap align=right"),1);
		$output.='<a href="'.$thisurl.'&returnList=1">List returned recipients</a><BR>';
		$output.='<a href="'.$thisurl.'&returnDisable=1">Disable returned recipients</a><BR>';
		$output.='<a href="'.$thisurl.'&returnCSV=1">CSV of returned recipients</a><BR>';

		if (t3lib_div::_GP("returnList")|t3lib_div::_GP("returnDisable")|t3lib_div::_GP("returnCSV"))		{
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('rid,rtbl', 'sys_dmail_maillog', 'mid='.intval($row['uid']).' AND response_type=-127');
			$idLists = array();
			while($rrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				switch($rrow["rtbl"])	{
					case "t":
						$idLists["tt_address"][]=$rrow["rid"];
					break;
					case "f":
						$idLists["fe_users"][]=$rrow["rid"];
					break;
					default:
						$idLists[$rrow["rtbl"]][]=$rrow["rid"];
					break;
				}
			}

			if (t3lib_div::_GP("returnList"))	{
				if (is_array($idLists["tt_address"]))	{$output.="<BR>ADDRESSES".$this->getRecordList($this->fetchRecordsListValues($idLists["tt_address"],"tt_address"),"tt_address");}
				if (is_array($idLists["fe_users"]))		{$output.="<BR>WEBSITE USERS".$this->getRecordList($this->fetchRecordsListValues($idLists["fe_users"],"fe_users"),"fe_users");}
//			if (is_array($idLists["PLAINLIST"]))	{$out.=$this->getRecordList($idLists["PLAINLIST"],"default",1);}
//			if (is_array($idLists[$this->userTable]))	{$out.=$this->getRecordList($this->fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable);}
			}
			if (t3lib_div::_GP("returnDisable"))	{
				if (is_array($idLists["tt_address"]))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists["tt_address"],"tt_address"),"tt_address");
					$output.="<BR>".$c." ADDRESSES DISABLED";
				}
				if (is_array($idLists["fe_users"]))	{
					$c=$this->disableRecipients($this->fetchRecordsListValues($idLists["fe_users"],"fe_users"),"fe_users");
					$output.="<BR>".$c." FE_USERS DISABLED";
				}
			}
			if (t3lib_div::_GP("returnCSV"))	{
				$emails=array();
				if (is_array($idLists["tt_address"]))	{
					$arr=$this->fetchRecordsListValues($idLists["tt_address"],"tt_address");
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v["email"];
					}
				}
				if (is_array($idLists["fe_users"]))	{
					$arr=$this->fetchRecordsListValues($idLists["fe_users"],"fe_users");
					reset($arr);
					while(list(,$v)=each($arr))	{
						$emails[]=$v["email"];
					}
				}
				$output.="<BR>Email recipients which are returned mails:<BR>";
				$output.='<textarea'.$GLOBALS["TBE_TEMPLATE"]->formWidthText().' rows="6" name="nothing">'.t3lib_div::formatForTextarea(implode($emails,chr(10))).'</textarea>';
			}
		}

		$this->noView=1;
		$theOutput.= $GLOBALS["SOBE"]->doc->section("Statistics for direct mail:",$output);
		
		$link = '<a href="'.$thisurl.'">Re-calculate cached statistics data</a>';
		$theOutput.= $GLOBALS["SOBE"]->doc->divider(10);
		$theOutput.= $GLOBALS["SOBE"]->doc->section("Re-calculate Cached Data:",$link);
		return $theOutput;
	}
	function disableRecipients($arr,$table)	{
		if ($GLOBALS["TCA"][$table])	{
			$fields_values=array();
			$enField = $GLOBALS["TCA"][$table]["ctrl"]["enablecolumns"]["disabled"];
			if ($enField)	{
				$fields_values[$enField]=1;
				$count=count($arr);
				$uidList = array_keys($arr);
				if (count($uidList))	{
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, 'uid IN ('.implode(',',$GLOBALS['TYPO3_DB']->cleanIntArray($uidList)).')', $fields_values);
				}
			}
		}
		return intval($count);
	}
	function makeStatTempTableContent($mrow)	{
			// Remove old:
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_sys_dmail_stat', 'mid='.intval($mrow['uid']));

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('rid,rtbl,tstamp,response_type,url_id,html_sent,size', 'sys_dmail_maillog', 'mid='.intval($mrow['uid']), '', 'rtbl,rid,tstamp');

		$currentRec = "";
		$recRec = "";
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			$thisRecPointer=$row["rtbl"].$row["rid"];
			if ($thisRecPointer!=$currentRec)	{
				$this->storeRecRec($recRec);
//				debug($thisRecPointer);
				$recRec=array(
					"mid"=>intval($mrow["uid"]),
					"rid"=>intval($row["rid"]),
					"rtbl"=>$row["rtbl"],
					"pings"=>array(),
					"plain_links"=>array(),
					"html_links"=>array(),
					"response"=>array(),
					"links"=>array()
				);
				$currentRec=$thisRecPointer;
			}
			switch ($row["response_type"])	{
				case "-1":
					$recRec["pings"][]=$row["tstamp"];
					$recRec["response"][]=$row["tstamp"];
				break;
				case "0":
					$recRec["recieved_html"]=$row["html_sent"]&1;
					$recRec["recieved_plain"]=$row["html_sent"]&2;
					$recRec["size"]=$row["size"];
					$recRec["tstamp"]=$row["tstamp"];
				break;
				case "1":
				case "2":
					$recRec[($row["response_type"]==1?"html_links":"plain_links")][] = $row["tstamp"];
					$recRec["links"][]=$row["tstamp"];
					if (!$recRec["firstlink"])	{
						$recRec["firstlink"]=$row["url_id"];
						$recRec["firstlink_time"]=intval(@max($recRec["pings"]));
						$recRec["firstlink_time"]= $recRec["firstlink_time"] ? $row["tstamp"]-$recRec["firstlink_time"] : 0;
					} elseif (!$recRec["secondlink"])	{
						$recRec["secondlink"]=$row["url_id"];
						$recRec["secondlink_time"]=intval(@max($recRec["pings"]));
						$recRec["secondlink_time"]= $recRec["secondlink_time"] ? $row["tstamp"]-$recRec["secondlink_time"] : 0;
					} elseif (!$recRec["thirdlink"])	{
						$recRec["thirdlink"]=$row["url_id"];
						$recRec["thirdlink_time"]=intval(@max($recRec["pings"]));
						$recRec["thirdlink_time"]= $recRec["thirdlink_time"] ? $row["tstamp"]-$recRec["thirdlink_time"] : 0;
					}
					$recRec["response"][]=$row["tstamp"];
				break;
				case "-127":
					$recRec["returned"]=1;
				break;
			}
		}
		$this->storeRecRec($recRec);
	}
	function storeRecRec($recRec)	{
		if (is_array($recRec))	{
			$recRec["pings_first"] = intval(@min($recRec["pings"]));
			$recRec["pings_last"] = intval(@max($recRec["pings"]));
			$recRec["pings"] = count($recRec["pings"]);

			$recRec["html_links_first"] = intval(@min($recRec["html_links"]));
			$recRec["html_links_last"] = intval(@max($recRec["html_links"]));
			$recRec["html_links"] = count($recRec["html_links"]);

			$recRec["plain_links_first"] = intval(@min($recRec["plain_links"]));
			$recRec["plain_links_last"] = intval(@max($recRec["plain_links"]));
			$recRec["plain_links"] = count($recRec["plain_links"]);
		
			$recRec["links_first"] = intval(@min($recRec["links"]));
			$recRec["links_last"] = intval(@max($recRec["links"]));
			$recRec["links"] = count($recRec["links"]);

			$recRec["response_first"] = t3lib_div::intInRange(intval(@min($recRec["response"]))-$recRec["tstamp"],0);
			$recRec["response_last"] = t3lib_div::intInRange(intval(@max($recRec["response"]))-$recRec["tstamp"],0);
			$recRec["response"] = count($recRec["response"]);
		
			$recRec["time_firstping"] = t3lib_div::intInRange($recRec["pings_first"]-$recRec["tstamp"],0);
			$recRec["time_lastping"] = t3lib_div::intInRange($recRec["pings_last"]-$recRec["tstamp"],0);

			$recRec["time_first_link"] = t3lib_div::intInRange($recRec["links_first"]-$recRec["tstamp"],0);
			$recRec["time_last_link"] = t3lib_div::intInRange($recRec["links_last"]-$recRec["tstamp"],0);

			$GLOBALS['TYPO3_DB']->exec_INSERTquery("cache_sys_dmail_stat", $recRec);
		}
	}
	function showQueryRes($query)	{
		$res = $GLOBALS['TYPO3_DB']->sql(TYPO3_db,$query);
		$lines = array();
		$first = 1;
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			if ($first)	{
				$lines[]="<tr bgcolor=#cccccc><td><b>".implode("</b></td><td><b>",array_keys($row))."</b></td></tr>";
				$first=0;
			}
			$lines[]="<tr bgcolor=#eeeeee><td>".implode("</td><td>",$row)."</td></tr>";
		}
		$str = '<table border=1 cellpadding=0 cellspacing=0>'.implode("",$lines).'</table>';
		return $str;
	}
	function getQueryRows($queryArray,$key_field)	{
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				$queryArray[0],
				$queryArray[1],
				$queryArray[2],
				$queryArray[3],
				$queryArray[4],
				$queryArray[5]
			);
		$lines = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
			if ($key_field)	{
				$lines[$row[$key_field]] = $row;
			} else {
				$lines[] = $row;
			}
		}
		return $lines;
	}

	function directMail_defaultView($row)	{
		global $LANG;
			// Render record:
		$out="";
		$Eparams="&edit[sys_dmail][".$row["uid"]."]=edit";
		$out.='<tr><td colspan=3 class="bgColor5" valign=top>'.fw($this->fName("subject")." <b>".t3lib_div::fixed_lgd($row["subject"],30)."&nbsp;&nbsp;</b>").'</td></tr>';
		
		$nameArr = explode(",","subject,from_name,from_email,replyto_name,replyto_email,organisation,attachment,priority,sendOptions,type,page,plainParams,HTMLParams,issent,renderedsize");
		while(list(,$name)=each($nameArr))	{
			$out.='<tr><td class="bgColor4">'.fw($this->fName($name)).'</td><td class="bgColor4">'.fw(htmlspecialchars(t3lib_BEfunc::getProcessedValue("sys_dmail",$name,$row[$name]))).'</td></tr>';
		}
		$out='<table border=0 cellpadding=1 cellspacing=1 width=460>'.$out.'</table>';
		if (!$row["issent"])	{
			if ($GLOBALS["BE_USER"]->check("tables_modify","sys_dmail"))	{
				$out.='<BR><A HREF="#" onClick="'.t3lib_BEfunc::editOnClick($Eparams,$GLOBALS["BACK_PATH"],"").'"><img src="'.$GLOBALS["BACK_PATH"].'gfx/edit2.gif" width=11 height=12 hspace=2 border=0 title="Edit" align="top">'.fw("<B>".$LANG->getLL("dmail_edit")."</B>").'</a>';
			} else {
				$out.='<BR><img src="'.$GLOBALS["BACK_PATH"].'gfx/edit2.gif" width=11 height=12 hspace=2 border=0 title="'.$LANG->getLL("dmail_edit").'" align="top">'.fw("(".$LANG->getLL("dmail_noEdit_noPerms").")");
			}
		} else {
			$out.='<BR><img src="'.$GLOBALS["BACK_PATH"].'gfx/edit2.gif" width=11 height=12 hspace=2 border=0 title="'.$LANG->getLL("dmail_edit").'" align="top">'.fw("(".$LANG->getLL("dmail_noEdit_isSent").")");
		}

		if ($row["type"]==0 && $row["page"])	{
			$pageRow = t3lib_BEfunc::getRecord ("pages",$row["page"]);
			if ($pageRow)	{
				$out.='<BR><BR>'.$LANG->getLL("dmail_basedOn").'<BR>';
				$out.='<nobr><a href="index.php?id='.$this->id.'&CMD=displayPageInfo&pages_uid='.$pageRow["uid"].'&SET[dmail_mode]=news"><img src="'.$GLOBALS["BACK_PATH"].t3lib_iconWorks::getIcon("pages",$pageRow).'" width=18 height=16 border=0 title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($pageRow["uid"],$this->perms_clause,20)).'" align=top>'.htmlspecialchars($pageRow["title"]).'</a></nobr><BR>';
			}
		}

		$theOutput.= $GLOBALS["SOBE"]->doc->section($LANG->getLL("dmail_view"),$out,0,1);
		
		// Status:
		
		$theOutput.= $GLOBALS["SOBE"]->doc->spacer(15);
		$menuItems = array();
			$menuItems[0]="[MENU]";
			if (!$row["issent"])	{
				$menuItems["prefetch"]="Fetch and compile maildata (read url)";
			}
			if ($row["from_email"] && $row["renderedsize"])	{
				$menuItems["testmail"]="Send a testmail";
				if (!$row["issent"])	{
					$menuItems["finalmail"]="Mass-send mail";
				}
			}
			if ($row["issent"])	{
				$menuItems["stats"]="See statistics of this mail";
			}

		$menu = t3lib_BEfunc::getFuncMenu($this->id,"CMD","",$menuItems,"","&sys_dmail_uid=".$row["uid"]);
		$theOutput.= $GLOBALS["SOBE"]->doc->section("Options:",$menu);
		
		if (!$row["renderedsize"])	{
			$theOutput.= $GLOBALS["SOBE"]->doc->divider(15);
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Note:","Use the menu to fetch content for the email");
		} elseif(!$row["from_email"])	{
			$theOutput.= $GLOBALS["SOBE"]->doc->divider(15);
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Note:","You <i>must</i> enter a 'Sender email' address for the mail!");
		} else {
			$theOutput.= $GLOBALS["SOBE"]->doc->divider(15);
			$theOutput.= $GLOBALS["SOBE"]->doc->section("Note:","Use the menu to send a test-mail to your own email address or finally send the mail to all recipients.");
		}
		$theOutput.= $GLOBALS["SOBE"]->doc->section('','<BR><BR><input type="submit" value="< BACK">');
		return $theOutput;
	}
	function linkDMail_record($str,$uid)	{
		return '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&SET[dmail_mode]=direct">'.$str.'</a>';
	}
	function linkRecip_record($str,$uid)	{
		return '<a href="index.php?id='.$this->id.'&CMD=displayMailGroup&group_uid='.$uid.'&SET[dmail_mode]=recip">'.$str.'</a>';
	}
	function fName($name)	{
		return $GLOBALS["LANG"]->sL(t3lib_BEfunc::getItemLabel("sys_dmail",$name));
	}
	function JSbottom($formname="forms[0]")	{
		if ($this->extJSCODE)	{
			$out.='
			<script language="javascript" type="text/javascript" src="'.$GLOBALS["BACK_PATH"].'t3lib/jsfunc.evalfield.js"></script>
			<script language="javascript" type="text/javascript">
				var evalFunc = new evalFunc;
				function typo3FormFieldSet(theField, evallist, is_in, checkbox, checkboxValue)	{
					var theFObj = new evalFunc_dummy (evallist,is_in, checkbox, checkboxValue);
					var theValue = document.'.$formname.'[theField].value;
					if (checkbox && theValue==checkboxValue)	{
						document.'.$formname.'[theField+"_hr"].value="";
						if (document.'.$formname.'[theField+"_cb"])	document.'.$formname.'[theField+"_cb"].checked = "";
					} else {
						document.'.$formname.'[theField+"_hr"].value = evalFunc.outputObjValue(theFObj, theValue);
						if (document.'.$formname.'[theField+"_cb"])	document.'.$formname.'[theField+"_cb"].checked = "on";
					}
				}
				function typo3FormFieldGet(theField, evallist, is_in, checkbox, checkboxValue, checkbox_off)	{
					var theFObj = new evalFunc_dummy (evallist,is_in, checkbox, checkboxValue);
					if (checkbox_off)	{
						document.'.$formname.'[theField].value=checkboxValue;
					}else{
						document.'.$formname.'[theField].value = evalFunc.evalObjValue(theFObj, document.'.$formname.'[theField+"_hr"].value);
					}
					typo3FormFieldSet(theField, evallist, is_in, checkbox, checkboxValue);
				}
			</script>
			<script language="javascript" type="text/javascript">'.$this->extJSCODE.'</script>';
			return $out;	
		}
	}
}



if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/direct_mail/mod/class.mod_web_dmail.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/direct_mail/mod/class.mod_web_dmail.php"]);
}

?>
