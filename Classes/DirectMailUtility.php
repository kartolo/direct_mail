<?php

namespace DirectMailTeam\DirectMail;

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

use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Utility\DmRegistryUtility;
use DirectMailTeam\DirectMail\Utility\FetchUtility;
use DirectMailTeam\DirectMail\Utility\RdctUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Static class.
 * Functions in this class are used by more than one modules.
 *
 * @author		Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 */
class DirectMailUtility
{
    /**
     * Get locallang label
     *
     * @param string $name Locallang label index
     *
     * @return string The label
     */
    public static function fName($name): string
    {
        return stripslashes(self::getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail', $name)));
    }

    /**
     * @return LanguageService
     */
    public static function getLanguageService(): LanguageService
    {
        return GeneralUtility::makeInstance(LanguageServiceFactory::class)->createFromUserPreferences(self::getBackendUser());
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    public static function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Get URL glue
     *
     * @param string $url
     *
     * @return string '& or ?'
     */
    public static function getURLGlue(string $url): string
    {
        return (strpos($url, '?') !== false) ? '&' : '?';
    }

    public static function prepareTypolinkParams(string $params): string
    {
        return substr($params, 0, 1) == '&' ? substr($params, 1) : $params;
    }

    public static function getTypolinkURL(
        string $parameter,
        bool $forceAbsoluteUrl = true,
        bool $linkAccessRestrictedPages = true
    ): string {

        if (empty($GLOBALS['TYPO3_REQUEST'])) {
            $context = GeneralUtility::makeInstance(Context::class);
            // Retrieve site and site language from context
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId(1);
            $siteLanguageId = $context->getPropertyFromAspect('language', 'id');
            $siteLanguage = $site->getLanguageById($siteLanguageId);
            $request = new ServerRequest(new Uri((string)$siteLanguage->getBase()));
            $request = $request->withAttribute('site', $site);
            $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
            $request = $request->withAttribute('language', $siteLanguage);
            $request = $request->withQueryParams(['id' => $site->getRootPageId()]);
            $GLOBALS['TYPO3_REQUEST'] = $request;
        }
        $typolinkPageUrl = 't3://page?uid=';
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        return $cObj->typolink_URL([
            'parameter' => $typolinkPageUrl . $parameter,
            'forceAbsoluteUrl' => $forceAbsoluteUrl,
            'linkAccessRestrictedPages' => $linkAccessRestrictedPages,
        ]);
    }

    /**
     * Fetch content of a page (only internal and external page)
     *
     * @param array $row Directmail DB record
     * @param array $params Any default parameters (usually the ones from pageTSconfig)
     * @param bool $returnArray Return error or warning message as array instead of string
     *
     * @return array|string Error or warning message during fetching the content
     */
    public static function fetchUrlContentsForDirectMailRecord(array $row, array $params, $returnArray = false)
    {
        $lllFile = 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf';
        $lang = self::getLanguageService();
        $output = '';
        $errorMsg = [];
        $warningMsg = [];
        $urls = self::getFullUrlsForDirectMailRecord($row);

        // Make sure long_link_rdct_url is consistent with baseUrl.
        //$row['long_link_rdct_url'] = $urls['baseUrl'];

        // Compile the mail
        /* @var $htmlmail Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        if ($params['enable_jump_url'] ?? false) {
            $glue = self::getURLGlue($urls['baseUrl']);
            $htmlmail->setJumperURLPrefix(
                $urls['baseUrl'] . $glue .
                'mid=###SYS_MAIL_ID###' .
                ((int)$params['jumpurl_tracking_privacy'] ? '' : '&rid=###SYS_TABLE_NAME###_###USER_uid###') .
                '&aC=###SYS_AUTHCODE###' .
                '&jumpurl='
            );

            $htmlmail->setJumperURLUseId(true);
        }
        if ($params['enable_mailto_jump_url'] ?? false) {
            $htmlmail->setJumperURLUseMailto(true);
        }

        $htmlmail->start();
        $htmlmail->setCharset($row['charset']);
        $htmlmail->setSimulateUsergroup($params['simulate_usergroup'] ?? 0);
        $htmlmail->setIncludeMedia($row['includeMedia']);

        if ($urls['plainTextUrl']) {
            $mailContent = GeneralUtility::makeInstance(FetchUtility::class)->getContents(self::addUserPass($urls['plainTextUrl'], $params));
            $htmlmail->addPlain($mailContent);
            if (!$mailContent || !$htmlmail->getPartPlainConfig('content')) {
                $errorMsg[] = $lang->sL($lllFile . ':dmail_no_plain_content');
            } elseif (!strstr($htmlmail->getPartPlainConfig('content'), '<!--DMAILER_SECTION_BOUNDARY')) {
                $warningMsg[] = $lang->sL($lllFile . ':dmail_no_plain_boundaries');
            }
        }

        // fetch the HTML url
        if ($urls['htmlUrl']) {
            // Username and password is added in htmlmail object
            $success = $htmlmail->addHTML(self::addUserPass($urls['htmlUrl'], $params));
            // If type = 1, we have an external page.
            if ($row['type'] == 1) {
                // Try to auto-detect the charset of the message
                $matches = [];
                $res = preg_match(
                    '/<meta[\s]+http-equiv="Content-Type"[\s]+content="text\/html;[\s]+charset=([^"]+)"/m',
                    ($htmlmail->getParts()['html_content'] ?? ''),
                    $matches
                );
                if ($res == 1) {
                    $htmlmail->setCharset($matches[1]);
                } elseif (isset($params['direct_mail_charset'])) {
                    $htmlmail->setCharset($params['direct_mail_charset']);
                } else {
                    $htmlmail->setCharset('iso-8859-1');
                }
            }
            if ($htmlmail->extractFramesInfo()) {
                $errorMsg[] = $lang->sL($lllFile . ':dmail_frames_not allowed');
            } elseif (!$success || !$htmlmail->getPartHtmlConfig('content')) {
                $errorMsg[] = $lang->sL($lllFile . ':dmail_no_html_content');
            } elseif (!strstr($htmlmail->getPartHtmlConfig('content'), '<!--DMAILER_SECTION_BOUNDARY')) {
                $warningMsg[] = $lang->sL($lllFile . ':dmail_no_html_boundaries');
            }
        }

        if (!count($errorMsg)) {
            // Update the record:
            $htmlmail->setPartMessageIdConfig($htmlmail->getMessageid());
            $mailContent = base64_encode(serialize($htmlmail->getParts()));

            $updateData = [
                'issent'             => 0,
                'charset'            => $htmlmail->getCharset(),
                'mailContent'        => $mailContent,
                'renderedSize'       => strlen($mailContent),
                'long_link_rdct_url' => $urls['baseUrl'],
            ];

            $done = GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmailRecord((int)$row['uid'], $updateData);

            if (count($warningMsg)) {
                $flashMessageRendererResolver = self::getFlashMessageRendererResolver();
                foreach ($warningMsg as $warning) {
                    $output .= $flashMessageRendererResolver
                        ->resolve()
                        ->render([
                            self::createFlashMessage($warning, $lang->sL($lllFile . ':dmail_warning'), ContextualFeedbackSeverity::WARNING, false),
                        ]);
                }
            }
        } else {
            $flashMessageRendererResolver = self::getFlashMessageRendererResolver();
            foreach ($errorMsg as $error) {
                $output .= $flashMessageRendererResolver
                    ->resolve()
                    ->render([
                        self::createFlashMessage($error, $lang->sL($lllFile . ':dmail_error'), ContextualFeedbackSeverity::ERROR, false),
                    ]);
            }
        }

        if ($returnArray) {
            return [
                'errors' => $errorMsg,
                'warnings' => $warningMsg,
            ];
        }

        return $output;
    }

    /**
        https://api.typo3.org/main/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_messaging_1_1_abstract_message.html
        const 	NOTICE = -2
        const 	INFO = -1
        const 	OK = 0
        const 	WARNING = 1
        const 	ERROR = 2
     * @param string $messageText
     * @param string $messageHeader
     * @param ContextualFeedbackSeverity $messageType
     * @param bool $storeInSession
     */
    protected static function createFlashMessage(
        string $messageText,
        string $messageHeader,
        ContextualFeedbackSeverity $messageType,
        bool $storeInSession = false): FlashMessage
    {
        return GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageText,
            $messageHeader, // [optional] the header
            $messageType, // [optional] the severity defaults to \TYPO3\CMS\Core\Messaging\FlashMessage::OK
            $storeInSession // [optional] whether the message should be stored in the session or only in the \TYPO3\CMS\Core\Messaging\FlashMessageQueue object (default is false)
        );
    }

    protected static function getFlashMessageRendererResolver(): FlashMessageRendererResolver
    {
        return GeneralUtility::makeInstance(FlashMessageRendererResolver::class);
    }

    /**
     * Add username and password for a password secured page
     * username and password are configured in the configuration module
     *
     * @param string $url The URL
     * @param array $params Parameters from pageTS
     *
     * @return string The new URL with username and password
     */
    protected static function addUserPass(string $url, array $params): string
    {
        $user = $params['http_username'] ?? '';
        $pass = $params['http_password'] ?? '';
        $matches = [];
        if ($user && $pass && preg_match('/^(?:http)s?:\/\//', $url, $matches)) {
            $url = $matches[0] . $user . ':' . $pass . '@' . substr($url, strlen($matches[0]));
        }
        if (($params['simulate_usergroup'] ?? false) && MathUtility::canBeInterpretedAsInteger($params['simulate_usergroup'])) {
            $glue = self::getURLGlue($url);
            $url = $url . $glue . 'dmail_fe_group=' . (int)$params['simulate_usergroup'] . '&access_token=' . GeneralUtility::makeInstance(DmRegistryUtility::class)->createAndGetAccessToken();
        }
        return $url;
    }

    /**
     * Set up URL variables for this $row.
     *
     * @param array $row Directmail DB record
     *
     * @return array $result Url_plain and url_html in an array
     */
    public static function getFullUrlsForDirectMailRecord(array $row): array
    {
        // Finding the domain to use
        if (!$_SERVER['HTTP_HOST']) {
            // In CLI / Scheduler context, $_SERVER['HTTP_HOST'] can be null
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId((int)$row['page']);
            $_SERVER['HTTP_HOST'] = $site->getBase()->getHost();
        }
        $result = [
            'baseUrl' => self::getTypolinkURL((int)$row['page']),
            'htmlUrl' => '',
            'plainTextUrl' => '',
        ];

        // Finding the url to fetch content from
        switch ((string)$row['type']) {
            case 1:
                $result['htmlUrl'] = $row['HTMLParams'];
                $result['plainTextUrl'] = $row['plainParams'];
                break;
            default:
                $params = self::prepareTypolinkParams($row['HTMLParams']);
                $result['htmlUrl'] = self::getTypolinkURL((int)$row['page'] . '&' . $params);
                $params = self::prepareTypolinkParams($row['plainParams']);
                $result['plainTextUrl'] = self::getTypolinkURL((int)$row['page'] . '&' . $params);
        }

        // plain
        if ($result['plainTextUrl']) {
            if (!($row['sendOptions'] & 1)) {
                $result['plainTextUrl'] = '';
            } else {
                $urlParts = @parse_url($result['plainTextUrl']);
                if (!$urlParts['scheme']) {
                    $result['plainTextUrl'] = 'http://' . $result['plainTextUrl'];
                }
            }
        }

        // html
        if ($result['htmlUrl']) {
            if (!($row['sendOptions'] & 2)) {
                $result['htmlUrl'] = '';
            } else {
                $urlParts = @parse_url($result['htmlUrl']);
                if (!$urlParts['scheme']) {
                    $result['htmlUrl'] = 'http://' . $result['htmlUrl'];
                }
            }
        }

        return $result;
    }

    /**
     * Wrapper for the old t3lib_div::intInRange.
     * Forces the integer $theInt into the boundaries of $min and $max.
     * If the $theInt is 'FALSE' then the $zeroValue is applied.
     */
    public static function intInRangeWrapper(
        int $theInt,
        int $min,
        int $max = 2000000000,
        int $zeroValue = 0
    ): int {
        return MathUtility::forceIntegerInRange($theInt, $min, $max, $zeroValue);
    }

    /**
     * Takes a clear-text message body for a plain text email, finds all 'http://' links and if they are longer than 76 chars they are converted to a shorter URL with a hash parameter.
     * The real parameter is stored in the database and the hash-parameter/URL will be redirected to the real parameter when the link is clicked.
     * This function is about preserving long links in messages.
     *
     * @param string $message Message content
     * @param string $urlmode URL mode; "76" or "all
     * @param string $index_script_url URL of index script (see makeRedirectUrl())
     * @return string Processed message content
     * @see makeRedirectUrl()
     * @deprecated since TYPO3 CMS 7, will be removed in TYPO3 CMS 8. Use mailer API instead
     */
    public static function substUrlsInPlainText(string $message, string $urlmode = '76', string $index_script_url = ''): string
    {
        $rdctUtility = GeneralUtility::makeInstance(RdctUtility::class);
        if (!$rdctUtility->installed()) {
            return $message;
        }

        $lengthLimit = $urlmode === 'all' ? 0 :(int)$urlmode;
        //$pattern = '/(http|https):\\/\\/.+(?=[\\]\\.\\?]*([\\! \'"()<>]+|$))/iU';
        // https://www.oreilly.com/library/view/regular-expressions-cookbook/9781449327453/ch08s02.html
        $pattern = '/\b((https?):\/\/|(www)\.)[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i';
        $messageSubstituted = preg_replace_callback(
            $pattern,
            function (array $matches) use ($rdctUtility, $lengthLimit, $index_script_url) {
                return $rdctUtility->getRedirects()->makeRedirectUrl($matches[0], $lengthLimit, $index_script_url);
            },
            $message
        );
        return $messageSubstituted;
    }

    /**
     * Fetches the attachment files referenced in the sys_dmail record.
     *
     * @param int $dmailUid The uid of the sys_dmail record to fetch the records for
     * @return FileReference[]
     */
    public static function getAttachments(int $dmailUid): array
    {
        /** @var FileRepository $fileRepository */
        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        return $fileRepository->findByRelation('sys_dmail', 'attachment', $dmailUid);
    }
}
