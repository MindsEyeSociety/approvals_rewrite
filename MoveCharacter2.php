<?php
  include_once("db.inc");
  require_once("include/vss_selection.inc");
  include_once("classes/ApplicationService.php");
  include_once("classes/EmailService.php");

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
        $query = "SELECT u.* FROM organizations o LEFT JOIN users u ON u.id = o.admin_user_id WHERE o.id=?";
        $params = [ -1 * (int)$vss_id ];
      } else {
        $query = "SELECT u.* FROM vsss v LEFT JOIN users u ON u.id = v.storyteller_id WHERE v.id=?";
        $params = [ (int)$vss_id ];
      }
      $db->query( $query, $params );
      if( $storyteller = $db->nextRow() )
      {
        $characterDAO = $daoFactory->getCharacterDAO();
        $organizationDAO = $daoFactory->getOrganizationDAO();
        $vssDAO = $daoFactory->getVSSDAO();
        $applicationService = new ApplicationService( $organizationDAO, $characterDAO, $vssDAO, $userInfoDAO );
        $emailService = new EmailService( $userInfoDAO, $characterDAO, $applicationService );

        $chain = $applicationService->getEscalationSTIDsForVssSelection( $vss_id, $storyteller['id'] );
        $emailService->sendVssJoinRequestEmail( $storyteller['id'], $chain, $row['player_name'], $character['name'], $character['subtype'] );
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
