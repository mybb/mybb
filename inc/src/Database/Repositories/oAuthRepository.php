<?php

declare(strict_types=1);

namespace MyBB\Database\Repositories;

use DB_Base;
use MyBB\Database\Models\oAuthProvider;
use MyBB\Database\Models\oAuthState;
use MyBB\Database\Models\oAuthToken;
use MyBB\Database\Models\oAuthUser;

readonly class oAuthRepository
{
    public const PROVIDERS_TABLE = 'oauth_providers';
    public const USERS_TABLE = 'oauth_users';
    public const STATES_TABLE = 'oauth_states';
    public const TOKENS_TABLE = 'oauth_tokens';
    public const USER_PROVIDER_IS_ACTIVE = 1;

    public function __construct(
        private DB_Base $db,
    ) {
    }

    public function providerFetch(
        oAuthProvider|array $providerModel = [],
        array $queryFields = ['provider_identifier',]
    ): oAuthProvider|false {
        $stateData = array_map(
            $this->db->escape_string(...),
            is_array($providerModel) ? $providerModel : $providerModel->toArray(),
        );

        array_walk($stateData, function (string &$value, string $column) {
            $value = "{$column}='{$value}'";
        });

        $query = $this->db->simple_select(
            self::PROVIDERS_TABLE,
            implode(', ', $queryFields),
            implode(' AND ', $stateData),
        );

        if ($this->db->num_rows($query)) {
            return $this->providerModelFromArray($this->db->fetch_array($query));
        }

        return false;
    }

    /** @return iterable<oAuthProvider> */
    public function providersFetch(
        array $whereClauses = [],
        array $queryFields = ['provider_identifier',],
        array $queryOptions = [],
    ): iterable {
        $stateData = array_map(
            $this->db->escape_string(...),
            $whereClauses,
        );

        array_walk($stateData, function (string &$value, string $column) {
            $value = "{$column}='{$value}'";
        });

        $query = $this->db->simple_select(
            self::PROVIDERS_TABLE,
            implode(', ', $queryFields),
            implode(' AND ', $stateData),
            $queryOptions
        );

        while ($providerData = $this->db->fetch_array($query)) {
            yield $this->providerModelFromArray($providerData);
        }
    }

    public function providerModelFromArray(array $data): oAuthProvider
    {
        return new oAuthProvider(...$data);
    }

    public function providerInsertUpdate(oAuthProvider|array $providerModel): void
    {
        if (!is_array($providerModel)) {
            $providerModel = $providerModel->toArray();
        }

        if (!empty($providerModel['provider_identifier'])) {
            $query = $this->db->simple_select(
                self::PROVIDERS_TABLE,
                'provider_identifier',
                "provider_identifier='{$providerModel['provider_identifier']}'"
            );

            if ($this->db->num_rows($query)) {
                $this->db->update_query(
                    self::PROVIDERS_TABLE,
                    array_map(
                        $this->db->escape_string(...),
                        $providerModel,
                    ),
                    "provider_identifier='{$providerModel['provider_identifier']}'"
                );

                return;
            }
        }

        $this->db->insert_query(
            self::PROVIDERS_TABLE,
            array_map(
                $this->db->escape_string(...),
                $providerModel,
            ),
        );
    }

    public function userInsert(oAuthUser|array $userModel): void
    {
        $this->db->insert_query(
            self::USERS_TABLE,
            array_map(
                $this->db->escape_string(...),
                is_array($userModel) ? $userModel : $userModel->toArray(),
            ),
        );
    }

    public function userFetch(
        oAuthUser|array $userModel,
        array $queryFields = ['user_id']
    ): oAuthUser|false {
        $userData = array_map(
            $this->db->escape_string(...),
            is_array($userModel) ? $userModel : $userModel->toArray(),
        );

        array_walk($userData, function (string &$value, string $column) {
            $value = "{$column}='{$value}'";
        });

        $query = $this->db->simple_select(
            self::USERS_TABLE,
            implode(', ', $queryFields),
            implode(' AND ', $userData),
        );

        if ($this->db->num_rows($query)) {
            return $this->userModelFromArray($this->db->fetch_array($query));
        }

        return false;
    }

    public function usersFetch(
        array $whereClauses,
        array $queryFields = ['user_id']
    ): iterable {
        $whereClauses = array_map(
            $this->db->escape_string(...),
            $whereClauses,
        );

        array_walk($whereClauses, function (string &$value, string $column) {
            $value = "{$column}='{$value}'";
        });

        $query = $this->db->simple_select(
            self::USERS_TABLE,
            implode(', ', $queryFields),
            implode(' AND ', $whereClauses),
        );

        while ($userData = $this->db->fetch_array($query)) {
            yield $this->userModelFromArray($userData);
        }
    }

    public function userActiveProvidersExists(string $providerIdentifier, int $userIdentifier): string|false
    {
        $userModel = $this->userFetch([
            'is_active' => self::USER_PROVIDER_IS_ACTIVE,
            'provider_identifier' => $providerIdentifier,
            'user_id' => $userIdentifier,
        ], ['oauth_id']);

        if ($userModel !== false) {
            return $userModel->oauth_id;
        }

        return false;
    }

    public function userModelFromArray(array $data): oAuthUser
    {
        return new oAuthUser(...$data);
    }

    public function userDisconnect(string $providerIdentifier, int $userIdentifier): void
    {
        $this->db->update_query(
            self::USERS_TABLE,
            ['user_id' => 0],
            "provider_identifier='{$providerIdentifier}' AND user_id='{$userIdentifier}'"
        );
    }

    public function stateInsert(oAuthState|array $stateModel): void
    {
        $this->db->insert_query(
            self::STATES_TABLE,
            array_map(
                $this->db->escape_string(...),
                is_array($stateModel) ? $stateModel : $stateModel->toArray(),
            ),
        );
    }

    public function stateFetch(
        oAuthState|array $stateModel,
        array $queryFields = ['state_id', 'state_code', 'pkce_code']
    ): oAuthState|false {
        $stateData = array_map(
            $this->db->escape_string(...),
            is_array($stateModel) ? $stateModel : $stateModel->toArray(),
        );

        array_walk($stateData, function (string &$value, string $column) {
            $value = "{$column}='{$value}'";
        });

        $query = $this->db->simple_select(
            self::STATES_TABLE,
            implode(', ', $queryFields),
            implode(' AND ', $stateData),
        );

        if ($this->db->num_rows($query)) {
            return $this->stateModelFromArray($this->db->fetch_array($query));
        }

        return false;
    }

    public function stateModelFromArray(array $data): oAuthState
    {
        return new oAuthState(...$data);
    }

    public function tokenInsertUpdate(oAuthToken|array $tokenModel): void
    {
        if (!is_array($tokenModel)) {
            $tokenModel = $tokenModel->toArray();
        }

        if (!empty($tokenModel['user_id']) && !empty($tokenModel['provider_identifier'])) {
            $query = $this->db->simple_select(
                self::TOKENS_TABLE,
                'token_id',
                "user_id='{$tokenModel['user_id']}' AND provider_identifier='{$tokenModel['provider_identifier']}'"
            );

            if ($this->db->num_rows($query)) {
                $this->db->update_query(
                    self::TOKENS_TABLE,
                    array_map(
                        $this->db->escape_string(...),
                        $tokenModel,
                    ),
                    "user_id='{$tokenModel['user_id']}' AND provider_identifier='{$tokenModel['provider_identifier']}'"
                );

                return;
            }
        }

        $this->db->insert_query(
            self::TOKENS_TABLE,
            array_map(
                $this->db->escape_string(...),
                $tokenModel + ['created_at' => TIME_NOW],
            ),
        );
    }

    /** @return iterable<oAuthToken> */
    public function tokensFetch(
        array $whereClauses = [],
        array $queryFields = ['token_id',],
        array $queryOptions = [],
    ): iterable {
        $whereClauses = array_map(
            $this->db->escape_string(...),
            $whereClauses,
        );

        array_walk($whereClauses, function (string &$value, string $column) {
            $value = "{$column}='{$value}'";
        });

        $query = $this->db->simple_select(
            self::TOKENS_TABLE,
            implode(', ', $queryFields),
            implode(' AND ', $whereClauses),
            $queryOptions
        );

        while ($providerData = $this->db->fetch_array($query)) {
            yield $this->tokenModelFromArray($providerData);
        }
    }

    public function tokenModelFromArray(array $data): oAuthToken
    {
        return new oAuthToken(...$data);
    }
}
