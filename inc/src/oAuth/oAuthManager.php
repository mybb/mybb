<?php

declare(strict_types=1);

namespace MyBB\oAuth;

use InvalidArgumentException;
use MyBB\Database\Models\oAuthProvider;
use MyBB\Database\Repositories\oAuthRepository;
use Symfony\Component\Routing\Generator\UrlGenerator;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use Wohali\OAuth2\Client\Provider\Discord;
use ChrisHemmings\OAuth2\Client\Provider\Drupal;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\LinkedIn;
use Stevenmaguire\OAuth2\Client\Provider\Microsoft;
use Stevenmaguire\OAuth2\Client\Provider\Paypal;
use Kerox\OAuth2\Client\Provider\Spotify;
use Krombox\OAuth2\Client\Provider\WordPress;

readonly class oAuthManager
{
    public function __construct(
        private oAuthRepository $repository,
        private UrlGenerator $urlGenerator,
    ) {
    }

    private function buildProvider(oAuthProvider $providerModel, string $routerName): AbstractProvider
    {
        return match ($providerModel->provider_identifier) {
            'discord' => new Discord([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'discord']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            'drupal' => new Drupal([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'urlAuthorize' => $providerModel->url_authorize,
                'urlAccessToken' => $providerModel->url_access_token,
                'urlResourceOwnerDetails' => $providerModel->url_owner_details,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'drupal']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            'facebook' => new Facebook([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'facebook']),
                // todo, should probably add a new column to the session table to store a proper state string
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
                'graphApiVersion' => 'v2.10',
                'enableBetaTier' => false,
            ]),
            'github' => new Github([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'github']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            'google' => new Google([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'google']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            'linkedin' => new LinkedIn([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'linkedin']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            'microsoft' => new Microsoft([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'microsoft']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            'paypal' => new Paypal([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'paypal']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
                'isSandbox' => false,
            ]),
            'spotify' => new Spotify([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'spotify']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            'wordpress' => new WordPress([
                'clientId' => $providerModel->client_id,
                'clientSecret' => $providerModel->client_secret,
                'scope' => $providerModel->oauth_scopes,
                'urlAuthorize' => $providerModel->url_authorize,
                'urlAccessToken' => $providerModel->url_access_token,
                'urlResourceOwnerDetails' => $providerModel->url_owner_details,
                'redirectUri' => $this->urlGenerator->generate($routerName, ['providerIdentifier' => 'wordpress']),
                'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
            ]),
            default => throw new InvalidArgumentException('Unsupported OAuth provider.'),
        };
    }

    public function buildLoginProvider(string $providerIdentifier): AbstractProvider
    {
        $providerModel = $this->repository->providerFetch([
            'is_enabled' => 1,
            'provider_identifier' => $providerIdentifier,
            'allow_login' => 1,
        ], ['provider_identifier', 'client_id', 'client_secret', 'oauth_scopes', 'url_authorize', 'url_access_token', 'url_owner_details']);

        return $this->buildProvider($providerModel, 'LoginAuthComplete');
    }

    public function buildRegistrationProvider(string $providerIdentifier): AbstractProvider
    {
        $providerModel = $this->repository->providerFetch([
            'is_enabled' => 1,
            'provider_identifier' => $providerIdentifier,
            'allow_registration' => 1,
        ], ['provider_identifier', 'client_id', 'client_secret', 'oauth_scopes', 'url_authorize', 'url_access_token', 'url_owner_details']);

        return $this->buildProvider($providerModel, 'RegisterAuthComplete');
    }

    public function buildConnectionProvider(string $providerIdentifier): AbstractProvider
    {
        $providerModel = $this->repository->providerFetch([
            'is_enabled' => 1,
            'provider_identifier' => $providerIdentifier,
            'allow_connection' => 1,
        ], ['provider_identifier', 'client_id', 'client_secret', 'oauth_scopes', 'url_authorize', 'url_access_token', 'url_owner_details']);

        return $this->buildProvider($providerModel, 'ConnectionAuthComplete');
    }
}
