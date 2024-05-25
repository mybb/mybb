<?php
/**
 * Functions used with processing HTTP requests, responses, and HTML rendering.
 */

declare(strict_types=1);

namespace MyBB\Maintenance;

use Illuminate\Http\Request;
use MyBB;
use MyLanguage;
use session;

use function MyBB\app;

#region general
function httpSetup(): void
{
    $mybb = app(MyBB::class);
    $lang = app(MyLanguage::class);

    $request = Request::capture();

    app()->instance(Request::class, $request);

    if (
        $request->has('language') &&
        is_string($request->get('language')) &&
        array_key_exists($request->get('language'), $lang->get_languages())
    ) {
        $lang->set_language($request->get('language'));
    }

    if (InstallationState::get(true) === InstallationState::INSTALLED) {
        $mybb->parse_cookies();

        require_once MYBB_ROOT . "inc/class_session.php";

        $session = new session();
        $session->init();
        $mybb->session = &$session;
    }
}

#region request
/**
 * @psalm-pure
 */
function httpRequestOverSecureTransport(): bool
{
    return (
        isset($_SERVER['HTTPS']) &&
        in_array(strtolower($_SERVER['HTTPS']), ['1', 'on'], true)
    ) || (
        isset($_SERVER['SERVER_PORT']) &&
        $_SERVER['SERVER_PORT'] === '443'
    );
}

/**
 * @psalm-pure
 */
function httpRequestFromLocalNetwork(): bool
{
    return filter_var(
        $_SERVER['REMOTE_ADDR'] ?? null,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
    ) === false;
}
#endregion

#region response
function httpAttachUserSession(array $user): void
{
    my_unsetcookie('sid');

    my_setcookie('mybbuser', $user['uid'] . '_' . $user['loginkey'], null, true, 'lax');
}

function httpAttachAcpUserSession(string $sid): void
{
    my_setcookie('acploginattempts', 0);
    my_setcookie('adminsid', $sid, null, true, 'lax');
}

function httpRedirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function httpSendStandardHeaders(): void
{
    $headers = [
        'Content-Security-Policy' => "default-src 'none'; base-uri 'self'; frame-ancestors 'none'; style-src 'self'; font-src 'self'; script-src 'self'; connect-src 'self'; prefetch-src 'self'",
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Referrer-Policy' => 'same-origin',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
    ];

    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
}
#endregion

#region rendering & output
function httpOutputError(string $title, string $message, array $context = []): never
{
    if (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json') {
        httpSendStandardHeaders();
        header('Content-type: application/json');

        echo json_encode([
            'retry' => true,
            'error' => [
                'title' => $title,
                'message' => $message,
            ],
        ]);
    } else {
        $response = template('maintenance/error.twig', [
            'page_title' => $title,
            'title' => $title,
            'message' => $message,
        ] + $context);

        httpSendStandardHeaders();

        echo $response;
    }

    exit;
}

/**
 * Renders a view using the Twig template system with minimal integration with other components.
 */
function template(string $name, array $context = []): string
{
    $loader = new \Twig\Loader\FilesystemLoader([
        MYBB_ROOT . 'inc/views/',
    ]);

    $twig = new \Twig\Environment($loader);

    if (
        function_exists('\MyBB\Maintenance\developmentEnvironment') &&
        developmentEnvironment() === true
    ) {
        $twig->enableDebug();
        $twig->addExtension(new \Twig\Extension\DebugExtension());
    }

    if (!defined('IN_INSTALL')) {
        $twig->addExtension(
            app()->make(\MyBB\Twig\Extensions\CoreExtension::class)
        );
    }

    $twig->addExtension(
        app()->make(\MyBB\Twig\Extensions\LangExtension::class)
    );

    if (defined('IN_INSTALL') || defined('IN_ADMINCP')) {
        $context['relative_asset_path'] = '..';
    } else {
        $context['relative_asset_path'] = '.';
    }

    $response = $twig->render($name, $context);

    return $response;
}
#endregion

#region file-cookie authentication
function authenticatedWithSession(): ?bool
{
    $mybb = app(MyBB::class);

    return in_array($mybb->usergroup['cancp'], [1, 'yes'], true);
}

function authenticatedWithFile(string $path): bool
{
    return authenticationFileExists(
        $path,
        getFileAuthenticationServerCode()
    );
}

function getFileAuthenticationClientCode(): string
{
    static $code = null;

    if ($code === null) {
        $salt = get_ip();

        $cookieValue = $_COOKIE['file_authentication'] ?? null;

        if (is_string($cookieValue) && str_contains($cookieValue, '.')) {
            $cookieData = explode('.', $cookieValue, 2);

            if (
                hash_equals(
                    getFileAuthenticationClientCodeSignature($cookieData[0], $salt),
                    $cookieData[1]
                )
            ) {
                $code = $cookieData[0];
            }
        }

        if ($code === null) {
            $code = random_str(40, true);

            $cookieValue = $code . '.' . getFileAuthenticationClientCodeSignature($code, $salt);

            my_setcookie('file_authentication', $cookieValue, TIME_NOW + 3600, true, 'strict');
        }
    }

    return $code;
}

function getFileAuthenticationServerCode(): string
{
    static $code = null;

    if ($code === null) {
        $code = substr(
            str_replace(
                ['_', '/', '+'],
                '',
                base64_encode(
                    hash('sha512', getFileAuthenticationClientCode(), true),
                ),
            ),
            0,
            20,
        );
    }

    return $code;
}

function getFileAuthenticationClientCodeSignature(string $clientCode, string $salt): string
{
    $key = getCache()->read('internal_settings')['encryption_key'] ?? null;

    if ($key === null) {
        throw new \Exception('Could not load encryption key');
    }

    $data = $clientCode . ';' . $salt;

    return hash_hmac(
        'sha512',
        $data,
        $key,
    );
}

/**
 * Checks for an authentication file using timing attack-safe comparison.
 */
function authenticationFileExists(string $path, string $userCode): bool
{
    $filenames = glob($path . 'auth_*');

    foreach ($filenames as $filename) {
        $code = str_replace('auth_', '', basename($filename));

        if (hash_equals($code, $userCode)) {
            return true;
        }
    }

    return false;
}
#endregion
