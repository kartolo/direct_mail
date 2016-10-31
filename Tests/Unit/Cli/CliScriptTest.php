<?php

namespace DirectMailTeam\DirectMail\Tests\Unit\Cli;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2013 Ingo Renner <ingo@typo3.org>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Testcase for cli script.
 *
 * @author Bernhard Kraft <kraft@webconsulting.at>
 */
class CliScriptTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @test
     */
    public function test_canIncludeCliScript()
    {
        $path = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('direct_mail').'cli/cli_direct_mail.php';
        $_SERVER['argv'] = array('direct_mail', 'otherTask');
        define('TYPO3_cliMode', '1');
        require_once $path;
        // If the test reaches this point the file file could get included
        $this->assertTrue(true);
    }
}
