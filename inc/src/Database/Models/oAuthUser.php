<?php

declare(strict_types=1);

namespace MyBB\Database\Models;

class oAuthUser
{
    public function __construct(
        public ?int $user_id = null,
        public ?string $provider_identifier = null,
        public ?string $oauth_id = null,
        public ?int $is_active = null,
        public ?int $created_at = null,
    ) {
    }

    public function toArray(): array
    {
        $array = [];

        if ($this->user_id) {
            $array['user_id'] = $this->user_id;
        }

        if ($this->provider_identifier) {
            $array['provider_identifier'] = $this->provider_identifier;
        }

        if ($this->oauth_id) {
            $array['oauth_id'] = $this->oauth_id;
        }

        if ($this->is_active) {
            $array['is_active'] = $this->is_active;
        }

        if ($this->created_at) {
            $array['created_at'] = $this->created_at;
        }

        return $array;
    }
}
