<?php
error_log("Point 1: Start of test2.php");

include_once("db.inc");

error_log("Point 2: db.inc included");

include_once("include/oauth_helper.php");
error_log("Point 3: oauth_helper.php included");

handle_oauth_login();
error_log("Point 4: handle_oauth_login completed");
//
// If we reach here, user is logged in - set up additional session data
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    error_log("Point 5: User is logged in - starting member check");
    $member_number = $_SESSION['cam_number'] ?? null;
    if (!$member_number || $member_number == 'undefined') {
        error_log("Point 6: No member number - exiting");
        echo "You do not appear to have a member number. Please return to <a href='http://portal.modernenigmasociety.org/membership/'>the Portal</a> to either become a member or claim your membership number";
        die();
    }
    error_log($_SESSION['cam_number']);
    die();
    $_SESSION['cam_number'] = $member_number;
    $_SESSION['user_id'] = getUserID($_SESSION['cam_number']);
    error_log("Point 7: user_id set");

    //include_once("session_setup.inc");
    //error_log("Point 9: session_setup.inc included");

    // Redirect to appropriate page
    if (!$_SESSION['org_id']) {
        error_log("Point 9: Redirecting to UserDisplay.php");
        #header('Location: UserDisplay.php?mode=edit');
        #echo("<a href='UserDisplay.php?mode=edit'>UserDisplay.php?mode=edit</a>");
    } else if ( ( array_key_exists( "admin_org_list", $_SESSION ) &&
                 count($_SESSION["admin_org_list"] ) > 0 ) ||
                 ( array_key_exists( "admin_vss_list", $_SESSION ) &&
                 count($_SESSION["admin_vss_list"] ) > 0 )
    ) {
        error_log("Point 10: Redirecting to app_main.php");
        #header( "Location: app_main.php" );
        echo("<a href='app_main.php'>app_main.php</a>");
    } else {
        error_log("Point 11: Redirecting to MyApps.php");
        #header( "Location: MyApps.php" );
        echo("<a href='MyApps.php'>MyApps.php</a>");
    }
    exit;
} else {
    error_log("Point 12: Not logged in - unsetting user_id");
    unset($_SESSION['user_id']);
    echo "Login failed. Please try again.";
}
?>
