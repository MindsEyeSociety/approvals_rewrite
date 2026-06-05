<?php
/**
 * Exports all player characters and their applications as XML.
 * Intended for data migration/reporting — not used in normal operation.
 */
include_once("../db.inc");

function xmlentities($string, $quote_style = ENT_COMPAT) {
    $trans = get_html_translation_table(HTML_ENTITIES, $quote_style);
    foreach ($trans as $key => $value) {
        $trans[$key] = '&#' . ord($key) . ';';
    }
    return strtr($string, $trans);
}

header("Content-Type: text/xml");
echo "<?xml version=\"1.0\"?>\n";
echo "<recordset>\n";

$db->query("SELECT id, name, ww_number, org_id FROM users");
$players = $db->getAllRows();

foreach ($players as $player) {
    echo "  <player name=\"{$player['name']}\" camnumber=\"{$player['ww_number']}\">\n";

    $db->query(
        "SELECT c.id, c.name, c.vss_id, v.venue FROM characters c LEFT JOIN venues v ON c.venue_id = v.id WHERE user_id = ?",
        [$player['id']]
    );
    $characters = $db->getAllRows();

    foreach ($characters as $character) {
        echo "    <character name=\"{$character['name']}\" venue=\"{$character['venue']}\">\n";

        $db->query(
            "SELECT id, app_number, status, category, description, mechanics FROM applications WHERE character_id = ?",
            [$character['id']]
        );
        $applications = $db->getAllRows();

        foreach ($applications as $application) {
            $status      = xmlentities($application['status']);
            $mechanics   = xmlentities($application['mechanics']);
            $description = xmlentities($application['description']);

            echo "      <application number=\"{$application['app_number']}\" status=\"$status\" category=\"{$application['category']}\">";
            echo "        <description>$description</description>\n";
            echo "        <mechanics>$mechanics</mechanics>\n";
            echo "      </application>\n";
        }
        echo "    </character>\n";
    }
    echo "  </player>\n";
}

echo "</recordset>";
?>
