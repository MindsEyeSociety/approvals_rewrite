<?php
  include_once( "db.inc" );

	
   if ( $_POST["table"]=="earnedxp" ) {
   	$datestamp = strtotime( stripslashes( $_POST["earneddate"] ) );
	$mysqldate = strftime( '%Y-%m-%d', $datestamp );
   	$query=sprintf(
   		"INSERT INTO earnedxp (".
   		"  eventname," .
   		"  earneddate, ".
   		"  xpearned, ".
   		"  notes, ".
   		"  character_id".
   		") values (".
   		"  '%s',".
   		"  '%s',".
		"  '%s',".
		"  '%s',".
		"  %d".
		")",
		$db->escape($_POST["eventname"]),
		$mysqldate,
		$db->escape($_POST["xpearned"]),
		$db->escape($_POST["notes"]),
		$_POST["character_id"]
	);
  } else {
  	$datestamp = strtotime( stripslashes( $_POST["spentdate"] ) );
		$mysqldate = strftime( '%Y-%m-%d', $datestamp ); 
  	$query="insert into spentxp ".
		"(itembought, spentdate, xpspent, notes, character_id) ".
		"values ".
		"('$_POST[itembought]', '$mysqldate', ".
		"'$_POST[xpspent]', '$_POST[notes]', ".
		"'$_POST[character_id]')";
  }

  $db->query($query);
	
  header("Location: ModifyCharacterXPList1.php?character_id=$_POST[character_id]&");
?>


