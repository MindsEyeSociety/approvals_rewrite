<?php
  include_once( "db.inc" );
  $id = (int)$_POST['id'];
	$name = $_POST['name'];
	$venue = $_POST['venue'];
	$subtype = $_POST['SubType'];
  $character_sheet = $_POST['character_sheet'];
  $background = $_POST['Background'];
	$active = $_POST['Active'] ? "1" : "0";
	$char_dead = $_POST['char_dead'] ? "1" : "0";
	if ( $char_dead ) $active = 0;
	
	if ( isset($_POST["delete"]) ) {
		 if ( isset($_POST["ConfirmDelete"]) ) {
		 		$db->query("SELECT id from applications where character_id='$id'");
				while ( $row=$db->nextRow() ) {
				  $db->query("DELETE from revisions where application_id='$row[id]'"); 
				  $db->query("DELETE from comments where app_id='$row[id]'"); 
				  $db->query("DELETE from applications where id='$row[id]'"); 
				}
		 		$db->query("delete from characters where id='$id'");
				header("Location: ModifyCharacterList1.php?message=deletesuccessful&");
				exit();
		 } else {
		 	 header("Location: ModifyCharacterList3.php?modify=$id&message=deletenotconfirmed&");
			 exit();
		 }
	} else {
  	$db->query("Select * from characters where id='$id'");
    if ( $db->numRows() > 0 ) {
      $query = "update characters set name=?,venue_id=?,".
  		         "subtype=?,active=?, last_updated=?".
  		         ", background=?, ".
							 "character_sheet=?, char_dead=? ".
							 "where id=?";
  		
      $params = [
		$name,
		$venue,
		$subtype,
		$active,
		strftime('%D'),
	       	$background,
		$character_sheet,
	       	$char_dead,
		$id
	];
    	$db->query($query, $params);
			
			if ( $char_dead ) {
				 $db->query("SELECT id, status from applications where character_id='$id'");
				 $applications = array();
				 while ( $approw=$db->nextRow() ) {
				   $applications[] = $approw;
				 }
				 
				 foreach( $applications as $approw ) {
				   $db->query("UPDATE applications set status='Removed', status_change_date=NOW() where id='$approw[id]'");
					 if ( $approw["status"]!='Removed' && $approw["status"] != 'Denied' ) {
  					 $query="INSERT into revisions ( revision, revision_date, user_id, application_id ) ".
  					 				"values ('Character Dead/Retired.  Status set to Removed.', now(), '$_SESSION[user_id]','$approw[id]')";
  					 $db->query($query);
					 } 
				 }
			}
    }
    header("Location: ModifyNPCList1.php");
		exit();
	}
 ?>
