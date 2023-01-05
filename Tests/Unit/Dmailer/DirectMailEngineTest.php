<?php

namespace DirectMailTeam\DirectMail\Tests\Unit\Mailer;

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

/**
 * Testcase for class "DirectMailTeam\DirectMail\Dmailer"
 *
 * @author Bernhard Kraft <kraft@webconsulting.at>
 */
class DirectMailEngineTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @test
     * @dataProvider extractHyperLinksDataProvider
     */
    public function test_extractHyperLinks($content, $path, $expected)
    {
        // This test also tests "tag_regex", "get_tag_attributes" and "absRef"
        // TODO: Write units tests also for those methods and provide mocked methods here.

        // The method "extractMediaLinks" will also get called but its result does not get tested.
        // TODO: Expand this test to make sure media with the "use_jumpurl" attribute will
        // also get added to the extracted hyperlinks

        // Create an instance of "dmailer" with only the "extractMediaLinks" being mocked.
        $dmailer = $this->getMock('dmailer', ['extractMediaLinks']);
        $dmailer->expects(self::once())->method('extractMediaLinks');
        $dmailer->setPartHtmlConfig('content', $content);
        $dmailer->setPartHtmlConfig('path', $path);
        $dmailer->setPartHtmlConfig('media', []);
        $dmailer->extractHyperLinks();

        self::assertEquals($expected, $dmailer->getPartHtmlConfig('hrefs'));
    }

    /**
     * Data provider for test_extractHyperLinks
     *
     * @return array
     */
    public function extractHyperLinksDataProvider()
    {
        return [
            'no hyperlinks found' => ['This is a simple test', '', null],
            'no hyperlinks in anchor' => ['This is a <a name="anchor">simple</a> test', '', null],
            'absolute url' => ['
				This is a <a name="link" href="http://google.com">simple</a> test',
                'http://www.server.com/',
                [
                    [
                        'ref' => 'http://google.com',
                        'quotes' => '"',
                        'subst_str' => '"http://google.com"',
                        'absRef' => 'http://google.com',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'absolute url (fails currently, #54459)' => ['
				This is a <a title="Browse to http://google.com for more information" href="http://google.com">simple</a> test',
                'http://www.server.com/',
                [
                    [
                        'ref' => 'http://google.com',
                        'quotes' => '"',
                        'subst_str' => '"http://google.com"',
                        'absRef' => 'http://google.com',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'relative link #1' => ['
				This is a <a name="link" href="fileadmin/simple.pdf">simple</a> test',
                'http://www.server.com/',
                [
                    [
                        'ref' => 'fileadmin/simple.pdf',
                        'quotes' => '"',
                        'subst_str' => '"fileadmin/simple.pdf"',
                        'absRef' => 'http://www.server.com/fileadmin/simple.pdf',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'relative link #2' => ['
				This is a <a name="link" href="fileadmin/simple.pdf">simple</a> test',
                'http://www.server.com',
                [
                    [
                        'ref' => 'fileadmin/simple.pdf',
                        'quotes' => '"',
                        'subst_str' => '"fileadmin/simple.pdf"',
                        'absRef' => 'http://www.server.com/fileadmin/simple.pdf',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'relative link #3' => ['
				This is a <a name="link" href="fileadmin/simple.pdf">simple</a> test',
                'http://www.server.com/subdirectory/',
                [
                    [
                        'ref' => 'fileadmin/simple.pdf',
                        'quotes' => '"',
                        'subst_str' => '"fileadmin/simple.pdf"',
                        'absRef' => 'http://www.server.com/subdirectory/fileadmin/simple.pdf',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'relative link #4' => ['
				This is a <a name="link" href="fileadmin/simple.pdf">simple</a> test',
                'http://www.server.com/subdirectory',
                [
                    [
                        'ref' => 'fileadmin/simple.pdf',
                        'quotes' => '"',
                        'subst_str' => '"fileadmin/simple.pdf"',
                        'absRef' => 'http://www.server.com/fileadmin/simple.pdf',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'absolute link #1' => ['
				This is a <a name="link" href="/fileadmin/simple.pdf">simple</a> test',
                'http://www.server.com/subdirectory',
                [
                    [
                        'ref' => '/fileadmin/simple.pdf',
                        'quotes' => '"',
                        'subst_str' => '"/fileadmin/simple.pdf"',
                        'absRef' => 'http://www.server.com/fileadmin/simple.pdf',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'absolute link #2' => ['
				This is a <a name="link" href="/fileadmin/simple.pdf">simple</a> test',
                'http://www.server.com/subdirectory/',
                [
                    [
                        'ref' => '/fileadmin/simple.pdf',
                        'quotes' => '"',
                        'subst_str' => '"/fileadmin/simple.pdf"',
                        'absRef' => 'http://www.server.com/fileadmin/simple.pdf',
                        'tag' => 'a',
                        'no_jumpurl' => 0,
                    ],
                ],
            ],
            'absolute link #3 (no_jumpurl)' => ['
				This is a <a name="link" href="image.png" no_jumpurl="1">simple</a> test',
                'http://www.server.com/subdirectory',
                [
                    [
                        'ref' => 'image.png',
                        'quotes' => '"',
                        'subst_str' => '"image.png"',
                        'absRef' => 'http://www.server.com/image.png',
                        'tag' => 'a',
                        'no_jumpurl' => 1,
                    ],
                ],
            ],
            'form action #1' => ['
				Hello.<br />
				Here you can send us your comment<br />
				<form name="formname" action="index.php?id=123" method="POST" no_jumpurl=1>
					<input type="text" name="comment" value="">
				</form>
				Thanks!',
                'http://www.server.com/subdirectory/',
                [
                    [
                        'ref' => 'index.php?id=123',
                        'quotes' => '"',
                        'subst_str' => '"index.php?id=123"',
                        'absRef' => 'http://www.server.com/subdirectory/index.php?id=123',
                        'tag' => 'form',
                        'no_jumpurl' => 1,
                    ],
                ],
            ],
        ];
    }
}
