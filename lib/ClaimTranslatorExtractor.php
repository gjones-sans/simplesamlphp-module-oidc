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

namespace SimpleSAML\Modules\OpenIDConnect;

use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetEntity;

class ClaimTranslatorExtractor extends ClaimExtractor
{
    /** @var array */
    protected $translationTable = [
        'sub' => [
            'eduPersonPrincipalName',
            'eduPersonTargetedID',
            'eduPersonUniqueId',
        ],
        'name' => [
            'cn',
            'displayName',
        ],
        'family_name' => [
            'sn',
        ],
        'given_name' => [
            'givenName',
        ],
        'middle_name' => [
            // Empty
        ],
        'nickname' => [
            'eduPersonNickname',
        ],
        'preferred_username' => [
            'uid',
        ],
        'profile' => [
            'labeledURI',
            'description',
        ],
        'picture' => [
            'jpegPhoto',
        ],
        'website' => [
            // Empty
        ],
        'gender' => [
            // Empty
        ],
        'birthdate' => [
            // Empty
        ],
        'zoneinfo' => [
            // Empty
        ],
        'locale' => [
            'preferredLanguage',
        ],
        'updated_at' => [
            // Empty
        ],
        'email' => [
            'mail',
        ],
        'email_verified' => [
            // Empty
        ],
        'address' => [
            'postalAddress',
        ],
        'phone_number' => [
            'mobile',
            'telephoneNumber',
            'homePhone',
        ],
        'phone_number_verified' => [
            // Empty
        ],
    ];

    /**
     * Claims for which it is allowed to have multiple values.
     * @var array $allowedMultiValueClaims
     */
    protected $allowedMultiValueClaims;

    /**
     * ClaimTranslatorExtractor constructor.
     *
     * @param ClaimSetEntity[] $claimSets
     *
     * @param array $translationTable
     * @param array $allowedMultipleValueClaims
     * @throws \OpenIDConnectServer\Exception\InvalidArgumentException
     */
    public function __construct(
        array $claimSets = [],
        array $translationTable = [],
        array $allowedMultipleValueClaims = []
    ) {
        $this->translationTable = array_merge($this->translationTable, $translationTable);

        $this->allowedMultiValueClaims = $allowedMultipleValueClaims;

        $this->protectedClaims[] = 'openid';
        $this->addClaimSet(new ClaimSetEntity('openid', [
            'sub',
        ]));

        parent::__construct($claimSets);
    }

    /**
     * @param array $samlAttributes
     */
    private function translateSamlAttributesToClaims($samlAttributes): array
    {
        $claims = [];

        foreach ($this->translationTable as $claim => $samlMatches) {
            foreach ($samlMatches as $samlMatch) {
                if (\array_key_exists($samlMatch, $samlAttributes)) {
                    $claims[$claim] = in_array($claim, $this->allowedMultiValueClaims) ?
                        $samlAttributes[$samlMatch] :
                        current($samlAttributes[$samlMatch]);
                    break;
                }
            }
        }

        return $claims;
    }

    public function extract(array $scopes, array $claims): array
    {
        $translatedClaims = $this->translateSamlAttributesToClaims($claims);

        return parent::extract($scopes, $translatedClaims);
    }
}
