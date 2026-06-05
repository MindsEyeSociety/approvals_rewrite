<?php
include_once("db.inc");

if (($_POST["name"] ?? "") == "") {
    header("Location: AddNPC.php?errmsg=2&");
    exit; // Always exit after a header redirect
}

$vss_npc = 0;
$org_npc = 0;
$npc_id = 0;

if (preg_match("/NPC:(-?\d+)/", $_POST["Type"], $matches)) {
    $npc_id = (int)$matches[1];
    
    // 1. Check for existing character safely
    $check_sql = "SELECT id FROM characters 
                  WHERE name = ? AND user_id = '0' AND vss_id = ? AND active = 1";
    
    $db->query($check_sql, [$_POST["name"], $npc_id]);

    if ($db->numRows() == 0) {
        // 2. Prepare base variables for the INSERT
        $name            = $_POST["name"];
        $venue           = $_POST["venue"];
        $subtype         = $_POST["SubType"];
        $background      = $_POST["Background"];
        $character_sheet = $_POST["character_sheet"];
        
        // Logic for NPC types
        if ($npc_id > 0) {
            $vss_npc = 1;
            $org_id  = '';
            $vss_id  = $npc_id;
            $approved = 1;
        } else {
            $org_npc = 1;
            $npc_id  = abs($npc_id); // Convert negative to positive
            $org_id  = $npc_id;
            $vss_id  = -$npc_id;
            $approved = 0;
        }

        // 3. Build the Clean INSERT Statement
        $insert_sql = "INSERT INTO characters 
                       (name, venue_id, subtype, user_id, active, char_type, 
                        org_id, vss_id, approved_in_vss, last_updated, 
                        background, character_sheet, char_dead) 
                       VALUES (?, ?, ?, 0, 1, 'NPC', ?, ?, ?, NOW(), ?, ?, '0')";

        $insert_params = [
            $name,
            $venue,
            $subtype,
            $org_id,
            $vss_id,
            $approved,
            $background,
            $character_sheet
        ];

        $db->query($insert_sql, $insert_params);

        // 4. Get the last ID
        // Note: If you update your DB class, you could use $db->insertId() here
        $db->query("SELECT LAST_INSERT_ID() as maxid");
        $row = $db->nextRow();
        $max_id = $row["maxid"];

        // 5. Redirects
        if (($_POST["redirect"] ?? "") == "AppDetails") {
            header("Location: AppDetails.php?mode=add&char_id=$max_id&");
        } elseif ($vss_npc) {
            header("Location: ModifyVSSCharacterList1.php");
        } elseif ($org_npc) {
            header("Location: app_main.php");
        }
        exit;
        
    } else {
        header("Location: AddNPC.php?errmsg=1&");
        exit;
    }
}
?>
