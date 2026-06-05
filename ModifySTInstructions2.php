<?php
  include_once("db.inc");

	if( isset( $_POST["org_id"] ) ) {
    $db->query("delete from instructions where org_id=?", [$_POST["org_id"]]);
    $db->query("insert into instructions (org_id,instruction) values (?, ?)", [$_POST["org_id"], $_POST["instruction"]]);
    $urlstring = "org_id=" . urlencode($_POST['org_id']) . "&";
	} elseif( isset( $_POST["vss_id"] ) ) {
    $db->query("delete from instructions where vss_id=?", [$_POST["vss_id"]]);
    $db->query("insert into instructions (vss_id,instruction) values (?, ?)", [$_POST["vss_id"], $_POST["instruction"]]);
    $urlstring = "vss_id=" . urlencode($_POST['vss_id']) . "&";
	}

	header("Location: ModifySTInstructions1.php?message=ChangesMade&" . $urlstring);
 ?>