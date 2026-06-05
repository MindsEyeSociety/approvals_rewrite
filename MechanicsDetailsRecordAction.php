<?php
  include_once("db.inc");
  $pagetitle="Custom Mechanics";

	if ( isset($_POST['delete']) ) {
		 if ( isset($_POST['ConfirmDelete'] ) ) {
		 		$db->query("delete from mechanics where id=?", [$_POST["id"]]);
				header("Location: MechanicsMain.php?message=MechanicsDeleted&");
				exit;
		 } else {
		 	 header("Location: MechanicsDetails.php?id=" . urlencode($_POST["id"]) . "&mode=Edit&message=DeleteNotConfirmed&");
			 exit;
		 }
	}
	
	if ( $_POST['mode']=="Add" ) {
		 $query="INSERT into mechanics ".
		 				"(venue_id,category,title,description,mechanics,sources,prerequisites) ".
						"values (?,?,?,?,?,?,?)";
		 $params = [
			$_POST["venue_id"],
			$_POST["category"],
			$_POST["title"],
			$_POST["description"],
			$_POST["mechanics"],
			$_POST["sources"],
			$_POST["prerequisites"]
		 ];
		$db->query($query, $params);
		$ThisID=$db->getInsertID();
		$message="message=NewMechanicsAdded&";
	} else {
		$query="UPDATE mechanics set ".
				   "venue_id=?, ".
					 "category=?, ".
					 "title=?, ".
					 "description=?, ".
					 "mechanics=?, ".
					 "sources=?, ".
					 "prerequisites=? ".
					 "WHERE id=?";
		$params = [
			$_POST["venue_id"],
			$_POST["category"],
			$_POST["title"],
			$_POST["description"],
			$_POST["mechanics"],
			$_POST["sources"],
			$_POST["prerequisites"],
			$_POST["id"]
		];
		$db->query($query, $params);
		$ThisID=$_POST["id"];
		$message="message=MechanicsUpdated&";
	}
	header("Location: MechanicsDetails.php?id=" . urlencode($ThisID) . "&" . $message);
	exit;
?>