<?php
  include_once("db.inc");
  $char_id = intval( $_GET['character_id'] );
  $db->query( "SELECT u.id, c.name as character_name, ".
              "v.name as vss_name from characters c ".
              "LEFT JOIN users u on c.user_id=u.id ".
              "LEFT JOIN vsss v on c.vss_id=v.id where c.id=?", [$char_id]);
  $email_info=$db->nextRow();
  $user_info = $userInfoDAO->getUserInfo($email_info['id']);
  $email_info['name'] = $user_info['name'];
  $email_info['email'] = $user_info['email'];

  // Only send email if the character owner is an active member
  $character_owner_active = $userInfoDAO->isMemberActive( $email_info['id'] );

  if ( $_GET["Action"]=="Reject" ) {
    $db->query("UPDATE characters SET vss_id='0' where id=?", [$char_id]);
    // Email to user saying rejected;
    if( $email_info['email'] != "" && $character_owner_active ) {
      $message = "Greetings $email_info[name],<br>\n".
                 "Your request to assign $email_info[character_name] to the VSS ".
                 "\"$email_info[vss_name]\" has been ".
                 "denied by the VST.  Please assign your character to a different ".
                 "VSS.";
      mail( $email_info['email'], "[Approval System] $email_info[character_name] ".
            "has been rejected from $email_info[vss_name]", $message,
            "From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
            "Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
            "Content-Type: text/html\r\n");
    }
  } elseif ( $_GET["Action"]=="Accept" ) {
	error_log(date('Y-m-d h:i:s').'APPROVED CHAR '.$_GET['character_id'].' USER '.$_SESSION['user_id']."\n",3,__DIR__ . '/vssmove.log');
    $db->query("UPDATE characters SET approved_in_vss='1' where id=?", [$char_id]);
    // Email to user saying accepted;
    if( $email_info['email'] != "" && $character_owner_active ) {
      $message = "Greetings $email_info[name],<br>\n".
                 "Your request to assign $email_info[character_name] to the VSS ".
                 "\"$email_info[vss_name]\" has been approved by the VST.";
      mail( $email_info['email'], "[Approval System] $email_info[character_name] ".
            "has been accepted into $email_info[vss_name]", $message,
            "From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
            "Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
            "Content-Type: text/html\r\n");
    }
  }
  header("Location: ModifyVSSCharacterList1.php");
  exit;
  include_once("titlebar.php");

?>
<?php include_once("footerbar.inc") ?>