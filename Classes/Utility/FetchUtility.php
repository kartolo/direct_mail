<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Utility;

use DirectMailTeam\DirectMail\Utility\Typo3ConfVarsUtility;
use GuzzleHttp\Psr7\Response;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * https://docs.guzzlephp.org/en/latest/request-options.html#verify
 */
class FetchUtility
{
    /**
     * @return resource|null
     */
    public function getStreamContext()
    {
        $context = null;
        $applicationContext = Environment::getContext();
        if ($applicationContext->isDevelopment()) {
            $context = stream_context_create(
                [
                    'ssl' => [
                        'verify_peer' => Typo3ConfVarsUtility::getDMConfigSSLVerifyPeer(),
                        'verify_peer_name' => Typo3ConfVarsUtility::getDMConfigSSLVerifyPeerName(),
                    ],
                ]
            );
        }

        return $context;
    }

    public function getResponse(string $url): Response
    {
        $context = null;
        $applicationContext = Environment::getContext();
        if ($applicationContext->isDevelopment()) {
            $context = ['verify' => Typo3ConfVarsUtility::getDMConfigSSLVerify()];
        }

        return GeneralUtility::makeInstance(RequestFactory::class)->request($url, 'GET', $context);
    }

    public function getContents(string $url): string
    {
        try  {
            $respose = $this->getResponse($url);
            return (string)$respose->getBody()->getContents();
        } catch(\Exception $e) {
        }
        return '';
    }
}
