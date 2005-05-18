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
 * mailSelect extension class to t3lib_queryGenerator
 *
 * Used to generate queries for selecting users in the database
 *
 * @author	Kasper Skårhøj <kasper@typo3.com>
 *
 * @package TYPO3
 * @subpackage tx_directmail
 * @version $Id$
 */

class mailSelect extends t3lib_queryGenerator	{

	var $langExt = array(
		"comparison" => array(
		 // Type = userdef,	offset = 64
				"64_" =>	"modtager html",
				"65_" =>	"ikke modtager html",
		
				"68_" =>	"er medlem af en af grupperne",
				"69_" =>	"ikke er medlem af en af grupperne",
				"70_" =>	"er medlem af alle grupperne",
				"71_" =>	"ikke er medlem af alle grupperne",
				"72_" =>	"har modtaget en af disse mails",		
				"73_" =>	"ikke har modtaget en af disse mails",
				"74_" =>	"har modtaget alle disse mails",		
				"75_" =>	"ikke har modtaget alle disse mails",		
				"76_" =>	"er medlem af en af disse kategorier",
				"77_" =>	"ikke er medlem af en af disse kategorier",
				"78_" =>	"er medlem af alle disse kategorier",
				"79_" =>	"ikke er medlem af alle disse kategorier",
				"80_" =>	"har responderet på en af", 
				"81_" =>	"ikke har responderet på en af", 
				"82_" =>	"har responderet på alle", 
				"83_" =>	"ikke har responderet på alle" /*, 
				"84_" =>	" har responderet på [mailliste] via en af disse",
				"85_" =>	"ikke har responderet på [mailliste] via en af disse",
				"86_" =>	"har responderet på [mailliste] via alle disse",
				"87_" =>	"ikke har responderet på [mailliste] via alle disse" */
		)
	);
	
	var $comp_offsetsExt = array(
		"userdef" => 2
	);

	var $fieldsExt =array(
		"maillist" => array(
			"label" => "mail liste",
			"type" => "userdef"
		)
	);
	var $allowedTables=array("tt_address","fe_users");
	
	function initUserDef()	{
		$this->lang = t3lib_div::array_merge_recursive_overrule($this->lang, $this->langExt);
		$this->comp_offsets = t3lib_div::array_merge_recursive_overrule($this->comp_offsets, $this->comp_offsetsExt);
		$this->fields = t3lib_div::array_merge_recursive_overrule($this->fields, $this->fieldsExt);		
	}
	function userDef($name,$conf,$fName,$fType) {						
		$out.=$this->mkTypeSelect($name.'[type]',$fName);
		$out.=$this->mkMailSelect($name.'[comparison]',$conf["comparison"],$conf["negate"]?1:0,$conf["and_entries"]?1:0);
		$out.='<input type="checkbox" '.($conf["negate"]?"checked":"").' name="'.$this->name.'['.$key.'][negate]'.'" onClick="submit();">';
		if($conf["comparison"]!=64&&$conf["comparison"]!=65) {
			$out.='<input type="checkbox" '.($conf["and_entries"]?"checked":"").' name="'.$name.'[and_entries]'.'" onClick="submit();">';
		}
		$out.='<input type="text" value="'.$conf["inputValue"].'" name="'.$name.'[inputValue]'.'"'.$GLOBALS["TBE_TEMPLATE"]->formWidth(20).'>';
		return $out;
	}
	function mkMailSelect($name,$comparison,$neg,$andEnt) {
		$compOffSet = $comparison >> 5;
		$out='<select name="'.$name.'" onChange="submit();">';
		for($i=32*$compOffSet+$neg+$andEnt*2;$i<32*($compOffSet+1);$i+=4) {
			if($this->lang["comparison"][$i."_"]) {
				$out.='<option value="'.$i.'"'.(($i >> 2)==($comparison >> 2) ? ' selected':'').'>'.$this->lang["comparison"][$i."_"].'</option>';
			}
		}
		$out.='</select>';
		return $out;
	}
	function mkTableSelect($name,$cur)	{
		global $TCA;
		$out='<select name="'.$name.'" onChange="submit();">';
		$out.='<option value=""></option>';
		reset($TCA);
		while(list($tN)=each($TCA)) {
			if ($GLOBALS["BE_USER"]->check("tables_select",$tN) && in_array($tN,$this->allowedTables))	{
				$out.='<option value="'.$tN.'"'.($tN==$cur ? ' selected':'').'>'.$GLOBALS["LANG"]->sl($TCA[$tN]["ctrl"]["title"]).'</option>';	
			}
		}
		$out.='</select>';
		return $out;
	}
}


if (defined("TYPO3_MODE") && $TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/direct_mail/mod/class.mailselect.php"])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/direct_mail/mod/class.mailselect.php"]);
}

?>