<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2003-2005 Robert Lemke (robert@typo3.org)
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * @author		Robert Lemke <robert@typo3.org>
 * @author 		Ivan Dharma Kartolo <ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 *
 * @version 	$Id: class.dmailer.php 90 2007-01-18 23:01:31Z ivan $
 */

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   56: class tx_directmail_calendarlib
 *   64:     function includeLib($confTS)
 *  260:     function getInputButton ($id)
 *
 * TOTAL FUNCTIONS: 2
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */
require_once (PATH_t3lib.'class.t3lib_tsparser.php');

/**
 * Shows javascript Calendar
 * based on the 'rlmp_dateselectlib' extension by Robert Lemke <robert@typo3.org>
 * with some modification to fit into tx_directmail extension
 *
 */
class tx_directmail_calendarlib {

	/**
	 * Include the javascript library and other data for page rendering
	 *
	 * @param	array		$confTS: Configuration for creating the dynamic calendar (see manual). Overrides TypoScript configuration.
	 * @return	string		$JS: the javascript code.
	 */
	function includeLib($confTS)	{

			//date format
		$confStatic['calConf.'] = array(
			'dateTimeFormat' => '%H:%M %d-%m-%Y',
			'inputFieldDateTimeFormat' => '%H:%M %d-%m-%Y',
		);

			// Read ext_typoscript_setup.txt for this extension and parse it into an array
		$tconf = t3lib_div::getUrl(t3lib_extMgm::extPath('direct_mail').'res/scripts/calendar/typoscript_setup_calendar.txt');

  		$infoParser = new t3lib_TSparser;
        $infoParser->parse($tconf);
        $conf = $infoParser->setup['mod.']['web_modules.']['dmail.'];
		$conf = t3lib_div::array_merge_recursive_overrule($conf,array('calConf.' => $confTS));
		$conf = t3lib_div::array_merge_recursive_overrule($conf,$confStatic);
		$GLOBALS['calendar.'] = $conf;

			// Check if another language than 'default' was selected
		$LLkey = 'default';
		if ($GLOBALS['BE_USER']->user['lang'] && $GLOBALS['BE_USER']->user['lang'] != '') {
			$LLkey = $GLOBALS['BE_USER']->user['lang'] ;
		}

			// Load translations
			// This is done kind of manually here, because we use this class
			// without making an instance. That's why I don't use pi_loadLL etc.
			// because we don't know in which scope we're currently in.
			//	Do you know a better way? Tell me ... ;-)

		$basePath = t3lib_extMgm::extPath('direct_mail').'res/scripts/calendar/locallang.php';

		if (@is_file($basePath))	{
			include_once($basePath);
		}

		$stylesheet = $conf['calConf.']['stylesheet'];
		if (substr ($stylesheet,0,4) == 'EXT:')	{		// extension
			list($extKey,$local) = explode('/',substr($stylesheet,4),2);
			$stylesheet='';
			if (strcmp($extKey,'') && t3lib_extMgm::isLoaded($extKey) && strcmp($local,''))	{
				$stylesheet = t3lib_div::getIndpEnv ('TYPO3_SITE_URL').$GLOBALS['TYPO3_LOADED_EXT'][$extKey]['siteRelPath'].$local;
			}
		}

		if (!$GLOBALS['tx_directmail']['tx_directmail_includeonce']) {

				// Add the calendar JavaScripts
			$JS ='
			<!-- import the calendar script -->
				<script type="text/javascript" src="../res/scripts/calendar/calendar-typo3.js"></script>
				<link rel="stylesheet" type="text/css" media="all" href="'.$stylesheet.'" />

				<script type="text/javascript">
			/*<![CDATA[*/

					tx_directmail_calendar._SDN = new Array
					("'.$LOCAL_LANG [$LLkey]['shortweekdays_sunday'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortweekdays_monday'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortweekdays_tuesday'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortweekdays_wednesday'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortweekdays_thursday'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortweekdays_friday'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortweekdays_saturday'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortweekdays_sunday'].'")

					tx_directmail_calendar._DN = new Array
					("'.$LOCAL_LANG [$LLkey]['weekdays_sunday'].'",
					 "'.$LOCAL_LANG [$LLkey]['weekdays_monday'].'",
					 "'.$LOCAL_LANG [$LLkey]['weekdays_tuesday'].'",
					 "'.$LOCAL_LANG [$LLkey]['weekdays_wednesday'].'",
					 "'.$LOCAL_LANG [$LLkey]['weekdays_thursday'].'",
					 "'.$LOCAL_LANG [$LLkey]['weekdays_friday'].'",
					 "'.$LOCAL_LANG [$LLkey]['weekdays_saturday'].'",
					 "'.$LOCAL_LANG [$LLkey]['weekdays_sunday'].'")

					tx_directmail_calendar._MN = new Array
					("'.$LOCAL_LANG [$LLkey]['months_january'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_february'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_march'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_april'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_may'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_june'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_july'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_august'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_september'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_october'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_november'].'",
					 "'.$LOCAL_LANG [$LLkey]['months_december'].'")

					tx_directmail_calendar._SMN = new Array
					("'.$LOCAL_LANG [$LLkey]['shortmonths_january'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_february'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_march'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_april'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_may'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_june'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_july'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_august'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_september'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_october'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_november'].'",
					 "'.$LOCAL_LANG [$LLkey]['shortmonths_december'].'")

						// tooltips
					tx_directmail_calendar._TT = {};
					tx_directmail_calendar._TT["ABOUT"] = "'.$LOCAL_LANG [$LLkey]['tt_about'].'";
					tx_directmail_calendar._TT["ABOUT_TIME"] = "'.$LOCAL_LANG [$LLkey]['tt_about_time'].'";
					tx_directmail_calendar._TT["TOGGLE"] = "'.$LOCAL_LANG [$LLkey]['tt_toggle_first_day_of_week'].'";
					tx_directmail_calendar._TT["PREV_YEAR"] = "'.$LOCAL_LANG [$LLkey]['tt_previous_year'].'";
					tx_directmail_calendar._TT["PREV_MONTH"] = "'.$LOCAL_LANG [$LLkey]['tt_previous_month'].'";
					tx_directmail_calendar._TT["GO_TODAY"] = "'.$LOCAL_LANG [$LLkey]['tt_go_today'].'";
					tx_directmail_calendar._TT["NEXT_MONTH"] = "'.$LOCAL_LANG [$LLkey]['tt_next_month'].'";
					tx_directmail_calendar._TT["NEXT_YEAR"] = "'.$LOCAL_LANG [$LLkey]['tt_next_year'].'";
					tx_directmail_calendar._TT["SEL_DATE"] = "'.$LOCAL_LANG [$LLkey]['tt_select_date'].'";
					tx_directmail_calendar._TT["DRAG_TO_MOVE"] = "'.$LOCAL_LANG [$LLkey]['tt_drag_to_move'].'";
					tx_directmail_calendar._TT["PART_TODAY"] = " ('.$LOCAL_LANG [$LLkey]['tt_part_today'].')";
					tx_directmail_calendar._TT["MON_FIRST"] = "'.$LOCAL_LANG [$LLkey]['tt_display_monday_first'].'";
					tx_directmail_calendar._TT["SUN_FIRST"] = "'.$LOCAL_LANG [$LLkey]['tt_display_sunday_first'].'";
					tx_directmail_calendar._TT["DAY_FIRST"] = "'.$LOCAL_LANG [$LLkey]['tt_display_day_first'].'";

					tx_directmail_calendar._TT["CLOSE"] = "'.$LOCAL_LANG [$LLkey]['tt_close'].'";
					tx_directmail_calendar._TT["TODAY"] = "'.$LOCAL_LANG [$LLkey]['tt_today'].'";

						// date formats
					tx_directmail_calendar._TT["DEF_DATE_FORMAT"] = "'.($conf['calConf.']['dateTimeFormat'] ? $conf['calConf.']['dateTimeFormat'] : $LOCAL_LANG [$LLkey]['dateTimeFormat']).'";
					tx_directmail_calendar._TT["TT_DATE_FORMAT"] = "'.($conf['calConf.']['toolTipDateTimeFormat']?$conf['calConf.']['toolTipDateTimeFormat']:$LOCAL_LANG [$LLkey]['toolTipDateTimeFormat']).'";
					tx_directmail_calendar._TT["WEEKEND"] = "'.($conf['calConf.']['weekend'] ? $conf['calConf.']['weekend'] : '6,0').'";

					tx_directmail_calendar._TT["WK"] = "'.$LOCAL_LANG [$LLkey]['week'].'";

						// This function gets called when the end-user clicks on some date.
					function tx_directmail_selected(cal, date) { //
						cal.sel.value = date; // just update the date in the input field.
					  if (cal.dateClicked && cal.sel.id == "calTest" )
					    // if we add this call we close the calendar on single-click.
					    // just to exemplify both cases, we are using this only for the 1st
					    // and the 3rd field, while 2nd and 4th will still require double-click.
					    cal.hide();
					}

						// And this gets called when the end-user clicks on the _selected_ date,
						// or clicks on the "Close" button.  It just hides the calendar without
						// destroying it.
					function tx_directmail_closeHandler(cal) { //
						cal.hide();                        // hide the calendar
					}

						// This function shows the calendar under the element having the given id.
						// It takes care of catching "mousedown" signals on document and hiding the
						// calendar if the click was outside.
					function tx_directmail_showCalendar (id, format, showsTime) { //
						var el = document.getElementById(id);

						if (calendar != null) {				// we already have some calendar created
							calendar.hide();				// so we hide it first.
						} else {
								// first-time call, create the calendar.
							var cal = new tx_directmail_calendar('. ($conf['calConf.']['weekStartsMonday'] ? $conf['calConf.']['weekStartsMonday'] : 'false') .', null, tx_directmail_selected, tx_directmail_closeHandler);
							cal.weekNumbers = '.($conf['calConf.']['displayWeekNumbers']?$conf['calConf.']['displayWeekNumbers']:'true').';
							if (typeof showsTime == "string") {
						      cal.showsTime = true;
						      cal.time24 = (showsTime == "24");
						    }

							calendar = cal;                  // remember it in the global var
							cal.setRange('.($conf['calConf.']['allowedYearMin']?$conf['calConf.']['allowedYearMin']:'1900').', '.($conf['calConf.']['allowedYearMax']?$conf['calConf.']['allowedYearMax']:'2070').');        // min/max year allowed.
							cal.create();
						}
						calendar.setDateFormat(format);    // set the specified date format
						calendar.parseDate(el.value);      // try to parse the text in field
						calendar.sel = el;                 // inform it what input field we use
					  '.
					($conf['calConf.']['showMethod'] == 'absolute' ?
						('calendar.showAt ('.$conf['calConf.']['showPositionAbsolute'].');') :
						('calendar.showAtElement(el);')
					).'
					  return false;
					}

			/*]]>*/
				</script>
			';

			$GLOBALS['tx_directmail']['tx_directmail_includeonce'] = TRUE;
			return $JS;
		}
	}


	/**
	 * Returns an input button which contains an onClick handler for opening the calendar
	 *
	 * @param	string		$id: HTML id of your input button
	 * @return	string		$out: HTML code (input button) for the date selector
	 */
	function getInputButton ($id) {
		$conf = $GLOBALS['calendar.'];
		$out =  '<input type="reset" value=" '.$conf['calConf.']['inputFieldLabel'].' " onclick="return tx_directmail_showCalendar('."'".$id."'".', '."'". ($conf['calConf.']['inputFieldDateTimeFormat']?$conf['calConf.']['inputFieldDateTimeFormat']:'%H:%M %d-%m-%Y') ."', '24'".');">';
		return $out;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/calendar/class.tx_directmail_calendarlib.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/calendar/class.tx_directmail_calendarlib.php']);
}

?>