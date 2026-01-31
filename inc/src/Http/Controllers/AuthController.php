<?php

declare(strict_types=1);

namespace MyBB\Http\Controllers;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use MyBB;
use MyBB\Utilities\Encryptor;
use MyLanguage;
use PluginSystem;
use Symfony\Component\HttpFoundation\Response;
use LoginDataHandler;
use UserDataHandler;
use MyBB\Database\Repositories\oAuthRepository;
use MyBB\Database\Models\oAuthProvider;

readonly class AuthController
{
    public function __construct(
        private MyBB $mybb,
        private MyLanguage $lang,
        private oAuthRepository $repository,
        private MyBB\oAuth\oAuthManager $manager,
        private PluginSystem $plugins,
    ) {
    }

    private function getEncryptionKey(): string
    {
        return bin2hex(
            getenv('OAUTH_ENCRYPTION_KEY') ?: (string)$this->mybb->config['OAUTH_ENCRYPTION_KEY']
        );
    }

    private function storeToken(oAuthProvider $providerModel, AccessTokenInterface $token, int $user_identifier): void
    {
        if (!$providerModel->store_token || !($encryptionKey = $this->getEncryptionKey())) {
            return;
        }

        try {
            $encryptor = new Encryptor(hex2bin($encryptionKey));

            $accessToken = $encryptor->encrypt($token->getToken());

            $refreshToken = $encryptor->encrypt($token->getRefreshToken());

            $this->repository->tokenInsertUpdate([
                'user_id' => $user_identifier,
                'provider_identifier' => $providerModel->provider_identifier,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $token->getExpires(),
            ]);
        } catch (Exception $e) {
            error($e->getMessage());
        }
    }

    private function storeState(string $providerIdentifier, AbstractProvider $provider): void
    {
        $this->repository->stateInsert([
            'session_id' => $this->mybb->session->sid,
            'provider_identifier' => $providerIdentifier,
            'state_code' => $provider->getState(),
            'pkce_code' => $provider->getPkceCode(),
            'created_at' => TIME_NOW,
        ]);
    }

    private function validateState(string $providerIdentifier): mixed
    {
        $stateModel = $this->repository->stateFetch([
            'session_id' => $this->mybb->session->sid,
            'provider_identifier' => $providerIdentifier,
            'state_code' => $this->mybb->get_input('state'),
        ]);

        if ($stateModel === false) {
            error('An error occurred while processing your request.');
        }

        return $stateModel;
    }

    /** @return array{AccessTokenInterface, ResourceOwnerInterface} */
    private function getAccessTokenAndUserDetails(AbstractProvider $provider, string $statePkceCode): array
    {
        try {
            $provider->setPkceCode($statePkceCode);

            $token = $provider->getAccessToken(
                'authorization_code',
                ['code' => $this->mybb->get_input('code')]
            );

            $userDetails = $provider->getResourceOwner($token);
        } catch (Exception|GuzzleException $e) {
            error($e->getMessage());
        }

        return [$token, $userDetails];
    }

    public function generateUsernamePool(ResourceOwnerInterface $userDetails, string $providerIdentifier): array
    {
        $usernamesPool = [explode('@', $userDetails->getEmail())[0] ?? ''];

        if (method_exists($userDetails, 'getUsername')) {
            $usernamesPool[] = str_replace(' ', '-', $userDetails->getUsername());
        }

        if (method_exists($userDetails, 'getNickname')) {
            $usernamesPool[] = str_replace(' ', '-', $userDetails->getNickname());
        }

        if (method_exists($userDetails, 'getName')) {
            $usernamesPool[] = str_replace(' ', '-', $userDetails->getName());
        }

        if (method_exists($userDetails, 'getFirstname')) {
            $usernamesPool[] = str_replace(' ', '-', $userDetails->getFirstname());
        }

        if (method_exists($userDetails, 'getLastName')) {
            $usernamesPool[] = str_replace(' ', '-', $userDetails->getLastName());
        }

        $usernamesPool[] = random_str().ucfirst($providerIdentifier);

        $usernamesPool[] = random_str();

        return $usernamesPool;
    }

    private function findAvailableUsername(array $userNamePools): string
    {
        foreach ($userNamePools as $usernameCandidate) {
            if (!get_user_by_username(trim($usernameCandidate), ['exists' => true])) {
                return $usernameCandidate;
            }
        }

        return '';
    }

    public function oauthLogin(string $providerIdentifier): void
    {
        if (!empty($this->mybb->user['uid'])) {
            error_no_permission();
        }

        try {
            $provider = $this->manager->buildLoginProvider($providerIdentifier);
        } catch (Exception $e) {
            error($e->getMessage(), status_code: Response::HTTP_BAD_GATEWAY);
        }

        $this->plugins->run_hooks('controller_oauth_login_start', $provider);

        $redirectUrl = $provider->getAuthorizationUrl();

        $this->storeState($providerIdentifier, $provider);

        header('Location: '.$redirectUrl);

        exit;
    }

    public function oauthLoginComplete(string $providerIdentifier): never
    {
        if (
            !empty($this->mybb->user['uid']) ||
            !$this->mybb->get_input('state')
        ) {
            error_no_permission();
        }

        $stateModel = $this->validateState($providerIdentifier);

        if ($stateModel === false) {
            error('An error occurred while processing your registration request.');
        }

        $providerModel = $this->repository->providerFetch([
            'provider_identifier' => $providerIdentifier,
        ], ['store_token']);

        try {
            $provider = $this->manager->buildLoginProvider($providerIdentifier);
        } catch (Exception $e) {
            error($e->getMessage(), status_code: Response::HTTP_BAD_GATEWAY);
        }

        [$token, $userDetails] = $this->getAccessTokenAndUserDetails($provider, $stateModel->pkce_code);

        $oauthId = $userDetails->getId();

        $userModel = $this->repository->userFetch([
            'is_active' => $this->repository::USER_PROVIDER_IS_ACTIVE,
            'provider_identifier' => $providerIdentifier,
            'oauth_id' => $oauthId,
        ]);

        if ($userModel === false) {
            error('An error occurred while processing your login request.');
        }

        $userData = get_user($userModel->user_id);

        if (!$userData) {
            error(
                'There is no account associated with this provider. Please register first or connect the provider to your account before using it.'
            );
        }

        require_once MYBB_ROOT.'inc/functions_user.php';
        require_once MYBB_ROOT.'inc/datahandlers/login.php';

        $loginDataHandler = new LoginDataHandler('get');

        $loginDataHandler->set_data([
            'uid' => $userData['uid'],
            'username' => $userData['username'], // required for `get_login_data()`
            'remember' => true,
        ]);

        if (!$loginDataHandler->validate_login()) {
            error(
                'An error occurred while processing your login request.'.inline_error(
                    $loginDataHandler->get_friendly_errors()
                )
            );
        }

        $loginDataHandler->complete_login();

        $this->storeToken($providerModel, $token, (int)$userData['uid']);

        $this->lang->load('member');

        redirect($this->mybb->settings['bburl'], $this->lang->redirect_loggedin);

        exit;
    }

    public function oauthRegister(string $providerIdentifier): void
    {
        if (!empty($this->mybb->user['uid'])) {
            error_no_permission();
        }

        try {
            $provider = $this->manager->buildRegistrationProvider($providerIdentifier);
        } catch (Exception $e) {
            error($e->getMessage(), status_code: Response::HTTP_BAD_GATEWAY);
        }

        $this->plugins->run_hooks('controller_oauth_register_start', $provider);

        $redirectUrl = $provider->getAuthorizationUrl();

        $this->storeState($providerIdentifier, $provider);

        header('Location: '.$redirectUrl);

        exit;
    }

    public function oauthRegistrationComplete(string $providerIdentifier): never
    {
        if (
            !empty($this->mybb->user['uid']) ||
            !$this->mybb->get_input('state')
        ) {
            error_no_permission();
        }

        $stateModel = $this->validateState($providerIdentifier);

        if ($stateModel === false) {
            error('An error occurred while processing your registration request.');
        }

        $providerModel = $this->repository->providerFetch([
            'provider_identifier' => $providerIdentifier,
        ], ['store_token', 'provider_identifier']);

        try {
            $provider = $this->manager->buildRegistrationProvider($providerIdentifier);
        } catch (Exception $e) {
            error($e->getMessage(), status_code: Response::HTTP_BAD_GATEWAY);
        }

        [$token, $userDetails] = $this->getAccessTokenAndUserDetails($provider, $stateModel->pkce_code);

        $oauthId = $userDetails->getId();

        $userModel = $this->repository->userFetch([
            'is_active' => $this->repository::USER_PROVIDER_IS_ACTIVE,
            'provider_identifier' => $providerIdentifier,
            'oauth_id' => $oauthId,
        ]);

        if (!$oauthId || $userModel !== false) {
            error('An error occurred while processing your registration request.');
        }

        $email = $userDetails->getEmail();

        $userData = get_user_by_username($email, ['username_method' => 1]);

        if ($userData && !$this->mybb->settings['allowmultipleemails']) {
            error(
                'An account with this email address already exists. Please login using your existing account or use a different email address.'
            );
        }

        require_once MYBB_ROOT.'inc/datahandlers/user.php';

        $userDataHandler = new UserDataHandler('insert');

        $username = $this->findAvailableUsername(
            $this->generateUsernamePool($userDetails, $providerIdentifier)
        );

        require_once MYBB_ROOT.'inc/functions_user.php';

        $userInsertData = [
            'username' => trim($username),
            'password' => '',
            'salt' => '',
            'password_algorithm' => '',
            'loginkey' => generate_loginkey(),
            'usergroup' => 2,
            'regip' => $this->mybb->session->packedip,
            'registration' => true,
        ];

        if ($email) {
            $userInsertData['email'] = $userInsertData['email2'] = $email;
        }

        $userDataHandler->set_data($userInsertData);

        $userDataHandler->validate_user();

        unset(
            $userDataHandler->errors['invalid_password_length'],
            $userDataHandler->errors['bad_password_security'],
            $userDataHandler->errors['no_complex_characters'],
            $userDataHandler->errors['passwords_dont_match'],
            $userDataHandler->errors['missing_required_profile_field'],
            $userDataHandler->errors['bad_profile_field_values'],
            $userDataHandler->errors['max_limit_reached'],
            $userDataHandler->errors['bad_profile_field_value'],
        );

        if (!$userDataHandler->get_errors()) {
            $userData = $userDataHandler->insert_user();
        } else {
            error(inline_error($userDataHandler->get_friendly_errors()));
        }

        my_setcookie(
            'mybbuser',
            $userData['uid'].'_'.$userData['loginkey'],
            null,
            true,
            'lax'
        );

        $this->repository->userInsert([
            'user_id' => $userData['uid'],
            'provider_identifier' => $providerIdentifier,
            'oauth_id' => $oauthId,
        ]);

        $this->storeToken($providerModel, $token, (int)$userData['uid']);

        $this->lang->load('member');

        redirect(
            $this->mybb->settings['bburl'],
            $this->lang->sprintf(
                $this->lang->redirect_registered,
                $this->mybb->settings['bbname'],
                htmlspecialchars_uni($userData['username'])
            )
        );

        exit;
    }

    public function oauthConnect(string $providerIdentifier): void
    {
        if (
            empty($this->mybb->user['uid']) ||
            $this->repository->userActiveProvidersExists($providerIdentifier, (int)$this->mybb->user['uid']) !== false
        ) {
            error_no_permission();
        }

        try {
            $provider = $this->manager->buildConnectionProvider($providerIdentifier);
        } catch (Exception $e) {
            error($e->getMessage(), status_code: Response::HTTP_BAD_GATEWAY);
        }

        $this->plugins->run_hooks('controller_oauth_connect_start', $provider);

        $redirectUrl = $provider->getAuthorizationUrl();

        $this->storeState($providerIdentifier, $provider);

        header('Location: '.$redirectUrl);

        exit;
    }

    public function oauthConnectionComplete(string $providerIdentifier): never
    {
        if (
            empty($this->mybb->user['uid']) ||
            !$this->mybb->get_input('state')
        ) {
            error_no_permission();
        }

        $stateModel = $this->validateState($providerIdentifier);

        if ($stateModel === false) {
            error('An error occurred while processing your registration request.');
        }

        $providerModel = $this->repository->providerFetch([
            'provider_identifier' => $providerIdentifier,
        ], ['store_token']);

        try {
            $provider = $this->manager->buildConnectionProvider($providerIdentifier);
        } catch (Exception $e) {
            error($e->getMessage(), status_code: Response::HTTP_BAD_GATEWAY);
        }

        [$token, $userDetails] = $this->getAccessTokenAndUserDetails($provider, $stateModel->pkce_code);

        $oauthId = $userDetails->getId();

        $userModel = $this->repository->userFetch([
            'is_active' => $this->repository::USER_PROVIDER_IS_ACTIVE,
            'provider_identifier' => $providerIdentifier,
            'oauth_id' => $oauthId,
        ]);

        if ($userModel !== false) {
            error('An error occurred while processing your connection request.');
        }

        $userData = $this->mybb->user;

        $this->repository->userInsert([
            'user_id' => $userData['uid'],
            'provider_identifier' => $providerIdentifier,
            'oauth_id' => $oauthId,
        ]);

        $this->storeToken($providerModel, $token, (int)$userData['uid']);

        $this->lang->load('usercp');

        redirect(
            $this->mybb->settings['bburl'].'/usercp.php?action=connections',
            $this->lang->sprintf(
                $this->lang->connections_redirect_provider_connected,
                $this->lang->{'connections_provider_'.$providerIdentifier},
            )
        );

        exit;
    }

    public function oauthDisconnect(string $providerIdentifier): void
    {
        if (
            empty($this->mybb->user['uid']) ||
            $this->repository->userActiveProvidersExists($providerIdentifier, (int)$this->mybb->user['uid']) === false
        ) {
            error_no_permission();
        }

        $this->plugins->run_hooks('controller_oauth_disconnect_start', $provider);

        $this->repository->userDisconnect($providerIdentifier, (int)$this->mybb->user['uid']);

        $this->lang->load('usercp');

        redirect(
            $this->mybb->settings['bburl'].'/usercp.php?action=connections',
            $this->lang->sprintf(
                $this->lang->connections_redirect_provider_disconnected,
                $this->lang->{'connections_provider_'.$providerIdentifier},
            )
        );
    }
}
