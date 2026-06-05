<?php
/**
 * DB connectivity test page — verifies the configured database is reachable.
 * Access at /utility/test.php (restrict in nginx to trusted IPs in production).
 */
include_once("../db.inc");

echo "<h2>Database Connection Test</h2>";
echo "<pre>PHP Version: " . phpversion() . "</pre>\n";
echo "<pre>Current time: " . date('Y-m-d H:i:s') . "</pre>\n\n";

$result = $db->query("SELECT id, org_name, nation, region FROM organizations ORDER BY org_name LIMIT 5");
$rows   = $db->getAllRows();

if (count($rows) > 0) {
    echo "<p style='color:green'>Connected — found " . count($rows) . " org rows</p>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>Org Name</th><th>Nation</th><th>Region</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['org_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['nation'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['region'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange'>Connected but no rows returned from organizations table.</p>";
}
?>
