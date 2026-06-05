<?php

include_once("db.inc");
	
$modify = $_POST["modify"];

if ( is_array( $_POST["delete"] ) && count($_POST["delete"]) > 0) {
	$delete_ids = array_map("intval", $_POST["delete"]);
	$placeholders = implode(",", array_fill(0, count($delete_ids), "?"));
	$query = "DELETE FROM category_venue WHERE category_id in ($placeholders)";
	$db->query($query, $delete_ids);
	$query = "DELETE FROM categories WHERE id in ($placeholders)";
	$db->query($query, $delete_ids);
	$modifyInDelete = in_array($modify, $_POST["delete"]);
} 
if( isset($_POST["modify"]) && !$modifyInDelete ) {
	header("Location: ModifyCategoryList3.php?modify=$_POST[modify]&");
} else {
	header("Location: ModifyCategoryList1.php");
}
