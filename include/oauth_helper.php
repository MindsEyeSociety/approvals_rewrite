<?php
function handle_oauth_login() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        return; // already authenticated
    }

    global $SETTINGS;

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $auth_url = 'https://portal.modernenigmasociety.org/oauth/v2/auth?' . http_build_query([
        'client_id'     => $SETTINGS["OAUTH_CLIENT_ID"],
        'redirect_uri'  => $SETTINGS["OAUTH_REDIRECT_URI"],
        'response_type' => 'code',
        'scope'         => '',
        'state'         => $state,
    ]);

    header('Location: ' . $auth_url);
    exit;
}
?>
