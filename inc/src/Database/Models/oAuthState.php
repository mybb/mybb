<?php

declare(strict_types=1);

namespace MyBB\Database\Models;

class oAuthState
{
    public function __construct(
        public ?int $state_id = null,
        public ?string $session_id = null,
        public ?string $provider_identifier = null,
        public ?string $state_code = null,
        public ?string $pkce_code = null,
        public ?int $created_at = null,
    ) {
    }

    public function toArray(): array
    {
        $array = [];

        if ($this->state_id) {
            $array['state_id'] = $this->state_id;
        }

        if ($this->session_id) {
            $array['session_id'] = $this->session_id;
        }

        if ($this->provider_identifier) {
            $array['provider_identifier'] = $this->provider_identifier;
        }

        if ($this->state_code) {
            $array['state_code'] = $this->state_code;
        }

        if ($this->pkce_code) {
            $array['pkce_code'] = $this->pkce_code;
        }

        if ($this->created_at) {
            $array['created_at'] = $this->created_at;
        }

        return $array;
    }
}
