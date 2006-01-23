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
 * @author  Jan-Erik Revsbech <jer@moccompany.com>
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


// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require ('conf.php');
require ($BACK_PATH.'init.php');
require ($BACK_PATH.'template.php');
$LANG->includeLLFile('EXT:direct_mail/mod/locallang.php');
#include ('locallang.php');
require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once('class.mod_web_dmail.php');
//require_once (PATH_t3lib.'class.t3lib_page.php');



$BE_USER->modAccess($MCONF,1);    // This checks permissions and exits if the users has no permission for entry.
// DEFAULT initialization of a module [END]




// Make instance:
$SOBE = t3lib_div::makeInstance('mod_web_dmail');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();

?>
