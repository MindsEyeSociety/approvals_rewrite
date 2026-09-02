<?php
  include_once("db.inc");
  require_once("include/vss_selection.inc");

  $vss_id = resolveVssSelection(
    isset($_POST['localvss_id']) ? $_POST['localvss_id'] : "",
    isset($_POST['totalvss_id']) ? $_POST['totalvss_id'] : ""
  );

  $query = "SELECT c.*, u.id AS player_id FROM characters c ".
           "LEFT JOIN users u ON u.id = c.user_id ".
           "WHERE c.id=?";
  $params = [ $_POST['char_id'] ];
  $db->query($query, $params);
  if ( $character = $db->nextRow() ) {
  	if($character['player_id'] == 0)
  	{
  		$row['player_name'] = "NPC";
  	}
  	else
  	{
    	$user_info = $userInfoDAO->getUserInfo($character['player_id']);
    	$row['player_name'] = $user_info['name'];
  	}
    if( $character['vss_id'] != $vss_id ) {
      if( $vss_id < 0 ) {
        $query = "SELECT u.* FROM organizations o LEFT JOIN users u ON u.id = o.admin_user_id WHERE o.id=-1*$vss_id";
      } else {
        $query = "SELECT u.* FROM vsss v LEFT JOIN users u ON u.id = v.storyteller_id WHERE v.id=$vss_id";
      }
      $db->query( $query );
      if( $storyteller = $db->nextRow() )
      {
 	$user_info = $userInfoDAO->getUserInfo($storyteller['id']);
 	$storyteller['email'] = $user_info['email'];
  if( $storyteller['email'] != '' && $userInfoDAO->isMemberActive( $storyteller['id'] ) ) {
        $message = "$row[player_name] has applied to add their character, ".
          "$character[name] ($character[subtype]) to your Venue Style Sheet.  ".
          "If you accept this character, you will have Low approval over this ".
          "character, and will be able to view the character under the ".
          "Character Census in the Storyteller menu.<br><br>\n" .
          "This is an automated message from the Approval system.<br>".
          "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/index.php\">Log in</a> to ".
          "the system and choose \"VSS Character List\" from the Storyteller ".
          "menue to accept or reject this character.";
        mail( $storyteller['email'], "[Approval System] A character has applied to join your VSS",
              $message, "From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
              "Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
              "Content-Type: text/html\r\n" );
          }
      }
    }
  } 
  
  if( $vss_id != 0 ) {
 	error_log(date('Y-m-d h:i:s').' CHAR '.$_POST['char_id'].' VSS '.$vss_id.' USER '.$_SESSION['user_id']."\n",3,__DIR__ . '/vssmove.log');
    $query="UPDATE characters SET vss_id=?, approved_in_vss='0' WHERE id=?";
	$params = [ $vss_id, $_POST['char_id'] ];
    $db->query($query, $params);
     if ( $_POST["redirect"]=="AppDetails" ) {
       header( "Location: AppDetails.php?mode=add&char_id=$_POST[char_id]&appUser_id=$_POST[user_id]&" );
     } elseif ( $_POST['redirect'] == 'DisplayCharacter' ) {
      header("Location: DisplayCharacter.php?char_id=$_POST[char_id]&" );
    } else {
       header( "Location: ModifyCharacterList1.php?message=vss_assigned" );
     }
  } else {
    header( "Location: MoveCharacter.php?char_id=$_POST[char_id]&redirect=$_POST[redirect]&message=require_vss" );
  }
  
 ?>
