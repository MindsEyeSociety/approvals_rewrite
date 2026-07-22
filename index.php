<?php
include_once("db.inc");

include_once("include/oauth_helper.php");

handle_oauth_login();

// If we reach here, user is logged in - set up additional session data
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $member_number = $_SESSION['cam_number'] ?? null;
    if (!$member_number || $member_number == 'undefined') {
        echo "You do not appear to have a member number. Please return to <a href='http://portal.modernenigmasociety.org/membership/'>the Portal</a> to either become a member or claim your membership number";
        exit;
    }
    $_SESSION['cam_number'] = $member_number;
    enforce_early_access();   // early access / phased rollout gate (if $SETTINGS["EARLY_ACCESS_ENABLED"] is true)
                              // non-whitelisted users (by cam_number vs the "early-access" file) are shown the denial message here
    $_SESSION['user_id'] = getUserID($_SESSION['cam_number']);
    include_once("session_setup.inc");

    //include_once("session_setup.inc");

    // Redirect to appropriate page
    if (!$_SESSION['org_id']) {
        header('Location: UserDisplay.php?mode=edit');
        echo("<a href='UserDisplay.php?mode=edit'>UserDisplay.php?mode=edit</a>");
    } else if ( ( array_key_exists( "admin_org_list", $_SESSION ) &&
                 count($_SESSION["admin_org_list"] ) > 0 ) ||
                 ( array_key_exists( "admin_vss_list", $_SESSION ) &&
                 count($_SESSION["admin_vss_list"] ) > 0 )
    ) {
        header( "Location: app_main.php" );
        echo("<a href='app_main.php'>app_main.php</a>");
    } else {
        header( "Location: MyApps.php" );
        echo("<a href='MyApps.php'>MyApps.php</a>");
    }
    exit;
} else {
    unset($_SESSION['user_id']);
    echo "Login failed. Please try again.";
}
?>
