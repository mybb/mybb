<?php

declare(strict_types=1);

namespace MyBB\Database\Models;

class oAuthToken
{
    public function __construct(
        public ?int $token_id = null,
        public ?int $user_id = null,
        public ?string $provider_identifier = null,
        public ?string $access_token = null,
        public ?string $refresh_token = null,
        public ?int $created_at = null,
        public ?int $expires_at = null,
    ) {
    }

    public function toArray(): array
    {
        $array = [];

        if ($this->token_id) {
            $array['token_id'] = $this->token_id;
        }

        if ($this->user_id) {
            $array['user_id'] = $this->user_id;
        }

        if ($this->provider_identifier) {
            $array['provider_identifier'] = $this->provider_identifier;
        }

        if ($this->access_token) {
            $array['access_token'] = $this->access_token;
        }

        if ($this->refresh_token) {
            $array['refresh_token'] = $this->refresh_token;
        }

        if ($this->created_at) {
            $array['created_at'] = $this->created_at;
        }

        if ($this->expires_at) {
            $array['expires_at'] = $this->expires_at;
        }

        return $array;
    }
}
