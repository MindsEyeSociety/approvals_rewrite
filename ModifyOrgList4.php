<?php
  include_once("db.inc");
	$qualifier_list = array( 'nation', 'region', 'domain', 'chapter' );
	$qualifiers = array();
	$params = [];
  foreach( $qualifier_list as $qualifier ) {
		$value = $_POST[$qualifier] ?? '';
		$qualifiers[] = "$qualifier = ?";
		$params[] = $value;
	};
	$params[] = $_POST["id"] ?? 0;

	$query = "select * from organizations where " . implode( " and ", $qualifiers) .
		" and id!=?";
	$db->query($query, $params);
  if ( $db->numRows() == 0 ) { 
    $email_is_google = email_is_google($_POST['email']);
    $query="update organizations set ".
  		"nation=?, ".
  		"region=?, ".
  		"domain=?, ".
  		"chapter=?, ".
  		"org_name=?, ".
  		"city=?, ".
  		"state=?, ".
  		"country=?, ".
  		"email=?, ".
  		"email_is_google=?, ".
  		"admin_user_id=?, ".
  		"active=? ".
  		"where id=?";
	$params = [
    		$_POST['nation'],
    		$_POST['region'],
    		$_POST['domain'],
    		$_POST['chapter'],
    		$_POST['orgName'],
    		$_POST['city'],
    		$_POST['state'],
    		$_POST['country'],
    		$_POST['email'],
    		$email_is_google,
    		$_POST['admin_user_id'],
    		isset($_POST['active']) ? 1 : 0,
    		$_POST['id']
    	];
  	$db->query($query, $params);
  }
  header("Location: ModifyOrgList1.php");
  
?>

