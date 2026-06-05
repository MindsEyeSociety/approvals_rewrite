<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>=== TEST SCRIPT STARTED ===</pre>\n";

include_once("include/settings.inc");
include_once("include/Database.class.php");
include_once("include/ResultSet.class.php");

echo "<pre>Settings loaded:</pre>\n";
echo "<pre>Host: " . htmlspecialchars($SETTINGS["APPROVALS_SERVER"]) . "</pre>\n";
echo "<pre>User: " . htmlspecialchars($SETTINGS["APPROVALS_USERNAME"]) . "</pre>\n";
echo "<pre>DB:   " . htmlspecialchars($SETTINGS["APPROVALS_DATABASE"]) . "</pre>\n";

$db = new Database(
    $SETTINGS["APPROVALS_SERVER"],
    $SETTINGS["APPROVALS_USERNAME"],
    $SETTINGS["APPROVALS_PASSWORD"],
    $SETTINGS["APPROVALS_DATABASE"]
);

if (!$db->dbh) {
    echo "<pre>ERROR: Connection handle is NULL after constructor!</pre>\n";
    die();
}

echo "<pre>Connection handle created successfully.</pre>\n";
echo "<pre>Client info: " . mysqli_get_client_info() . "</pre>\n";
echo "<pre>Server info: " . mysqli_get_server_info($db->dbh) . "</pre>\n";
echo "<pre>Host info:   " . mysqli_get_host_info($db->dbh) . "</pre>\n";
echo "<pre>Protocol:    " . mysqli_get_proto_info($db->dbh) . "</pre>\n";

echo "<pre>Current default database: " . mysqli_select_db($db->dbh, null) . "</pre>\n";

// Test a simple status query
$status_result = mysqli_query($db->dbh, "SELECT VERSION() AS ver, DATABASE() AS db");
if ($status_result) {
    $status_row = mysqli_fetch_assoc($status_result);
    echo "<pre>Server version from query: " . $status_row['ver'] . "</pre>\n";
    echo "<pre>Current DB from query:     " . $status_row['db'] . "</pre>\n";
} else {
    echo "<pre>Status query failed: " . mysqli_error($db->dbh) . "</pre>\n";
}

$count_query = "SELECT COUNT(*) AS total FROM organizations";
$count_res = mysqli_query($db->dbh, $count_query);
if ($count_res) {
    $count_row = mysqli_fetch_assoc($count_res);
    echo "<pre>Raw mysqli count: " . ($count_row ? $count_row['total'] : 'no row') . "</pre>\n";
    mysqli_free_result($count_res);
} else {
    echo "<pre>Raw count failed: " . mysqli_error($db->dbh) . "</pre>\n";
}

// Test the organizations table
$query = "SELECT COUNT(*) AS total FROM organizations";
echo "<pre>Running count query: " . htmlspecialchars($query) . "</pre>\n";

$count_result = $db->query($query);

if (!$count_result) {
    echo "<pre>Count query FAILED: " . mysqli_error($db->dbh) . "</pre>\n";
    die();
}

$count_row = $count_result->nextRow();  // or getAllRows()[0] depending on ResultSet
if ($count_row) {
    echo "<pre>Organizations count from app code: " . $count_row['total'] . "</pre>\n";
} else {
    echo "<pre>No row returned from count query</pre>\n";
}

// Try fetching a few rows
$select_query = "SELECT id, org_name, nation, region FROM organizations ORDER BY org_name LIMIT 5";
echo "<pre>Running select query: " . htmlspecialchars($select_query) . "</pre>\n";

$select_result = $db->query($select_query);

if (!$select_result) {
    echo "<pre>Select query FAILED: " . mysqli_error($db->dbh) . "</pre>\n";
} else {
    $rows = $select_result->getAllRows();
    echo "<pre>Rows fetched: " . count($rows) . "</pre>\n";
    if (!empty($rows)) {
        echo "<pre>Sample rows:\n" . print_r($rows, true) . "</pre>\n";
    } else {
        echo "<pre>No rows in result set</pre>\n";
    }
}

echo "<pre>=== TEST SCRIPT FINISHED ===</pre>\n";

?>
