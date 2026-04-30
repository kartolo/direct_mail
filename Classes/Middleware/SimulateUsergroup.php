<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Middleware;

use DirectMailTeam\DirectMail\Utility\DmRegistryUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

class SimulateUsergroup implements MiddlewareInterface
{
    public function __construct(
        private readonly Context $context,
        private readonly DmRegistryUtility $registryUtility,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $directMailFeGroup = (int)($queryParams['dmail_fe_group'] ?? 0);
        $accessToken = (string)($queryParams['access_token'] ?? '');

        if ($directMailFeGroup > 0 && $this->registryUtility->validateAndRemoveAccessToken($accessToken)) {
            /** @var UserAspect $userAspect */
            $userAspect = $this->context->getAspect('frontend.user');
            $frontendUser = $request->getAttribute('frontend.user');

            if ($frontendUser instanceof FrontendUserAuthentication && !in_array($directMailFeGroup, $userAspect->getGroupIds(), true)) {
                $this->context->setAspect('frontend.user', new UserAspect($frontendUser, [$directMailFeGroup]));
            }
        }

        return $handler->handle($request);
    }
}
