<?php
// Include db.inc EARLY so session is ready before we use $_SESSION
include_once("db.inc");

$CLIENT_ID     = $SETTINGS["OAUTH_CLIENT_ID"];
$CLIENT_SECRET = $SETTINGS["OAUTH_CLIENT_SECRET"];
$REDIRECT_URI  = $SETTINGS["OAUTH_REDIRECT_URI"];
$token_url     = $SETTINGS["OAUTH_TOKEN_URL"];

if (isset($_GET['error'])) {
    $err = htmlspecialchars($_GET['error_description'] ?? 'Unknown error');
    error_log("OAuth error: $err");
    die("Authorization error: $err");
}

if (!isset($_GET['code'])) {
    error_log("No authorization code received.");
    die("No authorization code received.");
}

$code = $_GET['code'];
$stored_state = $_SESSION['oauth_state'] ?? null;

if (!isset($_GET['state']) || $_GET['state'] !== $stored_state) {
    unset($_SESSION['oauth_state']);
    error_log("Invalid state parameter - CSRF check failed. Expected: " . ($stored_state ?? 'none') . ", Got: " . ($_GET['state'] ?? 'none'));
    die("Invalid state parameter - possible CSRF attack.");
}

unset($_SESSION['oauth_state']);

// Exchange code for tokens
$ch = curl_init($token_url);
curl_setopt_array($ch, array(
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(array(
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $REDIRECT_URI,
        'client_id'     => $CLIENT_ID,
        'client_secret' => $CLIENT_SECRET,
    )),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
));
$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("cURL error: $curl_error");
    die("cURL error: " . htmlspecialchars($curl_error));
}
if ($http_code !== 200) {
    error_log("Token HTTP $http_code: $response");
    die("Token request failed (HTTP $http_code): " . htmlspecialchars($response));
}

$token_data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg() . " - Raw: $response");
    die("JSON decode error: " . json_last_error_msg());
}
if (isset($token_data['error'])) {
    $err = $token_data['error_description'] ?? 'Unknown token error';
    error_log("Token error: $err");
    die("Token error: " . htmlspecialchars($err));
}

$_SESSION['access_token']  = $token_data['access_token'];
$_SESSION['refresh_token'] = $token_data['refresh_token'] ?? null;
$_SESSION['expires_in']    = $token_data['expires_in'] ?? 0;
$_SESSION['token_time']    = time();

// Fetch user info
$userinfo_url = $SETTINGS["OAUTH_API_URL"];
$ch = curl_init($userinfo_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer " . $_SESSION['access_token']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userinfo_response  = curl_exec($ch);
$userinfo_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error         = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log("cURL error (userinfo): $curl_error");
}

$userinfo = json_decode($userinfo_response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Userinfo JSON decode error: " . json_last_error_msg());
    $userinfo = [];
}

$_SESSION['user_id']    = getUserID($userinfo['membershipNumber']);
$_SESSION['cam_number'] = $userinfo['membershipNumber'] ?? '';
$_SESSION['email']      = $userinfo['emailAddress'] ?? '';
$_SESSION['username']   = $userinfo['nickname'] ?? $userinfo['firstName'] ?? '';
$_SESSION['logged_in']  = true;
unset($_SESSION['oauth_state']);

header("Location: /index.php");
exit;
?>
