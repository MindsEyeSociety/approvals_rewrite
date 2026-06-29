<?php
  include_once( "db.inc" );

  if ( $_POST["table"]=="earnedxp" ) {
  	$datestamp = strtotime( stripslashes( $_POST["earneddate"] ) );
		$mysqldate = strftime( '%Y-%m-%d', $datestamp ); 
  	$query="update earnedxp set ".
		"eventname=?, ".
		"earneddate=?, ".
		"xpearned=?, ".
		"notes=? ".
		"where id=?";
	$params = [
		$_POST["eventname"],
		$mysqldate,
		$_POST["xpearned"],
		$_POST["notes"],
		$_POST["id"]
	];
  } else {
  	$datestamp = strtotime( stripslashes( $_POST["spentdate"] ) );
		$mysqldate = strftime( '%Y-%m-%d', $datestamp ); 
  	$query="update spentxp set ".
		"itembought=?, ".
		"spentdate=?, ".
		"xpspent=?, ".
		"notes=? ".
		"where id=?";
	$params = [
		$_POST["itembought"],
		$mysqldate,
		$_POST["xpspent"],
		$_POST["notes"],
		$_POST["id"]
	];
  }

  $db->query($query, $params);

  header("Location: ModifyCharacterXPList1.php?character_id=$_POST[character_id]&");
?>


