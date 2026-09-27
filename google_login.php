<?php
/**
 * Google OAuth 2.0 Login for MyBB
 * Assignment implementation - adds "Login with Google" capability
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'google_login.php');
$templatelist = '';
require_once "./global.php";

// ==== CONFIG - Google OAuth credentials ====
$google_client_id     = getenv('GOOGLE_CLIENT_ID');
$google_client_secret = getenv('GOOGLE_CLIENT_SECRET');
$google_redirect_uri  = 'http://localhost/mybb/google_login.php';
// ============================================

if(!$mybb->get_input('code'))
{
	// Step 1: Send the user to Google's login/consent page
	$params = array(
		'client_id'     => $google_client_id,
		'redirect_uri'  => $google_redirect_uri,
		'response_type' => 'code',
		'scope'         => 'openid email profile',
		'access_type'   => 'online',
		'prompt'        => 'select_account'
	);
	$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params);
	header('Location: '.$auth_url);
	exit;
}

// Step 2: Google has redirected back here with an authorization code
$code = $mybb->get_input('code');

// Exchange the authorization code for an access token
$token_url = 'https://oauth2.googleapis.com/token';
$post_fields = array(
	'code'          => $code,
	'client_id'     => $google_client_id,
	'client_secret' => $google_client_secret,
	'redirect_uri'  => $google_redirect_uri,
	'grant_type'    => 'authorization_code'
);

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if($response === false)
{
	error("Google login failed (connection error): ".$curl_error);
}

$token_data = json_decode($response, true);

if(empty($token_data['access_token']))
{
	error("Google login failed: could not obtain access token. Response: ".htmlspecialchars_uni($response));
}

$access_token = $token_data['access_token'];

// Step 3: Fetch the logged-in Google user's profile
$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo?access_token='.urlencode($access_token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$profile_response = curl_exec($ch);
curl_close($ch);

$profile = json_decode($profile_response, true);

if(empty($profile['email']))
{
	error("Google login failed: could not retrieve your email address from Google.");
}

$google_email = $db->escape_string($profile['email']);
$google_name  = !empty($profile['name']) ? $profile['name'] : explode('@', $profile['email'])[0];

// Step 4: Find a matching MyBB account, or create one
$query = $db->simple_select("users", "uid, username, loginkey", "email='{$google_email}'", array('limit' => 1));
$existing_user = $db->fetch_array($query);

if($existing_user)
{
	$uid = $existing_user['uid'];
}
else
{
	// No MyBB account linked to this Google email yet - create one
	require_once MYBB_ROOT.'inc/datahandlers/user.php';
	$userhandler = new UserDataHandler('insert');

	$base_username = preg_replace('/[^A-Za-z0-9_]/', '', $google_name);
	if(empty($base_username))
	{
		$base_username = 'GoogleUser';
	}

		$username = $base_username;
	$suffix = 1;
	while($db->fetch_field($db->simple_select("users", "COUNT(uid) as count", "username='".$db->escape_string($username)."'"), "count") > 0)
	{
		$username = $base_username.$suffix;
		$suffix++;
	}
	$random_password = random_str(16, true);

	$user_data = array(
		"username"  => $username,
		"password"  => $random_password,
		"password2" => $random_password,
		"email"     => $profile['email'],
		"email2"    => $profile['email'],
		"usergroup" => 2, // Registered users group
		"language"  => '',
		"timezone"  => $mybb->settings['timezoneoffset'],
		"options"   => array(
			"allownotices"       => 1,
			"hideemail"          => 0,
			"subscriptionmethod" => 0,
			"receivepms"         => 1,
			"receivefrombuddy"   => 1,
			"pmnotice"           => 1,
			"invisible"          => 0,
			"showimages"         => 1,
			"showvideos"         => 1,
			"showsigs"           => 1,
			"showavatars"        => 1,
			"showquickreply"     => 1,
			"tpp"                => 0,
			"ppp"                => 0,
			"dstcorrection"      => 2,
			"threadmode"         => ''
		)
	);

	$userhandler->set_data($user_data);

	if(!$userhandler->validate_user())
	{
		$errors = implode('<br />', $userhandler->get_friendly_errors());
		error("Could not create an account from your Google login: {$errors}");
	}

	$new_user = $userhandler->insert_user();
	$uid = $new_user['uid'];
}

// Step 5: Log the user in by issuing MyBB's session cookie
$loginkey = md5(random_str(50));
$db->update_query("users", array(
	"loginkey"   => $loginkey,
	"lastactive" => TIME_NOW,
	"lastvisit"  => TIME_NOW
), "uid='{$uid}'");

my_setcookie("mybbuser", $uid."_".$loginkey, null, true, "Lax");

$db->update_query("sessions", array("uid" => $uid), "sid='{$session->sid}'");

redirect("index.php", "You have been logged in with your Google account.");
