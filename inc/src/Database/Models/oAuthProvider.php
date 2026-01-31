<?php

declare(strict_types=1);

namespace MyBB\Database\Models;

class oAuthProvider
{
    public function __construct(
        public ?string $provider_identifier = null,
        public ?string $client_id = null,
        public ?string $client_secret = null,
        public ?string $oauth_scopes = null,
        public ?string $url_authorize = null,
        public ?string $url_access_token = null,
        public ?string $url_owner_details = null,
        public ?int $store_token = null,
        public ?int $allow_login = null,
        public ?int $allow_registration = null,
        public ?int $allow_connection = null,
        public ?int $is_enabled = null,
    ) {
    }

    public function toArray(): array
    {
        $array = [];

        if ($this->provider_identifier) {
            $array['provider_identifier'] = $this->provider_identifier;
        }

        if ($this->client_id) {
            $array['client_id'] = $this->client_id;
        }

        if ($this->client_secret) {
            $array['client_secret'] = $this->client_secret;
        }

        if ($this->oauth_scopes) {
            $array['oauth_scopes'] = $this->oauth_scopes;
        }

        if ($this->url_authorize) {
            $array['url_authorize'] = $this->url_authorize;
        }

        if ($this->url_access_token) {
            $array['url_access_token'] = $this->url_access_token;
        }

        if ($this->url_owner_details) {
            $array['url_owner_details'] = $this->url_owner_details;
        }

        if ($this->store_token) {
            $array['store_token'] = $this->store_token;
        }

        if ($this->allow_login) {
            $array['allow_login'] = $this->allow_login;
        }

        if ($this->allow_registration) {
            $array['allow_registration'] = $this->allow_registration;
        }

        if ($this->allow_connection) {
            $array['allow_connection'] = $this->allow_connection;
        }

        if ($this->is_enabled) {
            $array['is_enabled'] = $this->is_enabled;
        }

        return $array;
    }
}
