<?php

namespace SimpleSAML\Modules\OpenIDConnect\Services;

/**
 * OpenID Connect (OIDC) Provider Metadata Service - provides information about OIDC authentication server.
 *
 * Class OPMetadataService
 * @package SimpleSAML\Modules\OpenIDConnect\Services
 */
class OidcProviderMetadataService
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    /**
     * @var array $metadata
     */
    private $metadata;

    public function __construct(
        ConfigurationService $configurationService
    ) {
        $this->configurationService = $configurationService;
        $this->initMetadata();
    }

    /**
     * Initialize metadata array.
     */
    public function initMetadata(): void
    {
        $this->metadata = [];
        $this->metadata['issuer'] = $this->configurationService->getSimpleSAMLSelfURLHost();
        $this->metadata['authorization_endpoint'] =
            $this->configurationService->getOpenIdConnectModuleURL('authorize.php');
        $this->metadata['token_endpoint'] = $this->configurationService->getOpenIdConnectModuleURL('access_token.php');
        $this->metadata['userinfo_endpoint'] = $this->configurationService->getOpenIdConnectModuleURL('userinfo.php');
        $this->metadata['jwks_uri'] = $this->configurationService->getOpenIdConnectModuleURL('jwks.php');
        $this->metadata['scopes_supported'] = array_keys($this->configurationService->getOpenIDScopes());
        $this->metadata['response_types_supported'] = ['code', 'token'];
        $this->metadata['subject_types_supported'] = ['public'];
        $this->metadata['id_token_signing_alg_values_supported'] = ['RS256'];
        $this->metadata['code_challenge_methods_supported'] = ['plain', 'S256'];
    }

    /**
     * Get OIDC Provider (OP) metadata array.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
