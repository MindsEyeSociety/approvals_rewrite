<?php
  include_once("db.inc");

  if( !empty($_REQUEST["delete"]) && empty( $_REQUEST["modify"] ) ) {
  	$delete_ids = array_map("intval", (array)$_REQUEST["delete"]);
  	$placeholders = implode(",", array_fill(0, count($delete_ids), "?"));
  	$query="delete from organizations where id in ($placeholders)";
    $db->query($query, $delete_ids);
    // Keep the public map's Google Sheet in sync (best-effort; never blocks the delete).
    include_once("classes/GoogleSheetsService.php");
    GoogleSheetsService::syncOrgMap();
    header("Location: ModifyOrgList1.php");
		exit();
	} else if ( !empty($_POST["modify"]))  {
    header("Location: ModifyOrgList3.php?modify=" . urlencode($_POST["modify"]) . "&");
		exit();
  } else {
    header("Location: ModifyOrgList1.php");
		exit();
	}
 ?>