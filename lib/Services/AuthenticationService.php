<?php

/*
 * This file is part of the simplesamlphp-module-oidc.
 *
 * Copyright (C) 2018 by the Spanish Research and Academic Network.
 *
 * This code was developed by Universidad de Córdoba (UCO https://www.uco.es)
 * for the RedIRIS SIR service (SIR: http://www.rediris.es/sir)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SimpleSAML\Modules\OpenIDConnect\Services;

use Laminas\Diactoros\ServerRequest;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Error\Exception;
use SimpleSAML\Modules\OpenIDConnect\Controller\Traits\GetClientFromRequestTrait;
use SimpleSAML\Modules\OpenIDConnect\Entity\ClientEntity;
use SimpleSAML\Modules\OpenIDConnect\Entity\UserEntity;
use SimpleSAML\Modules\OpenIDConnect\Factories\AuthSimpleFactory;
use SimpleSAML\Modules\OpenIDConnect\Repositories\ClientRepository;
use SimpleSAML\Modules\OpenIDConnect\Repositories\UserRepository;

class AuthenticationService
{
    use GetClientFromRequestTrait;

    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var AuthSimpleFactory
     */
    private $authSimpleFactory;
    /**
     * @var string
     */
    private $userIdAttr;

    /**
     * @var AuthProcService
     */
    private $authProcService;

    /**
     * @var OidcProviderMetadataService
     */
    private $oidcProviderMetadataService;

    /**
     * @var IdProviderMetadataService
     */
    private $idProviderMetadataService;

    public function __construct(
        ConfigurationService $configurationService,
        UserRepository $userRepository,
        AuthSimpleFactory $authSimpleFactory,
        AuthProcService $authProcService,
        ClientRepository $clientRepository,
        OidcProviderMetadataService $oidcProviderMetadataService,
        IdProviderMetadataService $idProviderMetadataService,
        string $userIdAttr
    ) {
        $this->configurationService = $configurationService;
        $this->userRepository = $userRepository;
        $this->authSimpleFactory = $authSimpleFactory;
        $this->authProcService = $authProcService;
        $this->clientRepository = $clientRepository;
        $this->oidcProviderMetadataService = $oidcProviderMetadataService;
        $this->idProviderMetadataService = $idProviderMetadataService;
        $this->userIdAttr = $userIdAttr;
    }

    /**
     * @param ServerRequest $request
     * @return UserEntity
     * @throws \Exception
     */
    public function getAuthenticateUser(ServerRequest $request): UserEntity
    {
        $oidcClient = $this->getClientFromRequest($request);
        $authSource = $this->resolveAuthSource($oidcClient);

        $authSimple = $this->authSimpleFactory->build($authSource);
        $authSimple->requireAuth();

        $state = $this->prepareStateArray($authSimple, $oidcClient, $request);
        $state = $this->authProcService->processState($state);
        $claims = $state['Attributes'];

        if (!\array_key_exists($this->userIdAttr, $claims)) {
            $attr = implode(', ', array_keys($claims));
            throw new Exception('Attribute `useridattr` doesn\'t exists in claims. Available attributes are: ' . $attr);
        }

        $userId = $claims[$this->userIdAttr][0];
        $user = $this->userRepository->getUserEntityByIdentifier($userId);

        if (!$user) {
            $user = UserEntity::fromData($userId, $claims);
            $this->userRepository->add($user);
        } else {
            $user->setClaims($claims);
            $this->userRepository->update($user);
        }

        return $user;
    }

    /**
     * Get auth source defined on the client. If not set on the client, get the default auth source defined in config.
     *
     * @param ClientEntity $client
     * @return string
     * @throws \Exception
     */
    private function resolveAuthSource(ClientEntity $client): string
    {
        return $client->getAuthSource() ??
            $this->configurationService->getOpenIDConnectConfiguration()->getString('auth');
    }

    /**
     * @param Simple $authSimple
     * @param ClientEntity $client
     * @param ServerRequest $request
     * @return array
     */
    private function prepareStateArray(Simple $authSimple, ClientEntity $client, ServerRequest $request): array
    {
        $state = $authSimple->getAuthDataArray();

        $state['OidcProviderMetadata'] = $this->oidcProviderMetadataService->getMetadata();

        $state['OidcRelyingPartyMetadata'] = array_filter($client->toArray(), function (string $key) {
            return $key !== 'secret';
        }, ARRAY_FILTER_USE_KEY);

        $state['OidcAuthorizationRequestParameters'] = array_filter($request->getQueryParams(), function (string $key) {
            $relevantAuthzParams = ['response_type', 'client_id', 'redirect_uri', 'scope', 'code_challenge_method'];
            return in_array($key, $relevantAuthzParams);
        }, ARRAY_FILTER_USE_KEY);

        $state['Source'] = $state['IdPMetadata'] = $this->idProviderMetadataService->getMetadata();

        return $state;
    }
}
