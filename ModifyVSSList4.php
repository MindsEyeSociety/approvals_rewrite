<?php
$vss = array();
$vss['venue_id'] = $_POST['venue_id'];
$vss['storyteller_id'] = $_POST['storyteller_id'];
$vss['name'] = stripslashes($_POST['name']);
$vss['vss'] = stripslashes($_POST['vss']);
$vss['email'] = stripslashes($_POST['email']);
$vss['org_id'] = $_POST['org_id'];
$vss['id'] = $_POST['id'];

include_once( "db.inc" );

if ( $vss["org_id"]>"0" && $vss["name"]!="" ) {
	$query="update vsss set ".
		"venue_id=?, ".
		"storyteller_id=?, ".
		"name=?, ".
		"email=?, ".
		"vss=?, ".
		"org_id=?, ".
		"modified=? ".
		"where id=?";
	$params = [	
  		$vss['venue_id'],
  		$vss['storyteller_id'],
  		$vss['name'],
  		$vss['email'],
  		$vss['vss'],
  		$vss['org_id'],
		time(),
  		$vss['id']
	];
  	$db->query($query, $params);
  
    header("Location: ModifyVSSList1.php");
} elseif ( $_POST["org_id"]==0 ) {
	header("Location: ModifyVSSList3.php?modify={$vss['id']}&message=InvalidOrg&");		
} else {
	header("Location: ModifyVSSList3.php?modify={$vss['id']}&message=NoName&");		
} 
